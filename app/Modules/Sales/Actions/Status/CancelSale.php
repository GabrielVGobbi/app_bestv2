<?php

declare(strict_types=1);

namespace App\Modules\Sales\Actions\Status;

use App\Models\User;
use App\Modules\Playlists\Actions\CancelPlaylistSlot;
use App\Modules\Playlists\Models\PlaylistSlot;
use App\Modules\Sales\Enums\SaleStatus;
use App\Modules\Sales\Events\SaleCanceled;
use App\Modules\Sales\Models\Sale;
use App\Supports\StatusTransitions\StatusTransitionResult;
use App\Supports\StatusTransitions\StatusTransitionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelSale
{
    public function __construct(
        private readonly StatusTransitionService $statusTransitionService,
        private readonly CancelPlaylistSlot $cancelPlaylistSlot,
    ) {}

    public function handle(Sale $sale, ?string $reason = null, bool $confirmed = false): StatusTransitionResult
    {
        return DB::transaction(function () use ($sale, $reason, $confirmed): StatusTransitionResult {
            $lockedSale = Sale::query()
                ->with('saleItems.sellable')
                ->whereKey($sale->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $user = auth()->user();

            if (! $user instanceof User) {
                throw ValidationException::withMessages([
                    'user' => __('Usuário autenticado é obrigatório para cancelar venda.'),
                ]);
            }

            foreach ($lockedSale->saleItems as $saleItem) {
                if ($saleItem->sellable instanceof PlaylistSlot) {
                    $this->cancelPlaylistSlot->handle($saleItem->sellable, $user, $reason);
                }
            }

            $result = $this->statusTransitionService->transition(
                model: $lockedSale,
                newStatus: SaleStatus::Canceled,
                reason: $reason,
                metadata: ['action' => self::class, 'domain' => 'sales'],
                confirmed: $confirmed,
            );

            SaleCanceled::dispatch($lockedSale->refresh());

            return $result;
        });
    }
}
