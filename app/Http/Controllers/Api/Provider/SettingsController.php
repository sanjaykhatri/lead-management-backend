<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function getPusherSettings(Request $request)
    {
        // Return Pusher settings for frontend (without sensitive data)
        return response()->json([
            'pusher_enabled' => Setting::get('pusher_enabled', false),
            'pusher_app_key' => Setting::get('pusher_app_key', ''),
            'pusher_app_cluster' => Setting::get('pusher_app_cluster', 'us2'),
        ]);
    }
}

