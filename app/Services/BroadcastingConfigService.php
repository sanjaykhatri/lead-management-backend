<?php

namespace App\Services;

use App\Models\Setting;

class BroadcastingConfigService
{
    public static function getPusherConfig()
    {
        return [
            'driver' => 'pusher',
            'key' => Setting::get('pusher_app_key', config('broadcasting.connections.pusher.key')),
            'secret' => Setting::get('pusher_app_secret', config('broadcasting.connections.pusher.secret')),
            'app_id' => Setting::get('pusher_app_id', config('broadcasting.connections.pusher.app_id')),
            'options' => [
                'cluster' => Setting::get('pusher_app_cluster', config('broadcasting.connections.pusher.options.cluster', 'us2')),
                'useTLS' => true,
            ],
        ];
    }

    public static function isPusherEnabled()
    {
        return Setting::get('pusher_enabled', false);
    }
}

