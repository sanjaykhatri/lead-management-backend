<?php

namespace App\Providers;

use App\Services\BroadcastingConfigService;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Broadcast;

class BroadcastingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Register broadcast routes with Sanctum authentication
        Broadcast::routes(['prefix' => 'api', 'middleware' => ['auth:sanctum']]);
        
        require base_path('routes/channels.php');

        // Update Pusher config from database if enabled
        try {
            if (BroadcastingConfigService::isPusherEnabled()) {
                $pusherConfig = BroadcastingConfigService::getPusherConfig();
                
                // Only update if we have valid credentials
                if (!empty($pusherConfig['key']) && !empty($pusherConfig['secret']) && !empty($pusherConfig['app_id'])) {
                    Config::set('broadcasting.connections.pusher', $pusherConfig);
                    Config::set('broadcasting.default', 'pusher');
                    
                    \Log::info('Pusher config updated from database', [
                        'enabled' => true,
                        'has_key' => !empty($pusherConfig['key']),
                        'has_secret' => !empty($pusherConfig['secret']),
                        'has_app_id' => !empty($pusherConfig['app_id']),
                        'cluster' => $pusherConfig['options']['cluster'] ?? 'unknown',
                    ]);
                } else {
                    \Log::warning('Pusher enabled but credentials missing', [
                        'has_key' => !empty($pusherConfig['key']),
                        'has_secret' => !empty($pusherConfig['secret']),
                        'has_app_id' => !empty($pusherConfig['app_id']),
                    ]);
                }
            } else {
                \Log::info('Pusher is disabled in database settings');
            }
        } catch (\Exception $e) {
            \Log::error('Failed to load Pusher config from database', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}

