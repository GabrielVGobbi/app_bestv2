<?php

declare(strict_types=1);

$project = dirname(__DIR__);

return [
    'parameters' => [
        'configDirectories' => [
            $project.'/config',
            $project.'/app/Modules/ExternalApi/config',
            $project.'/app/Modules/Timeline/Config',
            $project.'/app/Modules/Uploads/Config',
        ],
        'databaseMigrationsPath' => [
            $project.'/database/migrations',
            $project.'/app/Modules/Backup/Database/Migrations',
            $project.'/app/Modules/Clients/Database/Migrations',
            $project.'/app/Modules/Notifications/Database/Migrations',
            $project.'/app/Modules/Permissions/Database/Migrations',
            $project.'/app/Modules/Timeline/Database/Migrations',
            $project.'/app/Modules/Uploads/Database/Migrations',
            $project.'/app/Modules/ExternalApi/Database/Migrations',
        ],
    ],
];
