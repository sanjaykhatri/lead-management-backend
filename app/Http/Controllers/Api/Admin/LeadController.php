<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\LeadNote;
use App\Events\LeadStatusUpdated;
use App\Events\LeadAssigned;
use App\Notifications\LeadAssignedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LeadController extends Controller
{
    public function index(Request $request)
    {
        $query = Lead::with(['location', 'serviceProvider']);

        // Filter by location
        if ($request->has('location_id')) {
            $query->where('location_id', $request->location_id);
        }

        // Filter by date
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $leads = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json($leads);
    }

    public function show(Lead $lead)
    {
        $lead->load(['location', 'serviceProvider']);
        return response()->json($lead);
    }

    public function update(Request $request, Lead $lead)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'sometimes|in:new,contacted,closed',
            'service_provider_id' => 'sometimes|nullable|exists:service_providers,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $oldStatus = $lead->status;
        $oldProviderId = $lead->service_provider_id;
        
        $lead->update($request->only(['status', 'service_provider_id']));
        $lead->refresh();

        // Create note for status change
        if ($request->has('status') && $oldStatus !== $lead->status) {
            LeadNote::create([
                'lead_id' => $lead->id,
                'user_id' => $request->user()->id,
                'note' => "Status changed from {$oldStatus} to {$lead->status}",
                'type' => 'status_change',
                'metadata' => ['old_status' => $oldStatus, 'new_status' => $lead->status],
            ]);
            
            // Broadcast status update event
            event(new LeadStatusUpdated($lead, $oldStatus));
        }

        // Handle provider assignment/reassignment
        if ($request->has('service_provider_id') && $oldProviderId !== $lead->service_provider_id) {
            if ($lead->service_provider_id) {
                $provider = $lead->serviceProvider;
                $provider->notify(new LeadAssignedNotification($lead));
                event(new LeadAssigned($lead));
                
                LeadNote::create([
                    'lead_id' => $lead->id,
                    'user_id' => $request->user()->id,
                    'note' => "Lead assigned to {$provider->name}",
                    'type' => 'assignment',
                ]);
            }
        }

        return response()->json($lead->load(['location', 'serviceProvider']));
    }

    public function reassign(Request $request, Lead $lead)
    {
        $validator = Validator::make($request->all(), [
            'service_provider_id' => 'required|exists:service_providers,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $oldProviderId = $lead->service_provider_id;
        $oldProvider = $oldProviderId ? \App\Models\ServiceProvider::find($oldProviderId) : null;
        
        $lead->update(['service_provider_id' => $request->service_provider_id]);
        $lead->refresh()->load('serviceProvider');

        // Send notifications to new provider
        if ($lead->service_provider_id && $lead->serviceProvider) {
            $provider = $lead->serviceProvider;
            $provider->notify(new LeadAssignedNotification($lead));
            event(new LeadAssigned($lead));
            
            // Create note
            $noteText = $oldProviderId && $oldProvider
                ? "Lead reassigned from {$oldProvider->name} to {$provider->name}"
                : "Lead assigned to {$provider->name}";
                
            LeadNote::create([
                'lead_id' => $lead->id,
                'user_id' => $request->user()->id,
                'note' => $noteText,
                'type' => 'assignment',
            ]);
        }

        return response()->json($lead->load(['location', 'serviceProvider']));
    }
}
