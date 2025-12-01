<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;

class BroadcastingAuthController extends Controller
{
    public function authenticate(Request $request)
    {
        // Authenticate the user (can be admin or provider)
        $user = $request->user('sanctum');
        
        if (!$user) {
            Log::warning('Broadcasting auth: Unauthenticated request', [
                'channel' => $request->input('channel_name'),
                'socket_id' => $request->input('socket_id'),
            ]);
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $channelName = $request->input('channel_name');
        $socketId = $request->input('socket_id');
        
        Log::info('Broadcasting auth request', [
            'user_id' => $user->id,
            'user_type' => get_class($user),
            'channel_name' => $channelName,
            'socket_id' => $socketId,
        ]);

        // Set the authenticated user for Broadcast facade
        // This is required for channel authorization to work
        Broadcast::setUser($user);
        
        try {
            // Let Laravel handle the channel authorization using routes/channels.php
            $response = Broadcast::auth($request);
            
            Log::info('Broadcasting auth successful', [
                'user_id' => $user->id,
                'channel_name' => $channelName,
            ]);
            
            return $response;
        } catch (\Exception $e) {
            Log::error('Broadcasting auth failed', [
                'user_id' => $user->id,
                'channel_name' => $channelName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'message' => 'Authorization failed',
                'error' => $e->getMessage(),
            ], 403);
        }
    }
}

