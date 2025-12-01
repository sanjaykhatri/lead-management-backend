<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StripeSubscription;
use App\Models\SubscriptionHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    public function handleWebhook(Request $request)
    {
        Stripe::setApiKey(config('services.stripe.secret'));

        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $endpointSecret = config('services.stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $endpointSecret);
        } catch (\Exception $e) {
            Log::error('Stripe webhook error: ' . $e->getMessage());
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        // Handle the event
        switch ($event->type) {
            case 'customer.subscription.created':
            case 'customer.subscription.updated':
                $this->handleSubscriptionUpdate($event->data->object);
                break;
            case 'customer.subscription.deleted':
                $this->handleSubscriptionDeleted($event->data->object);
                break;
            default:
                Log::info('Unhandled webhook event type: ' . $event->type);
        }

        return response()->json(['received' => true]);
    }

    private function handleSubscriptionUpdate($subscription)
    {
        $customerId = $subscription->customer;
        $subscriptionId = $subscription->id;
        $status = $this->mapStripeStatus($subscription->status);
        
        // Get provider ID from metadata or find by customer
        $providerId = $subscription->metadata->service_provider_id ?? null;
        $planId = $subscription->metadata->subscription_plan_id ?? null;
        
        if (!$providerId) {
            // Try to find provider by customer ID
            $stripeSubscription = StripeSubscription::where('stripe_customer_id', $customerId)->first();
            if ($stripeSubscription) {
                $providerId = $stripeSubscription->service_provider_id;
                $planId = $stripeSubscription->subscription_plan_id;
            }
        }
        
        if ($providerId) {
            // If this is a new subscription (status is active/trialing) and provider already has an active subscription,
            // cancel the old one
            if (in_array($status, ['active', 'trialing'])) {
                $this->ensureSingleActiveSubscription($providerId, $subscriptionId, $customerId);
            }
            
            // Update or create subscription record
            $stripeSubscription = StripeSubscription::where('stripe_customer_id', $customerId)->first();
            
            if ($stripeSubscription) {
                $oldStatus = $stripeSubscription->status;
                $oldPlanId = $stripeSubscription->subscription_plan_id;
                
                $stripeSubscription->update([
                    'stripe_subscription_id' => $subscriptionId,
                    'status' => $status,
                    'subscription_plan_id' => $planId ?? $stripeSubscription->subscription_plan_id,
                    'current_period_end' => isset($subscription->current_period_end) 
                        ? date('Y-m-d H:i:s', $subscription->current_period_end) 
                        : null,
                    'trial_ends_at' => isset($subscription->trial_end) && $subscription->trial_end
                        ? date('Y-m-d H:i:s', $subscription->trial_end)
                        : null,
                ]);
                
                // Log the update
                $eventType = $oldStatus === 'incomplete' && $status === 'active' ? 'created' : 'updated';
                $amount = $subscription->items->data[0]->price->unit_amount / 100 ?? null;
                
                SubscriptionHistory::log(
                    $providerId,
                    $eventType,
                    $status,
                    $subscriptionId,
                    $customerId,
                    $planId ?? $oldPlanId,
                    $amount,
                    "Subscription {$eventType}: Status changed from {$oldStatus} to {$status}",
                    [
                        'old_status' => $oldStatus,
                        'new_status' => $status,
                        'stripe_event' => 'customer.subscription.updated',
                    ]
                );
            }
        }
    }

    private function handleSubscriptionDeleted($subscription)
    {
        $customerId = $subscription->customer;
        $subscriptionId = $subscription->id;
        
        $stripeSubscription = StripeSubscription::where('stripe_customer_id', $customerId)->first();
        
        if ($stripeSubscription) {
            $providerId = $stripeSubscription->service_provider_id;
            $planId = $stripeSubscription->subscription_plan_id;
            $amount = $subscription->items->data[0]->price->unit_amount / 100 ?? null;
            
            $stripeSubscription->update([
                'status' => 'canceled',
            ]);
            
            // Log the deletion
            SubscriptionHistory::log(
                $providerId,
                'canceled',
                'canceled',
                $subscriptionId,
                $customerId,
                $planId,
                $amount,
                "Subscription canceled via Stripe webhook",
                ['stripe_event' => 'customer.subscription.deleted']
            );
        }
    }

    /**
     * Ensure only one active subscription exists per provider
     */
    private function ensureSingleActiveSubscription($providerId, $newSubscriptionId, $customerId)
    {
        try {
            Stripe::setApiKey(config('services.stripe.secret'));
            
            // Get all active/trialing subscriptions for this customer
            $activeSubscriptions = \Stripe\Subscription::all([
                'customer' => $customerId,
                'status' => 'all',
                'limit' => 100,
            ]);
            
            foreach ($activeSubscriptions->data as $stripeSub) {
                // Skip the new subscription
                if ($stripeSub->id === $newSubscriptionId) {
                    continue;
                }
                
                // Cancel any other active or trialing subscriptions (delete in Stripe)
                if (in_array($stripeSub->status, ['active', 'trialing'])) {
                    \Stripe\Subscription::retrieve($stripeSub->id)->delete();
                    
                    Log::info('Canceled duplicate subscription via webhook', [
                        'provider_id' => $providerId,
                        'canceled_subscription_id' => $stripeSub->id,
                        'new_subscription_id' => $newSubscriptionId,
                    ]);
                    
                    // Log the cancellation
                    $planId = $stripeSub->metadata->subscription_plan_id ?? null;
                    $amount = $stripeSub->items->data[0]->price->unit_amount / 100 ?? null;
                    
                    SubscriptionHistory::log(
                        $providerId,
                        'canceled',
                        'canceled',
                        $stripeSub->id,
                        $customerId,
                        $planId,
                        $amount,
                        "Duplicate subscription canceled (new subscription created)",
                        ['canceled_by' => 'system', 'reason' => 'duplicate_subscription', 'new_subscription_id' => $newSubscriptionId]
                    );
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to ensure single active subscription', [
                'provider_id' => $providerId,
                'new_subscription_id' => $newSubscriptionId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function mapStripeStatus($stripeStatus)
    {
        $statusMap = [
            'active' => 'active',
            'canceled' => 'canceled',
            'past_due' => 'past_due',
            'incomplete' => 'incomplete',
            'trialing' => 'trialing',
        ];

        return $statusMap[$stripeStatus] ?? 'incomplete';
    }
}
