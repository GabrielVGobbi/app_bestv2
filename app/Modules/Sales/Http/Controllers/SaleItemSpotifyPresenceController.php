<?php

declare(strict_types=1);

namespace App\Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Sales\Actions\Items\CheckSaleItemSpotifyPresence;
use App\Modules\Sales\Enums\SaleItemType;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SaleItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class SaleItemSpotifyPresenceController extends Controller
{
    public function syncItem(Sale $sale, SaleItem $saleItem, CheckSaleItemSpotifyPresence $checkSaleItemSpotifyPresence): RedirectResponse
    {
        $this->authorizeRequest($sale);

        $checkSaleItemSpotifyPresence->handle($sale, $saleItem);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Verificação Spotify atualizada.')]);

        return to_route('sales.show', $sale);
    }

    public function syncSale(Sale $sale, CheckSaleItemSpotifyPresence $checkSaleItemSpotifyPresence): RedirectResponse
    {
        $this->authorizeRequest($sale);

        $sale->saleItems()
            ->where('type', SaleItemType::PlaylistSlot->value)
            ->get()
            ->each(fn (SaleItem $saleItem): SaleItem => $checkSaleItemSpotifyPresence->handle($sale, $saleItem));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Verificações Spotify atualizadas.')]);

        return to_route('sales.show', $sale);
    }

    private function authorizeRequest(Sale $sale): void
    {
        $user = auth()->user();

        abort_unless(
            $user instanceof User
            && $user->can('view', $sale)
            && $this->hasPermission($user, 'sales.items.spotify-playlist.verify'),
            403,
        );
    }

    private function hasPermission(User $user, string $slug): bool
    {
        $superRole = config('permissions.super_role', 'developer');

        if ($user->roles()->where('slug', $superRole)->exists()) {
            return true;
        }

        if ($user->permissions()->where('slug', $slug)->exists()) {
            return true;
        }

        return Permission::query()
            ->where('slug', $slug)
            ->whereHas('roles', fn (Builder $roles): Builder => $roles->whereIn('roles.id', $user->roles()->select('roles.id')))
            ->exists();
    }
}
