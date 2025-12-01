<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;

// Admin channel - all admins can listen (public channel, no auth needed)
// Note: This is a public channel, so we don't need authorization
// But we'll keep it here for consistency

// Provider private channel - only the specific provider can listen
Broadcast::channel('provider.{providerId}', function ($user, $providerId) {
    Log::info('Channel authorization check', [
        'channel' => 'provider.' . $providerId,
        'user_id' => $user->id ?? null,
        'user_type' => get_class($user),
        'provider_id' => $providerId,
    ]);
    
    // Check if user is a service provider and matches the providerId
    if ($user instanceof \App\Models\ServiceProvider) {
        $authorized = (int) $user->id === (int) $providerId;
        Log::info('Provider channel authorization result', [
            'authorized' => $authorized,
            'user_id' => $user->id,
            'provider_id' => $providerId,
        ]);
        return $authorized;
    }
    
    // If it's a User model, deny access (only providers can access their own channels)
    Log::warning('Channel authorization denied - user is not a ServiceProvider', [
        'user_type' => get_class($user),
        'provider_id' => $providerId,
    ]);
    return false;
});

