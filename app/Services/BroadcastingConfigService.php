<?php

namespace App\Services;

use App\Models\Setting;

class BroadcastingConfigService
{
    public static function getPusherConfig()
    {
        $cluster = Setting::get('pusher_app_cluster', config('broadcasting.connections.pusher.options.cluster', 'us2'));
        
        $config = [
            'driver' => 'pusher',
            'key' => Setting::get('pusher_app_key', config('broadcasting.connections.pusher.key')),
            'secret' => Setting::get('pusher_app_secret', config('broadcasting.connections.pusher.secret')),
            'app_id' => Setting::get('pusher_app_id', config('broadcasting.connections.pusher.app_id')),
            'options' => [
                'cluster' => $cluster,
                'useTLS' => true,
            ],
        ];
        
        // Log config (without sensitive data) for debugging
        \Log::debug('Pusher config generated', [
            'has_key' => !empty($config['key']),
            'has_secret' => !empty($config['secret']),
            'has_app_id' => !empty($config['app_id']),
            'app_id' => $config['app_id'] ?? 'missing',
            'cluster' => $cluster,
            'key_length' => strlen($config['key'] ?? ''),
        ]);
        
        return $config;
    }

    public static function isPusherEnabled()
    {
        return Setting::get('pusher_enabled', false);
    }
}

