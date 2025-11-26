<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StripeSubscription;
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
        
        $stripeSubscription = StripeSubscription::where('stripe_customer_id', $customerId)->first();
        
        if ($stripeSubscription) {
            $status = $this->mapStripeStatus($subscription->status);
            
            $stripeSubscription->update([
                'stripe_subscription_id' => $subscription->id,
                'status' => $status,
                'current_period_end' => isset($subscription->current_period_end) 
                    ? date('Y-m-d H:i:s', $subscription->current_period_end) 
                    : null,
            ]);
        }
    }

    private function handleSubscriptionDeleted($subscription)
    {
        $customerId = $subscription->customer;
        
        $stripeSubscription = StripeSubscription::where('stripe_customer_id', $customerId)->first();
        
        if ($stripeSubscription) {
            $stripeSubscription->update([
                'status' => 'canceled',
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
