<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Slack Webhook URL
    |--------------------------------------------------------------------------
    |
    | The Incoming Webhook URL generated in your Slack app settings.
    | This is the fallback value. The admin panel stores the active value
    | in app_settings (group: slack, key: webhook_url).
    |
    */

    'webhook_url' => env('SLACK_WEBHOOK_URL'),

    /*
    |--------------------------------------------------------------------------
    | Slack Enabled
    |--------------------------------------------------------------------------
    |
    | Master kill switch. When false, SlackService will silently skip all
    | sends. Overridable at runtime via AppSettings.
    |
    */

    'enabled' => (bool) env('SLACK_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Exception Reporting Level
    |--------------------------------------------------------------------------
    |
    | Minimum alert level for exceptions sent via the exception handler.
    | Valid values: info, warning, error, critical
    |
    */

    'log_level' => env('SLACK_LOG_LEVEL', 'warning'),

    /*
    |--------------------------------------------------------------------------
    | App Metadata (sent as footer in messages)
    |--------------------------------------------------------------------------
    */

    'app_name' => env('APP_NAME', 'Laravel'),
    'app_url' => env('APP_URL'),
    'environment' => env('APP_ENV', 'production'),

];
