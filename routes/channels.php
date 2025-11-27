<?php

use Illuminate\Support\Facades\Broadcast;

// Admin channel - all admins can listen
Broadcast::channel('admin', function ($user) {
    return $user->role === 'super_admin' || $user->role === 'admin' || $user->role === 'manager';
});

// Provider private channel - only the specific provider can listen
Broadcast::channel('provider.{providerId}', function ($user, $providerId) {
    return (int) $user->id === (int) $providerId;
});

