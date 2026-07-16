<?php

declare(strict_types=1);

namespace App\Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Sales\Actions\Items\RemoveSaleItem;
use App\Modules\Sales\Actions\Items\UpsertSaleItem;
use App\Modules\Sales\Http\Requests\UpsertSaleItemRequest;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SaleItem;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class SaleItemController extends Controller
{
    public function store(UpsertSaleItemRequest $request, Sale $sale, UpsertSaleItem $upsertSaleItem): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $upsertSaleItem->handle($sale, $request->validated(), $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Item adicionado.')]);

        return to_route('sales.show', $sale);
    }

    public function update(UpsertSaleItemRequest $request, Sale $sale, SaleItem $saleItem, UpsertSaleItem $upsertSaleItem): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $upsertSaleItem->handle($sale, $request->validated(), $user, $saleItem);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Item atualizado.')]);

        return to_route('sales.show', $sale);
    }

    public function destroy(Sale $sale, SaleItem $saleItem, RemoveSaleItem $removeSaleItem): RedirectResponse
    {
        $user = auth()->user();

        abort_unless(
            $user instanceof User
            && $user->can('update', $sale)
            && $this->hasPermission($user, 'sales.items.delete'),
            403,
        );

        $removeSaleItem->handle($sale, $saleItem);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Item removido.')]);

        return to_route('sales.show', $sale);
    }

    private function hasPermission(User $user, string $slug): bool
    {
        return $user->hasAnyPermission($slug);
    }
}
