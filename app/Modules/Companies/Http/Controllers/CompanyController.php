<?php

declare(strict_types=1);

namespace App\Modules\Companies\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Companies\Actions\SyncCompanyLogo;
use App\Modules\Companies\Http\Requests\Company\StoreCompanyRequest;
use App\Modules\Companies\Http\Requests\Company\UpdateCompanyRequest;
use App\Modules\Companies\Http\Resources\CompanyResource;
use App\Modules\Companies\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CompanyController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Company::class);

        return Inertia::render('app/companies/index', [
            'permissions' => [
                'can_manage' => $this->canManageCompanies(),
            ],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Company::class);

        return Inertia::render('app/companies/create');
    }

    public function store(StoreCompanyRequest $request, SyncCompanyLogo $syncCompanyLogo): RedirectResponse
    {
        $validated = $request->validated();
        $company = Company::create(Arr::except($validated, ['logo', 'remove_logo']));

        $syncCompanyLogo->handle($company, $request->file('logo'), $request->boolean('remove_logo'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Company created.')]);

        return to_route('companies.show', $company);
    }

    public function show(Company $company): Response
    {
        Gate::authorize('view', $company);

        return Inertia::render('app/companies/show', [
            'companyData' => new CompanyResource($company),
            'permissions' => [
                'can_manage' => $this->canManageCompanies(),
            ],
        ]);
    }

    public function edit(Company $company): Response
    {
        Gate::authorize('update', $company);

        return Inertia::render('app/companies/edit', [
            'companyData' => new CompanyResource($company),
            'permissions' => [
                'can_manage' => $this->canManageCompanies(),
            ],
        ]);
    }

    public function update(UpdateCompanyRequest $request, Company $company, SyncCompanyLogo $syncCompanyLogo): RedirectResponse
    {
        $validated = $request->validated();

        $company->update(Arr::except($validated, ['logo', 'remove_logo']));
        $syncCompanyLogo->handle($company, $request->file('logo'), $request->boolean('remove_logo'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Company updated.')]);

        return to_route('companies.show', $company);
    }

    public function destroy(Company $company): RedirectResponse
    {
        Gate::authorize('delete', $company);

        $company->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Company deleted.')]);

        return to_route('companies.index', [], 303);
    }

    private function canManageCompanies(): bool
    {
        return auth()->user()?->roles()
            ->whereIn('slug', ['admin', 'super-admin'])
            ->exists() ?? false;
    }
}
