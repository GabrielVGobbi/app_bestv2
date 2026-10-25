<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Enums\FinancialDirection;
use App\Modules\Finance\Http\Requests\FinancialInstallmentIndexRequest;
use App\Modules\Finance\Http\Resources\FinancialInstallmentResource;
use App\Modules\Finance\Models\FinancialInstallment;
use App\Modules\Finance\Support\FinancialCompanyAccess;
use App\Supports\Api\ApiDisplay;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class FinancialInstallmentApiController extends Controller
{
    /**
     * A direcao vem do path, nao de um filtro.
     *
     * Sao dois motivos. Primeiro, `direction` ja e o parametro de ordenacao do
     * `PaginationRequest` (`asc`/`desc`), entao usar o mesmo nome em query colidiria.
     * Segundo, e o que garante que a fila de pagar nunca devolva um recebivel: o
     * recorte e aplicado na query base, fora do alcance dos filtros do cliente.
     */
    public function index(
        string $direction,
        FinancialInstallmentIndexRequest $request,
        FinancialCompanyAccess $companyAccess,
    ): AnonymousResourceCollection {
        Gate::authorize('viewAny', FinancialInstallment::class);

        $monthStart = $request->monthStart();

        return ApiDisplay::for(FinancialInstallment::class, FinancialInstallmentResource::class)
            ->usingQuery(
                $companyAccess->scope(FinancialInstallment::query(), $request->user())
                    ->when(
                        $direction !== 'all',
                        fn ($query) => $query->direction(FinancialDirection::from($direction)),
                    )
                    ->when(
                        $monthStart !== null,
                        // Half-open range: SQLite stores date casts with a time part.
                        fn ($query) => $query
                            ->where('due_date', '>=', $monthStart->toDateString())
                            ->where('due_date', '<', $monthStart->addMonthNoOverflow()->toDateString()),
                    )
                    ->with([
                        'financialEntry' => fn (BelongsTo $relation) => $relation->withCount('installments')->with('originable'),
                        'counterparty',
                        'category' => fn (BelongsTo $relation) => $relation->with('parent'),
                    ])
            )
            ->paginate($request);
    }
}
