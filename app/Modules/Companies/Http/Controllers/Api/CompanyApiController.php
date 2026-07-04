<?php

declare(strict_types=1);

namespace App\Modules\Companies\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaginationRequest;
use App\Modules\Companies\Actions\SyncCompanyLogo;
use App\Modules\Companies\Http\Requests\Company\StoreCompanyRequest;
use App\Modules\Companies\Http\Requests\Company\UpdateCompanyRequest;
use App\Modules\Companies\Http\Resources\CompanyResource;
use App\Modules\Companies\Models\Company;
use App\Supports\Api\ApiDisplay;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;

class CompanyApiController extends Controller
{
    public function index(PaginationRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Company::class);

        return ApiDisplay::for(Company::class, CompanyResource::class)->paginate($request);
    }

    public function store(StoreCompanyRequest $request, SyncCompanyLogo $syncCompanyLogo): CompanyResource
    {
        $validated = $request->validated();
        $company = Company::create(Arr::except($validated, ['logo', 'remove_logo']));

        $syncCompanyLogo->handle($company, $request->file('logo'), $request->boolean('remove_logo'));

        return new CompanyResource($company);
    }

    public function show(Company $company): CompanyResource
    {
        Gate::authorize('view', $company);

        return new CompanyResource($company);
    }

    public function update(UpdateCompanyRequest $request, Company $company, SyncCompanyLogo $syncCompanyLogo): CompanyResource
    {
        $validated = $request->validated();

        $company->update(Arr::except($validated, ['logo', 'remove_logo']));
        $syncCompanyLogo->handle($company, $request->file('logo'), $request->boolean('remove_logo'));

        return new CompanyResource($company);
    }

    public function destroy(Company $company): JsonResponse
    {
        Gate::authorize('delete', $company);

        $company->delete();

        return response()->json(null, 204);
    }
}
