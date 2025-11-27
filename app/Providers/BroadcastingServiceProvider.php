<?php

namespace App\Providers;

use App\Services\BroadcastingConfigService;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Config;

class BroadcastingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Update Pusher config from database if enabled
        if (BroadcastingConfigService::isPusherEnabled()) {
            $pusherConfig = BroadcastingConfigService::getPusherConfig();
            
            Config::set('broadcasting.connections.pusher', $pusherConfig);
            Config::set('broadcasting.default', 'pusher');
        }
    }
}

