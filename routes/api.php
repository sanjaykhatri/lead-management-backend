<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\Admin\LeadController as AdminLeadController;
use App\Http\Controllers\Api\Admin\ServiceProviderController;
use App\Http\Controllers\Api\Admin\LocationController;
use App\Http\Controllers\Api\ProviderAuthController;
use App\Http\Controllers\Api\Provider\LeadController as ProviderLeadController;
use App\Http\Controllers\Api\Provider\SubscriptionController as ProviderSubscriptionController;
use App\Http\Controllers\Api\StripeWebhookController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::get('/locations', function () {
    $locations = \App\Models\Location::select('id', 'name', 'slug', 'address')->get();
    return response()->json($locations);
});
Route::post('/leads', [LeadController::class, 'store']);

// Admin authentication
Route::post('/admin/login', [AuthController::class, 'login']);

// Provider authentication (public)
Route::post('/provider/signup', [ProviderAuthController::class, 'signup']);
Route::post('/provider/login', [ProviderAuthController::class, 'login']);

// Admin protected routes
Route::middleware('auth:sanctum')->prefix('admin')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Leads
    Route::get('/leads', [AdminLeadController::class, 'index']);
    Route::get('/leads/{lead}', [AdminLeadController::class, 'show']);
    Route::put('/leads/{lead}', [AdminLeadController::class, 'update']);
    Route::put('/leads/{lead}/reassign', [AdminLeadController::class, 'reassign']);

    // Service Providers
    Route::apiResource('service-providers', ServiceProviderController::class);
    Route::post('/service-providers/{serviceProvider}/stripe-checkout', [ServiceProviderController::class, 'createCheckoutSession']);
    Route::get('/service-providers/{serviceProvider}/billing-portal', [ServiceProviderController::class, 'createBillingPortalSession']);
    Route::post('/service-providers/{serviceProvider}/activate', [ServiceProviderController::class, 'activate']);
    Route::post('/service-providers/{serviceProvider}/deactivate', [ServiceProviderController::class, 'deactivate']);

    // Locations
    Route::apiResource('locations', LocationController::class);
    Route::post('/locations/{location}/assign-providers', [LocationController::class, 'assignProviders']);
});

// Provider protected routes
Route::middleware('auth:sanctum')->prefix('provider')->group(function () {
    Route::get('/user', [ProviderAuthController::class, 'user']);
    Route::post('/logout', [ProviderAuthController::class, 'logout']);
    
    // Subscription
    Route::get('/subscription/status', [ProviderSubscriptionController::class, 'getStatus']);
    Route::post('/subscription/checkout', [ProviderSubscriptionController::class, 'createCheckoutSession']);
    Route::get('/subscription/billing-portal', [ProviderSubscriptionController::class, 'createBillingPortalSession']);
    
    // Leads
    Route::get('/leads', [ProviderLeadController::class, 'index']);
    Route::get('/leads/{lead}', [ProviderLeadController::class, 'show']);
    Route::put('/leads/{lead}', [ProviderLeadController::class, 'update']);
});

// Stripe webhook (no auth required)
Route::post('/stripe/webhook', [StripeWebhookController::class, 'handleWebhook']);

