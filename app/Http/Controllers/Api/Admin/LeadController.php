<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lead;
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

        $lead->update($request->only(['status', 'service_provider_id']));

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

        $lead->update(['service_provider_id' => $request->service_provider_id]);

        return response()->json($lead->load(['location', 'serviceProvider']));
    }
}
