<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\LeadNote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LeadNoteController extends Controller
{
    public function index(Lead $lead)
    {
        $notes = $lead->notes()->with(['user', 'serviceProvider'])->get();
        return response()->json($notes);
    }

    public function store(Request $request, Lead $lead)
    {
        $validator = Validator::make($request->all(), [
            'note' => 'required|string',
            'type' => 'sometimes|in:note,status_change,assignment,other',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $note = LeadNote::create([
            'lead_id' => $lead->id,
            'user_id' => $request->user()->id,
            'note' => $request->note,
            'type' => $request->type ?? 'note',
            'metadata' => $request->metadata ?? null,
        ]);

        return response()->json($note->load(['user']), 201);
    }

    public function update(Request $request, LeadNote $note)
    {
        $validator = Validator::make($request->all(), [
            'note' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Only allow the creator or admin to update
        if ($note->user_id !== $request->user()->id && $request->user()->role !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $note->update(['note' => $request->note]);

        return response()->json($note->load(['user']));
    }

    public function destroy(LeadNote $note)
    {
        // Only allow the creator or admin to delete
        if (request()->user()->id !== $note->user_id && request()->user()->role !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $note->delete();

        return response()->json(['message' => 'Note deleted successfully']);
    }
}

