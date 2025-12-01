<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\User;
use App\Events\LeadNoteCreated;
use App\Notifications\LeadNoteCreatedNotification;
use App\Notifications\AdminLeadNoteCreatedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class LeadNoteController extends Controller
{
    public function index(Request $request, Lead $lead)
    {
        $provider = $request->user();
        
        // Ensure the lead belongs to this provider
        if ($lead->service_provider_id !== $provider->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $notes = $lead->notes()->with(['user', 'serviceProvider'])->orderBy('created_at', 'desc')->get();
        return response()->json($notes);
    }

    public function store(Request $request, Lead $lead)
    {
        $provider = $request->user();
        
        // Ensure the lead belongs to this provider
        if ($lead->service_provider_id !== $provider->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'note' => 'required|string',
            'type' => 'sometimes|in:note,status_change,assignment,other',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $note = LeadNote::create([
            'lead_id' => $lead->id,
            'service_provider_id' => $provider->id,
            'note' => $request->note,
            'type' => $request->type ?? 'note',
            'metadata' => $request->metadata ?? null,
        ]);

        // Log the activity
        Log::info('Lead note created by provider', [
            'note_id' => $note->id,
            'lead_id' => $lead->id,
            'provider_id' => $provider->id,
            'provider_name' => $provider->name,
            'note_type' => $note->type,
        ]);

        // Load relationships for event
        $note->load(['lead', 'serviceProvider']);
        $lead->refresh()->load(['location', 'serviceProvider']);

        // Broadcast event for real-time updates
        try {
            event(new LeadNoteCreated($note));
            Log::info('LeadNoteCreated event fired', [
                'note_id' => $note->id,
                'lead_id' => $lead->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to broadcast LeadNoteCreated event', [
                'error' => $e->getMessage(),
                'note_id' => $note->id,
            ]);
        }

        // Send database notifications
        try {
            // Notify all admin users
            $adminUsers = User::whereIn('role', ['super_admin', 'admin', 'manager'])->get();
            foreach ($adminUsers as $admin) {
                $admin->notify(new AdminLeadNoteCreatedNotification($note));
            }

            // Notify the assigned provider (if different from the one who created the note)
            // Actually, since provider created it, we don't need to notify them again
            // But we could notify if admin adds a note to provider's lead
        } catch (\Exception $e) {
            Log::error('Failed to create notifications for lead note', [
                'error' => $e->getMessage(),
                'note_id' => $note->id,
            ]);
        }

        return response()->json($note->load(['serviceProvider']), 201);
    }

    public function update(Request $request, LeadNote $note)
    {
        $provider = $request->user();
        
        // Ensure the note belongs to a lead assigned to this provider
        if ($note->lead->service_provider_id !== $provider->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Only allow the creator to update
        if ($note->service_provider_id !== $provider->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'note' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $oldNote = $note->note;
        $note->update(['note' => $request->note]);

        // Log the activity
        Log::info('Lead note updated by provider', [
            'note_id' => $note->id,
            'lead_id' => $note->lead_id,
            'provider_id' => $provider->id,
            'provider_name' => $provider->name,
        ]);

        return response()->json($note->load(['serviceProvider']));
    }

    public function destroy(LeadNote $note)
    {
        $provider = request()->user();
        
        // Ensure the note belongs to a lead assigned to this provider
        if ($note->lead->service_provider_id !== $provider->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Only allow the creator to delete
        if ($note->service_provider_id !== $provider->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Log the activity
        Log::info('Lead note deleted by provider', [
            'note_id' => $note->id,
            'lead_id' => $note->lead_id,
            'provider_id' => $provider->id,
            'provider_name' => $provider->name,
        ]);

        $note->delete();

        return response()->json(['message' => 'Note deleted successfully']);
    }
}

