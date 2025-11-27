<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\TwilioConfigService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SettingsController extends Controller
{
    public function index(Request $request)
    {
        $group = $request->get('group', 'all');

        if ($group === 'all') {
            $settings = Setting::orderBy('group')->orderBy('key')->get();
        } else {
            $settings = Setting::where('group', $group)->orderBy('key')->get();
        }

        return response()->json($settings);
    }

    public function getByGroup($group)
    {
        $settings = Setting::getByGroup($group);
        return response()->json($settings);
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'settings' => 'required|array',
            'settings.*.key' => 'required|string',
            'settings.*.value' => 'nullable',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        foreach ($request->settings as $settingData) {
            $setting = Setting::where('key', $settingData['key'])->first();

            if ($setting) {
                $value = $settingData['value'] ?? '';

                // Convert value based on type
                if ($setting->type === 'boolean') {
                    $value = filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
                } elseif ($setting->type === 'json' && is_array($value)) {
                    $value = json_encode($value);
                } else {
                    $value = (string) $value;
                }

                $setting->update(['value' => $value]);
            }
        }

        return response()->json(['message' => 'Settings updated successfully']);
    }

    public function updateGroup(Request $request, $group)
    {
        $validator = Validator::make($request->all(), [
            'settings' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        foreach ($request->settings as $key => $value) {
            $setting = Setting::where('key', $key)->where('group', $group)->first();

            if ($setting) {
                // Convert value based on type
                if ($setting->type === 'boolean') {
                    $value = filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
                } elseif ($setting->type === 'json' && is_array($value)) {
                    $value = json_encode($value);
                } else {
                    $value = (string) $value;
                }

                $setting->update(['value' => $value]);
            }
        }

        return response()->json(['message' => 'Settings updated successfully']);
    }

    public function testPusher(Request $request)
    {
        try {
            $pusherEnabled = Setting::get('pusher_enabled', false);
            $appId = Setting::get('pusher_app_id');
            $appKey = Setting::get('pusher_app_key');
            $appSecret = Setting::get('pusher_app_secret');
            $cluster = Setting::get('pusher_app_cluster', 'us2');

            if (!$pusherEnabled) {
                return response()->json(['error' => 'Pusher is not enabled'], 400);
            }

            if (!$appId || !$appKey || !$appSecret) {
                return response()->json(['error' => 'Pusher credentials are missing'], 400);
            }

            // Check if Pusher package is installed
            if (!class_exists(\Pusher\Pusher::class)) {
                return response()->json(['error' => 'Pusher package not installed. Run: composer require pusher/pusher-php-server'], 500);
            }

            // Test Pusher connection
            $pusher = new \Pusher\Pusher(
                $appKey,
                $appSecret,
                $appId,
                ['cluster' => $cluster]
            );

            $pusher->trigger('test-channel', 'test-event', ['message' => 'Test successful']);

            return response()->json(['message' => 'Pusher connection successful']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Pusher connection failed: ' . $e->getMessage()], 500);
        }
    }

    public function testTwilio(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'test_phone' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $config = TwilioConfigService::getConfig();

            if (!$config['enabled']) {
                return response()->json(['error' => 'Twilio is not enabled'], 400);
            }

            if (!$config['account_sid'] || !$config['auth_token'] || !$config['from']) {
                return response()->json(['error' => 'Twilio credentials are missing'], 400);
            }

            // Check if Twilio package is installed
            if (!class_exists(\Twilio\Rest\Client::class)) {
                return response()->json(['error' => 'Twilio package not installed. Run: composer require twilio/sdk'], 500);
            }

            // Test Twilio connection
            $client = new \Twilio\Rest\Client($config['account_sid'], $config['auth_token']);

            $message = $client->messages->create(
                $request->test_phone,
                [
                    'from' => $config['from'],
                    'body' => 'Test SMS from Lead Management System'
                ]
            );

            return response()->json(['message' => 'Twilio SMS sent successfully', 'sid' => $message->sid]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Twilio SMS failed: ' . $e->getMessage()], 500);
        }
    }
}

