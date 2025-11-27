<?php

use Illuminate\Support\Facades\Broadcast;

// Admin channel - all admins can listen (public channel, no auth needed)
// Note: This is a public channel, so we don't need authorization
// But we'll keep it here for consistency

// Provider private channel - only the specific provider can listen
Broadcast::channel('provider.{providerId}', function ($user, $providerId) {
    // Check if user is a service provider and matches the providerId
    // $user can be either User (admin) or ServiceProvider
    if ($user instanceof \App\Models\ServiceProvider) {
        return (int) $user->id === (int) $providerId;
    }
    // If it's a User model, check if they're trying to access as admin (for testing)
    // Otherwise deny access
    return false;
});

