<?php

return [
    /*
     * Storage disk for database export files.
     * Must be a private (non-public) disk.
     */
    'disk' => 'local',

    /*
     * Directory within the disk where exports are stored.
     */
    'directory' => 'database-exports',

    /*
     * Hours until a database export file expires after completion.
     */
    'expires_after_hours' => 24,

    /*
     * Keep failed requests for diagnosis after safely removing their artifacts.
     * Cleanup does not renew the failed request's updated_at retention clock.
     */
    'failed_retention_days' => 7,

    /*
     * Only recognized, unreferenced private local artifacts are swept.
     * Unknown names and artifacts belonging to any registered request survive.
     */
    'orphan_retention_hours' => 24,
];
