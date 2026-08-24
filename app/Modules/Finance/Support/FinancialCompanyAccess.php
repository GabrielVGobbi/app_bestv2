<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use App\Models\User;
use App\Modules\Companies\Models\Company;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;

class FinancialCompanyAccess
{
    public function isGlobalAdministrator(User $user): bool
    {
        /** @var array<int, string> $roles */
        $roles = config('finance.global_company_roles', ['developer', 'admin', 'super-admin']);

        return $user->hasRole(...$roles);
    }

    public function canAccess(User $user, Company|int $company): bool
    {
        if ($this->isGlobalAdministrator($user)) {
            return true;
        }

        $companyId = $company instanceof Company ? $company->getKey() : $company;

        $user->loadMissing('companies:id');

        return $user->companies->contains(
            fn (Company $authorizedCompany): bool => $authorizedCompany->getKey() === $companyId,
        );
    }

    /**
     * @throws AuthorizationException
     */
    public function authorize(User $user, Company|int $company): void
    {
        if (! $this->canAccess($user, $company)) {
            throw new AuthorizationException(__('Você não possui acesso a esta empresa.'));
        }
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function scope(Builder $query, User $user): Builder
    {
        if ($this->isGlobalAdministrator($user)) {
            return $query;
        }

        return $query->whereIn(
            $query->qualifyColumn('company_id'),
            $user->companies()->select('companies.id'),
        );
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function scopeIncludingShared(Builder $query, User $user): Builder
    {
        if ($this->isGlobalAdministrator($user)) {
            return $query;
        }

        return $query->where(function (Builder $companyQuery) use ($query, $user): void {
            $companyQuery
                ->whereNull($query->qualifyColumn('company_id'))
                ->orWhereIn(
                    $query->qualifyColumn('company_id'),
                    $user->companies()->select('companies.id'),
                );
        });
    }
}
