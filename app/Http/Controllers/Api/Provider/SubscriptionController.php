<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use App\Models\ServiceProvider;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;
use Stripe\Checkout\Session;
use Stripe\BillingPortal\Session as BillingPortalSession;
use Stripe\Subscription as StripeSubscription;

class SubscriptionController extends Controller
{
    public function getPlans()
    {
        $plans = SubscriptionPlan::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('price')
            ->get();
        
        return response()->json($plans);
    }

    public function getStatus(Request $request)
    {
        $provider = $request->user();
        $provider->load(['stripeSubscription.plan']);

        // Best-effort sync with Stripe if we have a subscription id but status is not active
        try {
            $localSub = $provider->stripeSubscription;
            if ($localSub && $localSub->stripe_subscription_id && $localSub->status !== 'active') {
                Stripe::setApiKey(config('services.stripe.secret'));

                $stripeSub = StripeSubscription::retrieve($localSub->stripe_subscription_id);

                // Map Stripe status
                $statusMap = [
                    'active' => 'active',
                    'canceled' => 'canceled',
                    'past_due' => 'past_due',
                    'incomplete' => 'incomplete',
                    'trialing' => 'trialing',
                ];
                $mappedStatus = $statusMap[$stripeSub->status] ?? 'incomplete';

                $localSub->update([
                    'status' => $mappedStatus,
                    'current_period_end' => isset($stripeSub->current_period_end)
                        ? date('Y-m-d H:i:s', $stripeSub->current_period_end)
                        : null,
                ]);

                // Reload relations
                $provider->load(['stripeSubscription.plan']);
            }
        } catch (\Exception $e) {
            // Don't break the API if Stripe sync fails; just log it.
            \Log::error('Failed to sync Stripe subscription status in getStatus', [
                'provider_id' => $provider->id,
                'error' => $e->getMessage(),
            ]);
        }
        
        return response()->json([
            'has_active_subscription' => $provider->hasActiveSubscription(),
            'subscription' => $provider->stripeSubscription,
            'current_plan' => $provider->stripeSubscription?->plan,
        ]);
    }

    public function createCheckoutSession(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'plan_id' => 'required|exists:subscription_plans,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        Stripe::setApiKey(config('services.stripe.secret'));
        
        $provider = $request->user();
        $plan = SubscriptionPlan::findOrFail($request->plan_id);

        // Check if provider already has an active subscription to this plan
        if ($provider->stripeSubscription && 
            $provider->stripeSubscription->subscription_plan_id === $plan->id &&
            in_array($provider->stripeSubscription->status, ['active', 'trialing'])) {
            return response()->json([
                'error' => 'You are already subscribed to this plan'
            ], 400);
        }

        try {
            Log::info('Creating Stripe checkout session for provider', [
                'provider_id' => $provider->id,
                'plan_id' => $plan->id,
                'stripe_price_id' => $plan->stripe_price_id,
                'trial_days' => $plan->trial_days,
            ]);
            
            // Create or get Stripe customer
            $customerId = $provider->stripeSubscription?->stripe_customer_id;
            
            if (!$customerId) {
                $customerData = [
                    'email' => $provider->email,
                    'name' => $provider->name,
                    'metadata' => ['service_provider_id' => $provider->id],
                ];
                
                // Add address if available (required for Indian regulations)
                if ($provider->address || $provider->zip_code) {
                    $customerData['address'] = [
                        'line1' => $provider->address ?? '',
                        'postal_code' => $provider->zip_code ?? '',
                        'country' => 'IN', // Default to India, can be made configurable
                    ];
                }
                
                $customer = \Stripe\Customer::create($customerData);
                $customerId = $customer->id;
            } else {
                // Update existing customer with address if not already set
                try {
                    $existingCustomer = \Stripe\Customer::retrieve($customerId);
                    if (!$existingCustomer->address || !$existingCustomer->address->line1) {
                        \Stripe\Customer::update($customerId, [
                            'address' => [
                                'line1' => $provider->address ?? '',
                                'postal_code' => $provider->zip_code ?? '',
                                'country' => 'IN',
                            ],
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to update customer address during checkout', [
                        'provider_id' => $provider->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Cancel any existing active subscriptions for this provider
            $this->cancelExistingSubscriptions($provider, $customerId);

            // Build subscription data
            $subscriptionData = [
                'metadata' => [
                    'service_provider_id' => $provider->id,
                    'subscription_plan_id' => $plan->id,
                ],
            ];
            
            // Only add trial_period_days if it's greater than 0 (Stripe requires minimum 1 day)
            // If trial is 0, customer will be charged immediately upon checkout completion
            if ($plan->trial_days > 0) {
                $subscriptionData['trial_period_days'] = $plan->trial_days;
            }
            // When trial is 0, Stripe automatically charges immediately - no need for additional config

            // Create checkout session
            $session = Session::create([
                'customer' => $customerId,
                'mode' => 'subscription',
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price' => $plan->stripe_price_id,
                    'quantity' => 1,
                ]],
                'subscription_data' => $subscriptionData,
                'success_url' => config('services.frontend.url') . '/provider/subscription?success=true',
                'cancel_url' => config('services.frontend.url') . '/provider/subscription?canceled=true',
                'metadata' => ['service_provider_id' => $provider->id, 'subscription_plan_id' => $plan->id],
            ]);

            // Update or create subscription record
            $subscription = $provider->stripeSubscription()->updateOrCreate(
                ['service_provider_id' => $provider->id],
                [
                    'stripe_customer_id' => $customerId,
                    'status' => 'incomplete',
                    'subscription_plan_id' => $plan->id,
                ]
            );

            // Log subscription creation attempt
            SubscriptionHistory::log(
                $provider->id,
                'created',
                'incomplete',
                null,
                $customerId,
                $plan->id,
                $plan->price,
                "Checkout session created for plan: {$plan->name}",
                ['checkout_session_id' => $session->id, 'plan_name' => $plan->name]
            );

            return response()->json(['checkout_url' => $session->url]);
        } catch (\Exception $e) {
            \Log::error('Failed to create Stripe checkout session', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'provider_id' => $provider->id,
                'plan_id' => $plan->id,
                'stripe_price_id' => $plan->stripe_price_id,
            ]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function upgradePlan(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'plan_id' => 'required|exists:subscription_plans,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        Stripe::setApiKey(config('services.stripe.secret'));
        
        $provider = $request->user();
        $newPlan = SubscriptionPlan::findOrFail($request->plan_id);
        
        if (!$provider->stripeSubscription || !$provider->stripeSubscription->stripe_subscription_id) {
            return response()->json(['error' => 'No active subscription found'], 404);
        }

        // Check if provider is already on this plan
        if ($provider->stripeSubscription->subscription_plan_id === $newPlan->id) {
            return response()->json(['error' => 'You are already subscribed to this plan'], 400);
        }

        try {
            // Get current Stripe subscription
            $stripeSubscription = StripeSubscription::retrieve($provider->stripeSubscription->stripe_subscription_id);
            
            // Get current plan
            $currentPlan = $provider->stripeSubscription->plan;
            if (!$currentPlan) {
                return response()->json(['error' => 'Current plan not found'], 404);
            }

            // Calculate pro-rata credit for remaining time
            $currentPeriodEnd = $stripeSubscription->current_period_end;
            $now = time();
            $remainingSeconds = $currentPeriodEnd - $now;
            $totalPeriodSeconds = $currentPeriodEnd - $stripeSubscription->current_period_start;
            
            if ($remainingSeconds > 0 && $totalPeriodSeconds > 0) {
                $prorationFactor = $remainingSeconds / $totalPeriodSeconds;
                $creditAmount = $currentPlanPrice * $prorationFactor;
            } else {
                $creditAmount = 0;
            }

            // Determine if this is an upgrade or downgrade
            // Convert prices to float for comparison (they might be stored as strings)
            $currentPlanPrice = (float) $currentPlan->price;
            $newPlanPrice = (float) $newPlan->price;
            $isUpgrade = $newPlanPrice > $currentPlanPrice;
            $eventType = $isUpgrade ? 'upgraded' : 'downgraded';

            // Update subscription with new plan (pro-rata with immediate payment)
            $updatedSubscription = StripeSubscription::update($stripeSubscription->id, [
                'items' => [[
                    'id' => $stripeSubscription->items->data[0]->id,
                    'price' => $newPlan->stripe_price_id,
                ]],
                'proration_behavior' => 'create_prorations', // Stripe handles pro-rata automatically
                'metadata' => [
                    'service_provider_id' => $provider->id,
                    'subscription_plan_id' => $newPlan->id,
                ],
            ]);

            // Update customer with name and address (required for Indian regulations)
            try {
                $addressData = [];
                if ($provider->address) {
                    $addressData['line1'] = $provider->address;
                }
                if ($provider->zip_code) {
                    $addressData['postal_code'] = $provider->zip_code;
                }
                $addressData['country'] = 'IN'; // Default to India, can be made configurable
                
                \Stripe\Customer::update($provider->stripeSubscription->stripe_customer_id, [
                    'name' => $provider->name,
                    'address' => $addressData,
                ]);
            } catch (\Exception $e) {
                Log::warning('Failed to update customer address', [
                    'provider_id' => $provider->id,
                    'error' => $e->getMessage(),
                ]);
                // Don't fail the upgrade if address update fails, but log it
            }

            // Immediately invoice and pay for the prorated amount
            try {
                // Create an invoice for the subscription change (this includes prorations)
                $invoice = \Stripe\Invoice::create([
                    'customer' => $provider->stripeSubscription->stripe_customer_id,
                    'subscription' => $updatedSubscription->id,
                    'auto_advance' => false, // We'll finalize and pay manually
                ]);
                
                // Finalize the invoice (this calculates prorations)
                $invoice->finalizeInvoice();
                $invoice = \Stripe\Invoice::retrieve($invoice->id); // Refresh to get updated status
                
                // Pay the invoice immediately if there's an amount due
                if ($invoice->amount_due > 0) {
                    $paidInvoice = $invoice->pay();
                    Log::info('Prorated invoice paid immediately', [
                        'provider_id' => $provider->id,
                        'invoice_id' => $invoice->id,
                        'amount_paid' => $paidInvoice->amount_paid / 100,
                        'amount_due' => $invoice->amount_due / 100,
                        'event_type' => $eventType,
                    ]);
                } else {
                    Log::info('Prorated invoice (no payment needed - credit applied)', [
                        'provider_id' => $provider->id,
                        'invoice_id' => $invoice->id,
                        'amount_due' => $invoice->amount_due / 100,
                        'event_type' => $eventType,
                    ]);
                }
            } catch (\Stripe\Exception\InvalidRequestException $e) {
                // If invoice already exists or other Stripe-specific error
                if (strpos($e->getMessage(), 'already has an open invoice') !== false) {
                    // Try to retrieve and pay the existing invoice
                    try {
                        $invoices = \Stripe\Invoice::all([
                            'customer' => $provider->stripeSubscription->stripe_customer_id,
                            'subscription' => $updatedSubscription->id,
                            'status' => 'open',
                            'limit' => 1,
                        ]);
                        
                        if (!empty($invoices->data)) {
                            $invoice = $invoices->data[0];
                            if ($invoice->amount_due > 0) {
                                $invoice->pay();
                                Log::info('Paid existing open invoice', [
                                    'provider_id' => $provider->id,
                                    'invoice_id' => $invoice->id,
                                ]);
                            }
                        }
                    } catch (\Exception $retryError) {
                        Log::warning('Could not pay existing invoice', [
                            'provider_id' => $provider->id,
                            'error' => $retryError->getMessage(),
                        ]);
                    }
                } else {
                    Log::error('Failed to create/pay prorated invoice', [
                        'provider_id' => $provider->id,
                        'error' => $e->getMessage(),
                        'stripe_error' => $e->getStripeCode(),
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Failed to create/pay prorated invoice', [
                    'provider_id' => $provider->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                // Continue anyway - Stripe will handle billing on next cycle
            }

            // Get old plan for logging
            $oldPlan = $currentPlan;

            // Update local subscription record
            $updateData = [
                'stripe_subscription_id' => $updatedSubscription->id,
                'subscription_plan_id' => $newPlan->id,
                'status' => $updatedSubscription->status,
            ];
            
            // Only update current_period_end if it exists
            if (isset($updatedSubscription->current_period_end) && $updatedSubscription->current_period_end) {
                $updateData['current_period_end'] = \Carbon\Carbon::createFromTimestamp($updatedSubscription->current_period_end);
            }
            
            $provider->stripeSubscription->update($updateData);

            // Log upgrade/downgrade
            SubscriptionHistory::log(
                $provider->id,
                $eventType,
                $updatedSubscription->status,
                $updatedSubscription->id,
                $provider->stripeSubscription->stripe_customer_id,
                $newPlan->id,
                $newPlan->price,
                ucfirst($eventType) . " from {$oldPlan->name} to {$newPlan->name}",
                [
                    'old_plan_id' => $oldPlan->id,
                    'old_plan_name' => $oldPlan->name,
                    'old_plan_price' => $currentPlanPrice,
                    'new_plan_name' => $newPlan->name,
                    'new_plan_price' => $newPlanPrice,
                    'proration_credit' => $creditAmount,
                    'charged_immediately' => true,
                ]
            );

            return response()->json([
                'message' => 'Plan ' . $eventType . ' successfully',
                'subscription' => $provider->stripeSubscription->fresh(['plan']),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to upgrade/downgrade plan', [
                'provider_id' => $provider->id,
                'plan_id' => $request->plan_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'error' => 'Failed to update plan: ' . $e->getMessage()
            ], 500);
        }
    }

    public function createBillingPortalSession(Request $request)
    {
        Stripe::setApiKey(config('services.stripe.secret'));
        
        $provider = $request->user();

        if (!$provider->stripeSubscription || !$provider->stripeSubscription->stripe_customer_id) {
            return response()->json(['error' => 'No subscription found'], 404);
        }

        try {
            $session = BillingPortalSession::create([
                'customer' => $provider->stripeSubscription->stripe_customer_id,
                'return_url' => config('services.frontend.url') . '/provider/dashboard',
            ]);

            return response()->json(['portal_url' => $session->url]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Cancel any existing active subscriptions for a provider
     */
    private function cancelExistingSubscriptions(ServiceProvider $provider, $customerId)
    {
        try {
            Stripe::setApiKey(config('services.stripe.secret'));

            // Get all active subscriptions for this customer from Stripe
            $stripeSubscriptions = \Stripe\Subscription::all([
                'customer' => $customerId,
                'status' => 'active',
                'limit' => 100,
            ]);

            // Also check for trialing subscriptions
            $trialingSubscriptions = \Stripe\Subscription::all([
                'customer' => $customerId,
                'status' => 'trialing',
                'limit' => 100,
            ]);

            $allActiveSubscriptions = array_merge($stripeSubscriptions->data, $trialingSubscriptions->data);

            foreach ($allActiveSubscriptions as $stripeSub) {
                // Skip if this is the same subscription we're about to create (shouldn't happen, but safety check)
                if ($provider->stripeSubscription && $provider->stripeSubscription->stripe_subscription_id === $stripeSub->id) {
                    continue;
                }

                // Cancel the subscription immediately (delete it in Stripe)
                \Stripe\Subscription::retrieve($stripeSub->id)->delete();

                Log::info('Canceled existing subscription before creating new one', [
                    'provider_id' => $provider->id,
                    'canceled_subscription_id' => $stripeSub->id,
                    'customer_id' => $customerId,
                ]);

                // Log the cancellation
                $planId = $stripeSub->metadata->subscription_plan_id ?? null;
                $amount = $stripeSub->items->data[0]->price->unit_amount / 100 ?? null;

                SubscriptionHistory::log(
                    $provider->id,
                    'canceled',
                    'canceled',
                    $stripeSub->id,
                    $customerId,
                    $planId,
                    $amount,
                    "Subscription canceled due to new subscription creation",
                    ['canceled_by' => 'system', 'reason' => 'new_subscription_created']
                );
            }
        } catch (\Exception $e) {
            Log::error('Failed to cancel existing subscriptions', [
                'provider_id' => $provider->id,
                'customer_id' => $customerId,
                'error' => $e->getMessage(),
            ]);
            // Don't throw - we'll still try to create the new subscription
        }
    }

    public function getHistory(Request $request)
    {
        $provider = $request->user();
        
        $history = SubscriptionHistory::where('service_provider_id', $provider->id)
            ->with(['plan'])
            ->orderBy('event_date', 'desc')
            ->paginate(20);
        
        return response()->json($history);
    }
}

