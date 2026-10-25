<?php

namespace App\Modules\ExternalApi\Examples;

use App\Modules\ExternalApi\Facades\Iugu;

class IuguUsageExample
{
    public function listCustomers(): array
    {
        return Iugu::customers()->list([
            'limit' => 10,
            'skip' => 0,
        ]);
    }

    public function listPayments(): array
    {
        return Iugu::payments()->list([
            'limit' => 10,
            'skip' => 0,
        ]);
    }
}
