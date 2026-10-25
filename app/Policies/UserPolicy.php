<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->managesUsers($user);
    }

    public function view(User $user, User $target): bool
    {
        return $user->is($target) || $this->managesUsers($user);
    }

    public function create(User $user): bool
    {
        return $this->managesUsers($user);
    }

    public function updateAny(User $user): bool
    {
        return $this->managesUsers($user);
    }

    public function update(User $user, User $target): bool
    {
        return $this->managesUsers($user);
    }

    public function deleteAny(User $user): bool
    {
        return $this->managesUsers($user);
    }

    public function delete(User $user, User $target): bool
    {
        return ! $user->is($target) && $target->id !== 1 && $this->managesUsers($user);
    }

    /** Administradores herdam a gestao de usuarios da rota; a permissao cobre delegacoes pontuais. */
    private function managesUsers(User $user): bool
    {
        return $user->hasRole('admin', 'super-admin') || $user->hasAnyPermission('users.manage');
    }
}
