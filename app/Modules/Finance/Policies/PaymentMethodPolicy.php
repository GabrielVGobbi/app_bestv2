<?php

declare(strict_types=1);

namespace App\Modules\Finance\Policies;

use App\Models\User;
use App\Modules\Finance\Models\PaymentMethod;
use App\Modules\Permissions\Models\Permission;

class PaymentMethodPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'finance.payment-methods.view');
    }

    public function view(User $user, PaymentMethod $paymentMethod): bool
    {
        return $this->hasPermission($user, 'finance.payment-methods.view');
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'finance.payment-methods.create');
    }

    public function update(User $user, PaymentMethod $paymentMethod): bool
    {
        return $this->hasPermission($user, 'finance.payment-methods.update');
    }

    public function delete(User $user, PaymentMethod $paymentMethod): bool
    {
        return $this->hasPermission($user, 'finance.payment-methods.delete');
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
            ->whereHas('roles', fn ($roles) => $roles->whereIn('roles.id', $user->roles()->select('roles.id')))
            ->exists();
    }
}
