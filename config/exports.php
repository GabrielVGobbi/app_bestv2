<?php

use App\Models\User;
use App\Modules\Clients\Models\Client;
use App\Modules\Companies\Models\Company;
use App\Modules\Sales\Models\Sale;

return [
    /*
     * Models that are allowed to be exported.
     * Add any Exportable model class string here.
     */
    'allowed_models' => [
        User::class,
        Company::class,
        Client::class,
        Sale::class,
    ],

    /*
     * Storage disk for export files.
     * Must be a private (non-public) disk.
     */
    'disk' => 'local',

    /*
     * Directory within the disk where exports are stored.
     */
    'directory' => 'exports',

    /*
     * Hours until an export file expires after completion.
     */
    'expires_after_hours' => 1,

    /*
     * Chunk size for processing records in the job.
     */
    'chunk_size' => 500,
];
