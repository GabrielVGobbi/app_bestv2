<?php

declare(strict_types=1);

namespace App\Modules\Clients\Observers;

use App\Modules\Clients\Models\Client;
use App\Supports\ActivityActions\ActivityActionLogger;

class ClientObserver
{
    public function created(Client $client): void
    {
        //
    }

    public function updated(Client $client): void {}

    public function deleted(Client $client): void
    {
        //
    }
}
