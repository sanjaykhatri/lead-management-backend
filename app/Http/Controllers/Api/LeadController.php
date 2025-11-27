<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Location;
use App\Models\ServiceProvider;
use App\Services\LeadAssignmentService;
use App\Events\LeadAssigned;
use App\Notifications\LeadAssignedNotification;
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
        
        // Use assignment service to assign lead
        $assignmentService = new LeadAssignmentService();
        $assignedProvider = $assignmentService->assignLead(
            new Lead(['zip_code' => $request->zip_code, 'location_id' => $location->id]),
            $location
        );
        
        $serviceProviderId = $assignedProvider?->id;

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

        // Always broadcast to admin (for all new leads)
        event(new LeadAssigned($lead));
        
        // Send notifications if provider is assigned
        if ($assignedProvider) {
            // Send SMS and database notification
            $assignedProvider->notify(new LeadAssignedNotification($lead));
        }

        return response()->json([
            'message' => 'Lead submitted successfully',
            'lead' => $lead,
        ], 201);
    }
}
