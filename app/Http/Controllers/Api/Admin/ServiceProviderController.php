<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Stripe\Stripe;
use Stripe\Checkout\Session;
use Stripe\BillingPortal\Session as BillingPortalSession;

class ServiceProviderController extends Controller
{
    public function index()
    {
        $providers = ServiceProvider::with('stripeSubscription')->get();
        return response()->json($providers);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:service_providers,email',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $provider = ServiceProvider::create($request->all());

        return response()->json($provider, 201);
    }

    public function show(ServiceProvider $serviceProvider)
    {
        $serviceProvider->load(['stripeSubscription', 'locations', 'leads']);
        return response()->json($serviceProvider);
    }

    public function update(Request $request, ServiceProvider $serviceProvider)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:service_providers,email,' . $serviceProvider->id,
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $serviceProvider->update($request->all());

        return response()->json($serviceProvider->load('stripeSubscription'));
    }

    public function destroy(ServiceProvider $serviceProvider)
    {
        $serviceProvider->delete();
        return response()->json(['message' => 'Service provider deleted']);
    }

    public function createCheckoutSession(Request $request, ServiceProvider $serviceProvider)
    {
        Stripe::setApiKey(config('services.stripe.secret'));

        try {
            // Create or get Stripe customer
            $customerId = $serviceProvider->stripeSubscription?->stripe_customer_id;
            
            if (!$customerId) {
                $customer = \Stripe\Customer::create([
                    'email' => $serviceProvider->email,
                    'name' => $serviceProvider->name,
                    'metadata' => ['service_provider_id' => $serviceProvider->id],
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
                'success_url' => config('services.frontend.url') . '/admin/service-providers?success=true',
                'cancel_url' => config('services.frontend.url') . '/admin/service-providers?canceled=true',
                'metadata' => ['service_provider_id' => $serviceProvider->id],
            ]);

            // Update or create subscription record
            $subscription = $serviceProvider->stripeSubscription()->updateOrCreate(
                ['service_provider_id' => $serviceProvider->id],
                ['stripe_customer_id' => $customerId, 'status' => 'incomplete']
            );

            return response()->json(['checkout_url' => $session->url]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function createBillingPortalSession(Request $request, ServiceProvider $serviceProvider)
    {
        Stripe::setApiKey(config('services.stripe.secret'));

        if (!$serviceProvider->stripeSubscription || !$serviceProvider->stripeSubscription->stripe_customer_id) {
            return response()->json(['error' => 'No subscription found'], 404);
        }

        try {
            $session = BillingPortalSession::create([
                'customer' => $serviceProvider->stripeSubscription->stripe_customer_id,
                'return_url' => config('services.frontend.url') . '/admin/service-providers',
            ]);

            return response()->json(['portal_url' => $session->url]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
