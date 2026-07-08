<?php

declare(strict_types=1);

namespace App\Modules\Finance\Listeners;

use App\Modules\Finance\Actions\Sales\CreateReceivableFromSale;
use App\Modules\Sales\Events\SaleConfirmed;

class CreateReceivableForConfirmedSale
{
    public function __construct(private readonly CreateReceivableFromSale $createReceivable) {}

    public function handle(SaleConfirmed $event): void
    {
        $this->createReceivable->handle($event->sale);
    }
}
