<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\User;
use App\Models\Location;
use App\Models\ServiceProvider;
use App\Services\LeadAssignmentService;
use App\Events\LeadAssigned;
use App\Notifications\LeadAssignedNotification;
use App\Notifications\AdminLeadAssignedNotification;
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

        // Refresh lead to ensure relationships are loaded
        $lead->refresh();
        $lead->load(['location', 'serviceProvider']);

        // Always broadcast to admin (for all new leads)
        try {
            // Ensure Pusher config is loaded before broadcasting
            if (\App\Services\BroadcastingConfigService::isPusherEnabled()) {
                $pusherConfig = \App\Services\BroadcastingConfigService::getPusherConfig();
                \Illuminate\Support\Facades\Config::set('broadcasting.connections.pusher', $pusherConfig);
                \Illuminate\Support\Facades\Config::set('broadcasting.default', 'pusher');
                
                \Log::info('Pusher config loaded before broadcast', [
                    'has_key' => !empty($pusherConfig['key']),
                    'has_secret' => !empty($pusherConfig['secret']),
                    'has_app_id' => !empty($pusherConfig['app_id']),
                    'cluster' => $pusherConfig['options']['cluster'] ?? 'unknown',
                ]);
            }
            
            $pusherEnabled = \App\Services\BroadcastingConfigService::isPusherEnabled();
            $broadcastDriver = config('broadcasting.default');
            $pusherConfig = config('broadcasting.connections.pusher');
            
            \Log::info('Firing LeadAssigned event (will be queued)', [
                'lead_id' => $lead->id,
                'lead_name' => $lead->name,
                'provider_id' => $lead->service_provider_id,
                'provider_name' => $lead->serviceProvider->name ?? null,
                'pusher_enabled' => $pusherEnabled,
                'broadcast_driver' => $broadcastDriver,
                'pusher_config_loaded' => !empty($pusherConfig),
                'pusher_app_id' => $pusherConfig['app_id'] ?? 'missing',
                'pusher_key' => !empty($pusherConfig['key']) ? '***' . substr($pusherConfig['key'], -4) : 'missing',
                'queue_connection' => config('queue.default'),
                'channels' => [
                    'admin',
                    $lead->service_provider_id ? 'private-provider.' . $lead->service_provider_id : null,
                ],
            ]);
            
            $event = new LeadAssigned($lead);
            event($event);
            
            \Log::info('LeadAssigned event queued successfully', [
                'lead_id' => $lead->id,
                'should_broadcast' => $event->shouldBroadcast(),
                'note' => 'Event will be processed by queue worker. Make sure queue:work is running.',
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to broadcast LeadAssigned event', [
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'lead_id' => $lead->id,
            ]);
        }
        
        // Send notifications if provider is assigned
        if ($assignedProvider) {
            // Send SMS and database notification
            $assignedProvider->notify(new LeadAssignedNotification($lead));
        }

        // Send database notifications to all admin users
        try {
            $adminUsers = User::whereIn('role', ['super_admin', 'admin', 'manager'])->get();
            foreach ($adminUsers as $admin) {
                $admin->notify(new AdminLeadAssignedNotification($lead));
            }
        } catch (\Exception $e) {
            \Log::error('Failed to create admin notifications for lead assignment', [
                'error' => $e->getMessage(),
                'lead_id' => $lead->id,
            ]);
        }

        return response()->json([
            'message' => 'Lead submitted successfully',
            'lead' => $lead,
        ], 201);
    }
}
