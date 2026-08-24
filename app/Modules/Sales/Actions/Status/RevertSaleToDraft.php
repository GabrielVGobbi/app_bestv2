<?php

declare(strict_types=1);

namespace App\Modules\Sales\Actions\Status;

use App\Modules\Sales\Enums\SaleStatus;
use App\Modules\Sales\Models\Sale;
use App\Supports\StatusTransitions\StatusTransitionResult;
use App\Supports\StatusTransitions\StatusTransitionService;
use Illuminate\Support\Facades\DB;

class RevertSaleToDraft
{
    public function __construct(
        private readonly StatusTransitionService $statusTransitionService,
    ) {}

    public function handle(Sale $sale): StatusTransitionResult
    {
        return DB::transaction(function () use ($sale): StatusTransitionResult {
            $lockedSale = Sale::query()
                ->whereKey($sale->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            return $this->statusTransitionService->transition(
                model: $lockedSale,
                newStatus: SaleStatus::Draft,
                metadata: ['action' => self::class, 'domain' => 'sales'],
            );
        });
    }
}
