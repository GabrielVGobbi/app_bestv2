<?php

declare(strict_types=1);

use App\Modules\Clients\Models\Client;
use App\Modules\Finance\Models\BankStatementImport;
use App\Modules\Finance\Models\FinancialEntry;
use App\Modules\Finance\Models\FinancialInstallment;
use App\Modules\Finance\Models\FinancialSettlement;
use App\Modules\Playlists\Models\Playlist;
use App\Modules\Timeline\Models\TimelineComment;

return [
    'disk' => env('UPLOADS_DISK', 'local'),
    'max_size_mb' => (int) env('UPLOADS_MAX_SIZE_MB', 50),
    'route_prefix' => env('UPLOADS_ROUTE_PREFIX', 'api/v1/uploads'),
    'web_route_prefix' => env('UPLOADS_WEB_ROUTE_PREFIX', 'uploads'),
    'public_disk' => env('UPLOADS_PUBLIC_DISK', 'public'),

    'public_rules' => [],

    'route_middleware' => [
        'api',
        'auth:sanctum',
    ],
    'web_route_middleware' => [
        'web',
        'auth',
    ],

    'aliases' => [
        'client' => Client::class,
        'bank-statement-import' => BankStatementImport::class,
        'financial-entry' => FinancialEntry::class,
        'financial-installment' => FinancialInstallment::class,
        'financial-settlement' => FinancialSettlement::class,
        'playlist' => Playlist::class,
        'timeline-comment' => TimelineComment::class,
    ],
];
