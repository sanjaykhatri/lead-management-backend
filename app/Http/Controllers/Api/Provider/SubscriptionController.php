<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use App\Models\ServiceProvider;
use Illuminate\Http\Request;
use Stripe\Stripe;
use Stripe\Checkout\Session;
use Stripe\BillingPortal\Session as BillingPortalSession;

class SubscriptionController extends Controller
{
    public function getStatus(Request $request)
    {
        $provider = $request->user();
        $provider->load('stripeSubscription');
        
        return response()->json([
            'has_active_subscription' => $provider->hasActiveSubscription(),
            'subscription' => $provider->stripeSubscription,
        ]);
    }

    public function createCheckoutSession(Request $request)
    {
        Stripe::setApiKey(config('services.stripe.secret'));
        
        $provider = $request->user();

        try {
            // Create or get Stripe customer
            $customerId = $provider->stripeSubscription?->stripe_customer_id;
            
            if (!$customerId) {
                $customer = \Stripe\Customer::create([
                    'email' => $provider->email,
                    'name' => $provider->name,
                    'metadata' => ['service_provider_id' => $provider->id],
                ]);
                $customerId = $customer->id;
            }

            // Create checkout session
            $session = Session::create([
                'customer' => $customerId,
                'mode' => 'subscription',
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price' => config('services.stripe.price_id'),
                    'quantity' => 1,
                ]],
                'success_url' => config('services.frontend.url') . '/provider/subscription?success=true',
                'cancel_url' => config('services.frontend.url') . '/provider/subscription?canceled=true',
                'metadata' => ['service_provider_id' => $provider->id],
            ]);

            // Update or create subscription record
            $subscription = $provider->stripeSubscription()->updateOrCreate(
                ['service_provider_id' => $provider->id],
                ['stripe_customer_id' => $customerId, 'status' => 'incomplete']
            );

            return response()->json(['checkout_url' => $session->url]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
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
}

