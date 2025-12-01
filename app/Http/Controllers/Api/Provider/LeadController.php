<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\ActivityLog;
use App\Models\User;
use App\Events\LeadStatusUpdated;
use App\Notifications\ProviderLeadStatusUpdatedNotification;
use App\Notifications\AdminLeadStatusUpdatedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class LeadController extends Controller
{
    public function index(Request $request)
    {
        $provider = $request->user();
        
        // Check if account is active
        if (!$provider->is_active) {
            return response()->json([
                'message' => 'Your account has been deactivated. Please contact admin to activate your account.',
                'account_inactive' => true,
            ], 403);
        }
        
        // Check if provider has active subscription
        if (!$provider->hasActiveSubscription()) {
            return response()->json([
                'message' => 'Please contact admin to activate your account or subscribe to a plan.',
                'has_active_subscription' => false,
            ], 403);
        }
        
        $query = Lead::with(['location', 'serviceProvider'])
            ->where('service_provider_id', $provider->id);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $leads = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json($leads);
    }

    public function show(Request $request, Lead $lead)
    {
        $provider = $request->user();
        
        // Check if account is active
        if (!$provider->is_active) {
            return response()->json([
                'message' => 'Your account has been deactivated. Please contact admin to activate your account.',
                'account_inactive' => true,
            ], 403);
        }
        
        // Check if provider has active subscription
        if (!$provider->hasActiveSubscription()) {
            return response()->json([
                'message' => 'Please contact admin to activate your account or subscribe to a plan.',
                'has_active_subscription' => false,
            ], 403);
        }
        
        // Ensure the lead belongs to this provider
        if ($lead->service_provider_id !== $provider->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $lead->load(['location', 'serviceProvider']);
        return response()->json($lead);
    }

    public function update(Request $request, Lead $lead)
    {
        $provider = $request->user();
        
        // Check if account is active
        if (!$provider->is_active) {
            return response()->json([
                'message' => 'Your account has been deactivated. Please contact admin to activate your account.',
                'account_inactive' => true,
            ], 403);
        }
        
        // Check if provider has active subscription
        if (!$provider->hasActiveSubscription()) {
            return response()->json([
                'message' => 'Please contact admin to activate your account or subscribe to a plan.',
                'has_active_subscription' => false,
            ], 403);
        }
        
        // Ensure the lead belongs to this provider
        if ($lead->service_provider_id !== $provider->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:new,contacted,closed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $oldStatus = $lead->status;
        $lead->update(['status' => $request->status]);

        // Create note and broadcast if status changed
        if ($oldStatus !== $lead->status) {
            LeadNote::create([
                'lead_id' => $lead->id,
                'service_provider_id' => $provider->id,
                'note' => "Status changed from {$oldStatus} to {$lead->status}",
                'type' => 'status_change',
                'metadata' => ['old_status' => $oldStatus, 'new_status' => $lead->status],
            ]);

            // Log the activity
            ActivityLog::log(
                $lead->id,
                'status_updated',
                'provider',
                $provider->id,
                $provider->name,
                "Status changed from {$oldStatus} to {$lead->status}",
                ['old_status' => $oldStatus, 'new_status' => $lead->status]
            );
            
            // Broadcast status update event
            try {
                Log::info('Firing LeadStatusUpdated event (from provider, will be queued)', [
                    'lead_id' => $lead->id,
                    'old_status' => $oldStatus,
                    'new_status' => $lead->status,
                    'provider_id' => $provider->id,
                    'updated_by' => 'provider',
                    'updated_by_name' => $provider->name,
                    'queue_connection' => config('queue.default'),
                ]);
                event(new LeadStatusUpdated($lead, $oldStatus, 'provider', $provider->name));
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

            // Send database notifications
            try {
                // Notify the provider (who made the change)
                $provider->notify(new ProviderLeadStatusUpdatedNotification($lead, $oldStatus));

                // Notify all admin users
                $adminUsers = User::whereIn('role', ['super_admin', 'admin', 'manager'])->get();
                foreach ($adminUsers as $admin) {
                    $admin->notify(new AdminLeadStatusUpdatedNotification($lead, $oldStatus));
                }
            } catch (\Exception $e) {
                Log::error('Failed to create notifications for lead status update', [
                    'error' => $e->getMessage(),
                    'lead_id' => $lead->id,
                ]);
            }
        }

        return response()->json($lead->load(['location', 'serviceProvider']));
    }
}
