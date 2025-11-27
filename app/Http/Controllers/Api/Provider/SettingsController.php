<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function getPusherSettings(Request $request)
    {
        // Return Pusher settings in same format as admin endpoint (key-value pairs)
        return response()->json(Setting::getByGroup('pusher'));
    }
}

