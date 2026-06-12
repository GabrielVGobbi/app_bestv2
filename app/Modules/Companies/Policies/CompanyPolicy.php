<?php

declare(strict_types=1);

namespace App\Modules\Companies\Policies;

use App\Models\User;
use App\Modules\Companies\Models\Company;

class CompanyPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canManageCompanies($user);
    }

    public function view(User $user, Company $company): bool
    {
        return $this->canManageCompanies($user);
    }

    public function create(User $user): bool
    {
        return $this->canManageCompanies($user);
    }

    public function update(User $user, Company $company): bool
    {
        return $this->canManageCompanies($user);
    }

    public function delete(User $user, Company $company): bool
    {
        return $this->canManageCompanies($user);
    }

    private function canManageCompanies(User $user): bool
    {
        return $user->roles()
            ->whereIn('slug', ['admin', 'super-admin'])
            ->exists();
    }
}
