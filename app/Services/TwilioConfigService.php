<?php

namespace App\Services;

use App\Models\Setting;

class TwilioConfigService
{
    public static function getConfig()
    {
        return [
            'enabled' => Setting::get('twilio_enabled', false),
            'account_sid' => Setting::get('twilio_account_sid', ''),
            'auth_token' => Setting::get('twilio_auth_token', ''),
            'from' => Setting::get('twilio_from', ''),
        ];
    }

    public static function isEnabled()
    {
        return Setting::get('twilio_enabled', false);
    }
}

