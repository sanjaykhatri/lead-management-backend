<?php

use App\Services\BroadcastingConfigService;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Broadcaster
    |--------------------------------------------------------------------------
    |
    | This option controls the default broadcaster that will be used by the
    | framework when an event needs to be broadcast. You may set this to
    | any of the connections defined in the "connections" array below.
    |
    */

    'default' => env('BROADCAST_DRIVER', 'pusher'),

    /*
    |--------------------------------------------------------------------------
    | Broadcast Connections
    |--------------------------------------------------------------------------
    |
    | Here you may define all of the broadcast connections that will be used
    | to broadcast events to other systems or over websockets. Samples of
    | each available type of connection are provided inside this array.
    |
    */

    'connections' => [

        'pusher' => [
            'driver' => 'pusher',
            'key' => env('PUSHER_APP_KEY', '04a5e49b8b3cca55deaf'),
            'secret' => env('PUSHER_APP_SECRET', 'b4b4b34ea9a8929dbb09'),
            'app_id' => env('PUSHER_APP_ID', '2083652'),
            'options' => [
                'cluster' => env('PUSHER_APP_CLUSTER', 'ap2'),
                'useTLS' => false,
            ],
        ],

        'ably' => [
            'driver' => 'ably',
        ],

        'log' => [
            'driver' => 'log',
        ],

        'null' => [
            'driver' => 'null',
        ],

    ],

];

