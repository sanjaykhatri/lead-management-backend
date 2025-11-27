<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;

class BroadcastingAuthController extends Controller
{
    public function authenticate(Request $request)
    {
        // Authenticate the user (can be admin or provider)
        $user = $request->user('sanctum');
        
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Set the authenticated user for Broadcast facade
        Broadcast::setUser($user);
        
        // Let Laravel handle the channel authorization using routes/channels.php
        return Broadcast::auth($request);
    }
}

