<?php

use App\Modules\Addresses\Providers\AddressesServiceProvider;
use App\Modules\Backup\Providers\BackupServiceProvider;
use App\Modules\Clients\Providers\ClientsServiceProvider;
use App\Modules\Companies\Providers\CompaniesServiceProvider;
use App\Modules\CrudGenerator\Providers\CrudGeneratorServiceProvider;
use App\Modules\Email\Providers\EmailServiceProvider;
// use App\Modules\ExternalApi\Providers\ExternalApiServiceProvider;
use App\Modules\Notifications\Providers\NotificationsServiceProvider;
use App\Modules\Permissions\Providers\PermissionsServiceProvider;
use App\Modules\SeederGenerator\Providers\SeederGeneratorServiceProvider;
use App\Modules\Timeline\Providers\TimelineServiceProvider;
use App\Modules\Uploads\Providers\UploadsServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\TelescopeServiceProvider;

return [
    BackupServiceProvider::class,
    ClientsServiceProvider::class,
    CrudGeneratorServiceProvider::class,
    EmailServiceProvider::class,
    // ExternalApiServiceProvider::class,
    NotificationsServiceProvider::class,
    CompaniesServiceProvider::class,
    PermissionsServiceProvider::class,
    SeederGeneratorServiceProvider::class,
    TimelineServiceProvider::class,
    UploadsServiceProvider::class,
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    TelescopeServiceProvider::class,
    AddressesServiceProvider::class,
];
