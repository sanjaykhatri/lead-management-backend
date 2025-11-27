<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class ProviderAuthController extends Controller
{
    public function signup(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:service_providers,email',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $provider = ServiceProvider::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'address' => $request->address,
            'password' => Hash::make($request->password),
        ]);

        $token = $provider->createToken('provider-token')->plainTextToken;

        return response()->json([
            'provider' => $provider->load('stripeSubscription'),
            'token' => $token,
            'message' => 'Account created successfully. Please subscribe to access leads.',
        ], 201);
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $provider = ServiceProvider::where('email', $request->email)->with('stripeSubscription')->first();

        if (!$provider || !Hash::check($request->password, $provider->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        // Check if account is active
        if (!$provider->is_active) {
            return response()->json([
                'message' => 'Your account has been deactivated. Please contact admin to activate your account.',
                'account_inactive' => true,
            ], 403);
        }

        // Check subscription status
        $hasActiveSubscription = $provider->hasActiveSubscription();
        
        $token = $provider->createToken('provider-token')->plainTextToken;

        return response()->json([
            'provider' => $provider,
            'token' => $token,
            'has_active_subscription' => $hasActiveSubscription,
            'subscription_status' => $provider->stripeSubscription?->status ?? 'none',
        ]);
    }

    public function user(Request $request)
    {
        return response()->json($request->user());
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }
}
