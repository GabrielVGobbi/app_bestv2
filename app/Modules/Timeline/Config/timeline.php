<?php

declare(strict_types=1);

use App\Modules\Clients\Models\Client;

return [
    'route_prefix' => env('TIMELINE_ROUTE_PREFIX', 'api/v1/timeline'),

    'route_middleware' => [
        'api',
        'auth:sanctum',
    ],

    'aliases' => [
        'client' => Client::class,
    ],

    'comments' => [
        'max_length' => 10000,
        'allow_replies' => true,
        'max_reply_depth' => 1,
    ],
];
