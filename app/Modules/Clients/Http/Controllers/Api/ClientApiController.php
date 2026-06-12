<?php

declare(strict_types=1);

namespace App\Modules\Clients\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaginationRequest;
use App\Modules\Clients\Http\Requests\Client\StoreClientRequest;
use App\Modules\Clients\Http\Requests\Client\UpdateClientRequest;
use App\Modules\Clients\Http\Resources\ClientResource;
use App\Modules\Clients\Models\Client;
use App\Supports\Api\ApiDisplay;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ClientApiController extends Controller
{
    public function index(PaginationRequest $request): AnonymousResourceCollection
    {
        return ApiDisplay::for(Client::class, ClientResource::class)->paginate($request);
    }

    public function store(StoreClientRequest $request): ClientResource
    {
        $client = Client::create($request->validated());

        return new ClientResource($client->load(['responsibleUser', 'addresses']));
    }

    public function show(Client $client): ClientResource
    {
        return new ClientResource($client->load(['responsibleUser', 'addresses']));
    }

    public function update(UpdateClientRequest $request, Client $client): ClientResource
    {
        $client->update($request->validated());

        return new ClientResource($client->load(['responsibleUser', 'addresses']));
    }

    public function destroy(Client $client): JsonResponse
    {
        $client->delete();

        return response()->json(null, 204);
    }
}
