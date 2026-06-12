<?php

declare(strict_types=1);
use App\Models\User;
use App\Modules\Clients\Models\Client;

return [
    /*
    |--------------------------------------------------------------------------
    | Address API Routes
    |--------------------------------------------------------------------------
    */
    'route_prefix' => env('ADDRESSES_ROUTE_PREFIX', 'api/v1/addresses'),
    'route_name' => env('ADDRESSES_ROUTE_NAME', 'api.addresses.'),
    'route_middleware' => ['api', 'auth:sanctum'],

    /*
    |--------------------------------------------------------------------------
    | Addressable Models
    |--------------------------------------------------------------------------
    |
    | The API accepts the alias in "model" and the model key in "model_id".
    | Add or remove aliases after copying the module to a project.
    |
    */
    'addressables' => [
        'user' => User::class,
        'client' => Client::class,
        'customer' => Client::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Lookup Columns
    |--------------------------------------------------------------------------
    |
    | The resolver checks only columns that exist on the target model table.
    |
    */
    'lookup_columns' => ['uuid', 'id'],

    'default_country' => env('ADDRESSES_DEFAULT_COUNTRY', 'Brasil'),
];
