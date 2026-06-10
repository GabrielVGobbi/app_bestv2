<?php

declare(strict_types=1);

namespace App\Modules\CrudGenerator\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CrudGenerator\DTOs\CrudBlueprint;
use App\Modules\CrudGenerator\Http\Requests\CrudGeneratorRequest;
use App\Modules\CrudGenerator\Services\CrudGeneratorService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class CrudGeneratorController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/dev/crud-generator');
    }

    public function preview(CrudGeneratorRequest $request, CrudGeneratorService $generator): JsonResponse
    {
        $result = $generator->preview(CrudBlueprint::fromArray($request->validated()));

        return response()->json(['data' => $result->summary()]);
    }

    public function store(CrudGeneratorRequest $request, CrudGeneratorService $generator): JsonResponse
    {
        $validated = $request->validated();
        $result = $generator->apply(
            CrudBlueprint::fromArray($validated),
            (bool) ($validated['force'] ?? false),
        );

        return response()->json([
            'data' => $result->summary(),
            'message' => 'CRUD generated. Run php artisan wayfinder:generate --with-form --no-interaction.',
        ]);
    }
}
