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
];
