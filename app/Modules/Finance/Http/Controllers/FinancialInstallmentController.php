<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Models\FinancialInstallment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Filas operacionais de contas a pagar e a receber.
 *
 * A parcela e a unidade de trabalho do financeiro: quem opera paga um vencimento,
 * nao um titulo. Filtrar um periodo aqui devolve uma linha por vencimento, que e o
 * que a tela de lancamentos nao consegue responder.
 */
class FinancialInstallmentController extends Controller
{
    public function index(string $direction): RedirectResponse
    {
        Gate::authorize('viewAny', FinancialInstallment::class);

        return to_route('financial-entries.index', ['direction' => $direction]);
    }
}
