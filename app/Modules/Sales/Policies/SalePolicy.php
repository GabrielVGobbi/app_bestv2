<?php

declare(strict_types=1);

namespace App\Modules\Sales\Policies;

use App\Models\User;
use App\Modules\Sales\Models\Sale;

class SalePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'sales.view') || $this->hasPermission($user, 'sales.view-own');
    }

    public function view(User $user, Sale $sale): bool
    {
        return $this->hasPermission($user, 'sales.view')
            || ($this->hasPermission($user, 'sales.view-own') && $sale->seller_id === $user->id);
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'sales.create');
    }

    public function update(User $user, Sale $sale): bool
    {
        return $this->hasPermission($user, 'sales.update');
    }

    public function quote(User $user, Sale $sale): bool
    {
        return $this->hasPermission($user, 'sales.quote');
    }

    public function revertToDraft(User $user, Sale $sale): bool
    {
        return $this->hasPermission($user, 'sales.update');
    }

    public function confirm(User $user, Sale $sale): bool
    {
        return $this->hasPermission($user, 'sales.confirm');
    }

    public function finish(User $user, Sale $sale): bool
    {
        return $this->hasPermission($user, 'sales.finish');
    }

    public function cancel(User $user, Sale $sale): bool
    {
        return $this->hasPermission($user, 'sales.cancel');
    }

    public function delete(User $user, Sale $sale): bool
    {
        return $this->hasPermission($user, 'sales.delete');
    }

    public function viewAmounts(User $user, Sale $sale): bool
    {
        return $this->hasPermission($user, 'sales.amounts.view');
    }

    private function hasPermission(User $user, string $slug): bool
    {
        return $user->hasAnyPermission($slug);
    }
}
