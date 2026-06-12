<?php

use App\Modules\Clients\Http\Controllers\Api\ClientApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web API
|--------------------------------------------------------------------------
| Endpoints internos consumidos pela SPA React (Inertia). Compartilham o
| guard auth:sanctum: requisições de domínio stateful autenticam via sessão
| (cookie) e requisições com Bearer token autenticam via token Sanctum.
*/

Route::as('web-api.')->middleware('auth:sanctum')->group(function () {
    Route::apiResource('clients', ClientApiController::class)->parameters(['clients' => 'client']);
});
