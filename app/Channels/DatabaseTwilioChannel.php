<?php

namespace App\Channels;

use App\Services\TwilioConfigService;
use Illuminate\Notifications\Notification;

class DatabaseTwilioChannel
{
    public function send($notifiable, Notification $notification)
    {
        if (!method_exists($notification, 'toTwilio')) {
            return;
        }

        $config = TwilioConfigService::getConfig();

        if (!$config['enabled'] || !$config['account_sid'] || !$config['auth_token']) {
            return;
        }

        $message = $notification->toTwilio($notifiable);
        $to = $notifiable->routeNotificationFor('twilio', $notification) ?: $notifiable->phone;

        if (!$to) {
            return;
        }

        // Check if Twilio SDK is available
        if (!class_exists(\Twilio\Rest\Client::class)) {
            \Log::error('Twilio SDK not installed. Run: composer require twilio/sdk');
            return;
        }

        $client = new \Twilio\Rest\Client($config['account_sid'], $config['auth_token']);

        $client->messages->create(
            $to,
            [
                'from' => $config['from'],
                'body' => $message->content,
            ]
        );
    }
}

