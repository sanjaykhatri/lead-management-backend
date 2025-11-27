<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class LocationController extends Controller
{
    public function index()
    {
        $locations = Location::with('serviceProviders')->get();
        return response()->json($locations);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|unique:locations,slug',
            'address' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->all();
        if (!isset($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $location = Location::create($data);

        return response()->json($location, 201);
    }

    public function show(Location $location)
    {
        $location->load('serviceProviders');
        return response()->json($location);
    }

    public function update(Request $request, Location $location)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'slug' => 'sometimes|string|unique:locations,slug,' . $location->id,
            'address' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $location->update($request->all());

        return response()->json($location->load('serviceProviders'));
    }

    public function destroy(Location $location)
    {
        $location->delete();
        return response()->json(['message' => 'Location deleted']);
    }

    public function assignProviders(Request $request, Location $location)
    {
        $validator = Validator::make($request->all(), [
            'service_provider_ids' => 'required|array',
            'service_provider_ids.*' => 'exists:service_providers,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $location->serviceProviders()->sync($request->service_provider_ids);

        return response()->json($location->load('serviceProviders'));
    }

    public function updateAssignmentAlgorithm(Request $request, Location $location)
    {
        $validator = Validator::make($request->all(), [
            'assignment_algorithm' => 'required|in:round_robin,geographic,load_balance,manual',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $location->update(['assignment_algorithm' => $request->assignment_algorithm]);

        return response()->json($location);
    }
}
