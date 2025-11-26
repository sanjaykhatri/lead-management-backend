<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Location;
use App\Models\ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LeadController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'location_slug' => 'required|string|exists:locations,slug',
            'name' => 'required|string|max:255',
            'phone' => 'required|string|regex:/^[\d\s\-\+\(\)]+$/',
            'email' => 'required|email|max:255',
            'zip_code' => 'required|string|max:10',
            'project_type' => 'required|string|max:255',
            'timing' => 'required|string|max:255',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $location = Location::where('slug', $request->location_slug)->firstOrFail();
        
        // Find eligible service providers (active subscription) for this location
        $eligibleProviders = $location->serviceProviders()
            ->whereHas('stripeSubscription', function ($query) {
                $query->where('status', 'active');
            })
            ->get();

        // Auto-assign to first eligible provider if available
        $serviceProviderId = null;
        if ($eligibleProviders->isNotEmpty()) {
            $serviceProviderId = $eligibleProviders->first()->id;
        }

        $lead = Lead::create([
            'location_id' => $location->id,
            'service_provider_id' => $serviceProviderId,
            'name' => $request->name,
            'phone' => $request->phone,
            'email' => $request->email,
            'zip_code' => $request->zip_code,
            'project_type' => $request->project_type,
            'timing' => $request->timing,
            'notes' => $request->notes,
            'status' => 'new',
        ]);

        return response()->json([
            'message' => 'Lead submitted successfully',
            'lead' => $lead,
        ], 201);
    }
}
