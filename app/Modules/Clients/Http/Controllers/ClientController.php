<?php

declare(strict_types=1);

namespace App\Modules\Clients\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Clients\Http\Requests\Client\StoreClientRequest;
use App\Modules\Clients\Http\Requests\Client\UpdateClientRequest;
use App\Modules\Clients\Http\Resources\ClientResource;
use App\Modules\Clients\Models\Client;
use App\Supports\ActivityActions\ActivityActionLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('app/clients/index');
    }

    public function create(): Response
    {
        return Inertia::render('app/clients/create');
    }

    public function store(StoreClientRequest $request): RedirectResponse
    {
        $client = Client::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Client created.')]);

        return to_route('clients.show', $client);
    }

    public function show(Request $request, Client $client, ActivityActionLogger $activityLogger): Response
    {
        // $activityLogger->log(
        //    actionKey: 'clients.viewed',
        //    subject: $client,
        //    causer: $request->user(),
        //    request: $request,
        // );

        return Inertia::render('app/clients/show', [
            'clientData' => new ClientResource($client),
        ]);
    }

    public function edit(Client $client): Response
    {
        return Inertia::render('app/clients/edit', [
            'clientData' => new ClientResource($client),
        ]);
    }

    public function update(UpdateClientRequest $request, Client $client): RedirectResponse
    {
        $client->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Client updated.')]);

        return to_route('clients.show', $client);
    }

    public function destroy(Client $client): RedirectResponse
    {
        $client->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Client deleted.')]);

        return to_route('clients.index', [], 303);
    }
}
