<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\User;
use App\Models\ActivityLog;
use App\Events\LeadStatusUpdated;
use App\Events\LeadAssigned;
use App\Notifications\LeadAssignedNotification;
use App\Notifications\AdminLeadAssignedNotification;
use App\Notifications\AdminLeadStatusUpdatedNotification;
use App\Notifications\ProviderLeadStatusUpdatedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

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
        $user = $request->user();
        
        $lead->update($request->only(['status', 'service_provider_id']));
        $lead->refresh();

        // Create note for status change
        if ($request->has('status') && $oldStatus !== $lead->status) {
            LeadNote::create([
                'lead_id' => $lead->id,
                'user_id' => $user->id,
                'note' => "Status changed from {$oldStatus} to {$lead->status}",
                'type' => 'status_change',
                'metadata' => ['old_status' => $oldStatus, 'new_status' => $lead->status],
            ]);

            // Log the activity
            ActivityLog::log(
                $lead->id,
                'status_updated',
                'admin',
                $user->id,
                $user->name,
                "Status changed from {$oldStatus} to {$lead->status}",
                ['old_status' => $oldStatus, 'new_status' => $lead->status]
            );
            
            // Broadcast status update event
            try {
                Log::info('Firing LeadStatusUpdated event (will be queued)', [
                    'lead_id' => $lead->id,
                    'old_status' => $oldStatus,
                    'new_status' => $lead->status,
                    'provider_id' => $lead->service_provider_id,
                    'updated_by' => 'admin',
                    'updated_by_name' => $user->name,
                    'queue_connection' => config('queue.default'),
                ]);
                event(new LeadStatusUpdated($lead, $oldStatus, 'admin', $user->name));
                Log::info('LeadStatusUpdated event queued successfully', [
                    'lead_id' => $lead->id,
                    'note' => 'Event will be processed by queue worker. Make sure queue:work is running.',
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to broadcast LeadStatusUpdated event', [
                    'error' => $e->getMessage(),
                    'lead_id' => $lead->id,
                ]);
            }

            // Create database notifications
            try {
                // Notify all admin users
                $adminUsers = User::whereIn('role', ['super_admin', 'admin', 'manager'])->get();
                foreach ($adminUsers as $admin) {
                    if ($admin->id !== $user->id) { // Don't notify the creator
                        $admin->notify(new AdminLeadStatusUpdatedNotification($lead, $oldStatus));
                    }
                }

                // Notify the assigned provider if lead has one
                if ($lead->service_provider_id && $lead->serviceProvider) {
                    $lead->serviceProvider->notify(new ProviderLeadStatusUpdatedNotification($lead, $oldStatus));
                }
            } catch (\Exception $e) {
                Log::error('Failed to create notifications for lead status update', [
                    'error' => $e->getMessage(),
                    'lead_id' => $lead->id,
                ]);
            }
        }

        // Handle provider assignment/reassignment
        if ($request->has('service_provider_id') && $oldProviderId !== $lead->service_provider_id) {
            if ($lead->service_provider_id) {
                $provider = $lead->serviceProvider;
                $provider->notify(new LeadAssignedNotification($lead));
                
                try {
                    Log::info('Firing LeadAssigned event (update assignment)', [
                        'lead_id' => $lead->id,
                        'provider_id' => $lead->service_provider_id,
                    ]);
                    event(new LeadAssigned($lead));
                } catch (\Exception $e) {
                    Log::error('Failed to broadcast LeadAssigned event (update assignment)', [
                        'error' => $e->getMessage(),
                        'lead_id' => $lead->id,
                    ]);
                }

                // Notify all admins of the (re)assignment
                try {
                    $adminUsers = User::whereIn('role', ['super_admin', 'admin', 'manager'])->get();
                    foreach ($adminUsers as $admin) {
                        if ($admin->id !== $user->id) { // Don't notify the creator
                            $admin->notify(new AdminLeadAssignedNotification($lead));
                        }
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to create admin notifications for lead (re)assignment', [
                        'error' => $e->getMessage(),
                        'lead_id' => $lead->id,
                    ]);
                }
                
                // Create note
                $oldProviderName = $oldProvider ? $oldProvider->name : 'Unknown';
                $noteText = $oldProviderId 
                    ? "Lead reassigned from {$oldProviderName} to {$provider->name}"
                    : "Lead assigned to {$provider->name}";
                
                LeadNote::create([
                    'lead_id' => $lead->id,
                    'user_id' => $user->id,
                    'note' => $noteText,
                    'type' => 'assignment',
                ]);

                // Log the activity
                ActivityLog::log(
                    $lead->id,
                    $oldProviderId ? 'reassigned' : 'assigned',
                    'admin',
                    $user->id,
                    $user->name,
                    $oldProviderId 
                        ? "Lead reassigned to {$provider->name}"
                        : "Lead assigned to {$provider->name}",
                    ['provider_id' => $provider->id, 'provider_name' => $provider->name, 'old_provider_id' => $oldProviderId]
                );
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

        $user = $request->user();
        $oldProviderId = $lead->service_provider_id;
        $oldProvider = $oldProviderId ? \App\Models\ServiceProvider::find($oldProviderId) : null;
        
        $lead->update(['service_provider_id' => $request->service_provider_id]);
        $lead->refresh()->load('serviceProvider');

        // Send notifications to new provider
        if ($lead->service_provider_id && $lead->serviceProvider) {
            $provider = $lead->serviceProvider;
            $provider->notify(new LeadAssignedNotification($lead));
            
            try {
                Log::info('Firing LeadAssigned event (reassign)', [
                    'lead_id' => $lead->id,
                    'provider_id' => $lead->service_provider_id,
                ]);
                event(new LeadAssigned($lead));
            } catch (\Exception $e) {
                Log::error('Failed to broadcast LeadAssigned event (reassign)', [
                    'error' => $e->getMessage(),
                    'lead_id' => $lead->id,
                ]);
            }

            // Notify all admins of the reassignment
            try {
                $adminUsers = User::whereIn('role', ['super_admin', 'admin', 'manager'])->get();
                foreach ($adminUsers as $admin) {
                    if ($admin->id !== $user->id) { // Don't notify the creator
                        $admin->notify(new AdminLeadAssignedNotification($lead));
                    }
                }
            } catch (\Exception $e) {
                Log::error('Failed to create admin notifications for lead reassignment', [
                    'error' => $e->getMessage(),
                    'lead_id' => $lead->id,
                ]);
            }
            
            // Create note
            $noteText = $oldProviderId && $oldProvider
                ? "Lead reassigned from {$oldProvider->name} to {$provider->name}"
                : "Lead assigned to {$provider->name}";
                
            LeadNote::create([
                'lead_id' => $lead->id,
                'user_id' => $user->id,
                'note' => $noteText,
                'type' => 'assignment',
            ]);

            // Log the activity
            ActivityLog::log(
                $lead->id,
                'reassigned',
                'admin',
                $user->id,
                $user->name,
                $noteText,
                ['provider_id' => $provider->id, 'provider_name' => $provider->name, 'old_provider_id' => $oldProviderId, 'old_provider_name' => $oldProvider?->name]
            );
        }

        return response()->json($lead->load(['location', 'serviceProvider']));
    }
}
