<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Email Default Enabled State
    |--------------------------------------------------------------------------
    |
    | This value is the DEFAULT used when no DB setting has been persisted yet.
    | The runtime kill switch is managed via AppSettings (stored in DB + cache).
    | Use EMAIL_TO_OVERRIDE in staging to redirect all emails to a single address.
    |
    */

    'defaults' => [
        'enabled' => env('EMAIL_ENABLED', true),
        'auth_enabled' => env('EMAIL_AUTH_ENABLED', true),
        'export_enabled' => env('EMAIL_EXPORT_ENABLED', true),
        'notification_enabled' => env('EMAIL_NOTIFICATION_ENABLED', true),
        'system_enabled' => env('EMAIL_SYSTEM_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Override Recipient
    |--------------------------------------------------------------------------
    |
    | When set, ALL outgoing emails are redirected to this address.
    | Useful in staging/testing environments to prevent accidental delivery.
    |
    */

    'to_override' => env('EMAIL_TO_OVERRIDE'),

];
