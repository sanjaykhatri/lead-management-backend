<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SubscriptionPlanController extends Controller
{
    public function index()
    {
        $plans = SubscriptionPlan::orderBy('sort_order')->orderBy('price')->get();
        return response()->json($plans);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'stripe_price_id' => 'required|string|unique:subscription_plans,stripe_price_id',
            'price' => 'required|numeric|min:0',
            'interval' => 'required|in:monthly,yearly',
            'trial_days' => 'nullable|integer|min:0',
            'features' => 'nullable|array',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $plan = SubscriptionPlan::create([
            'name' => $request->name,
            'stripe_price_id' => $request->stripe_price_id,
            'price' => $request->price,
            'interval' => $request->interval,
            'trial_days' => $request->trial_days ?? 0,
            'features' => $request->features ?? [],
            'is_active' => $request->has('is_active') ? $request->is_active : true,
            'sort_order' => $request->sort_order ?? 0,
        ]);

        return response()->json($plan, 201);
    }

    public function show(SubscriptionPlan $subscriptionPlan)
    {
        return response()->json($subscriptionPlan);
    }

    public function update(Request $request, SubscriptionPlan $subscriptionPlan)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'stripe_price_id' => [
                'sometimes',
                'required',
                'string',
                Rule::unique('subscription_plans', 'stripe_price_id')->ignore($subscriptionPlan->id),
            ],
            'price' => 'sometimes|required|numeric|min:0',
            'interval' => 'sometimes|required|in:monthly,yearly',
            'trial_days' => 'nullable|integer|min:0',
            'features' => 'nullable|array',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $subscriptionPlan->update($request->only([
            'name',
            'stripe_price_id',
            'price',
            'interval',
            'trial_days',
            'features',
            'is_active',
            'sort_order',
        ]));

        return response()->json($subscriptionPlan);
    }

    public function destroy(SubscriptionPlan $subscriptionPlan)
    {
        // Check if plan has active subscriptions
        if ($subscriptionPlan->subscriptions()->where('status', 'active')->exists()) {
            return response()->json([
                'error' => 'Cannot delete plan with active subscriptions. Please deactivate it instead.'
            ], 422);
        }

        $subscriptionPlan->delete();
        return response()->json(['message' => 'Plan deleted successfully']);
    }
}

