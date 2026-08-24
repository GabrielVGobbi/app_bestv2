<?php

declare(strict_types=1);

namespace App\Modules\Finance\Listeners;

use App\Modules\Finance\Actions\Sales\CancelReceivableFromSale;
use App\Modules\Sales\Events\SaleCanceled;

class CancelReceivableForCanceledSale
{
    public function __construct(private readonly CancelReceivableFromSale $cancelReceivable) {}

    public function handle(SaleCanceled $event): void
    {
        $this->cancelReceivable->handle($event->sale);
    }
}
