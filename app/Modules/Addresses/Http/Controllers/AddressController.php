<?php

declare(strict_types=1);

namespace App\Modules\Addresses\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addresses\Http\Requests\StoreAddressRequest;
use App\Modules\Addresses\Http\Requests\UpdateAddressRequest;
use App\Modules\Addresses\Http\Resources\AddressResource;
use App\Modules\Addresses\Models\Address;
use App\Modules\Addresses\Services\AddressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AddressController extends Controller
{
    public function __construct(private readonly AddressService $addresses) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $addressable = $this->addresses->resolveAddressable(
            alias: $request->string('model')->toString(),
            key: $request->input('model_id'),
        );

        if ($addressable === null) {
            return AddressResource::collection(collect());
        }

        return AddressResource::collection(
            $addressable->addresses()
                ->orderByDesc('is_default')
                ->orderByDesc('created_at')
                ->get()
        );
    }

    public function store(StoreAddressRequest $request): JsonResponse
    {
        $addressable = $this->addresses->resolveAddressable(
            alias: $request->string('model')->toString(),
            key: $request->input('model_id'),
        );

        if ($addressable === null) {
            return response()->json(['message' => 'Addressable model not found.'], 404);
        }

        $address = $this->addresses->findOrCreate($addressable, $request->safe()->except(['model', 'model_id']));

        return response()->json([
            'data' => new AddressResource($address),
            'message' => 'Address created successfully.',
        ], 201);
    }

    public function update(UpdateAddressRequest $request, Address $address): JsonResponse
    {
        $address = $this->addresses->update($address, $request->validated());

        return response()->json([
            'data' => new AddressResource($address),
            'message' => 'Address updated successfully.',
        ]);
    }

    public function destroy(Address $address): JsonResponse
    {
        $this->addresses->delete($address);

        return response()->json([
            'data' => null,
            'message' => 'Address removed successfully.',
        ]);
    }

    public function setDefault(Address $address): JsonResponse
    {
        $address = $this->addresses->setDefault($address);

        return response()->json([
            'data' => new AddressResource($address),
            'message' => 'Default address updated successfully.',
        ]);
    }
}
