<?php

declare(strict_types=1);

namespace App\Modules\SeederGenerator\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\SeederGenerator\DTOs\SeederBlueprint;
use App\Modules\SeederGenerator\Http\Requests\SeederGeneratorRequest;
use App\Modules\SeederGenerator\Services\SeederGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Inertia\Response;

class SeederGeneratorController extends Controller
{
    public function index(SeederGeneratorService $service): Response
    {
        return Inertia::render('admin/dev/seeder-generator', [
            'tables' => $service->getTables(),
        ]);
    }

    public function tables(SeederGeneratorService $service): JsonResponse
    {
        return response()->json(['data' => $service->getTables()]);
    }

    public function preview(SeederGeneratorRequest $request, SeederGeneratorService $service): JsonResponse
    {
        $blueprint = SeederBlueprint::fromArray($request->validated());
        $code = $service->generateSeederCode($blueprint);

        return response()->json([
            'data' => [
                'code' => $code,
                'class_name' => $blueprint->className(),
                'file_name' => $blueprint->fileName(),
            ],
        ]);
    }

    public function generate(SeederGeneratorRequest $request, SeederGeneratorService $service): JsonResponse
    {
        $blueprint = SeederBlueprint::fromArray($request->validated());
        $path = $service->saveSeeder($blueprint);
        $code = File::get($path);

        return response()->json([
            'data' => [
                'code' => $code,
                'class_name' => $blueprint->className(),
                'file_name' => $blueprint->fileName(),
                'path' => $path,
            ],
            'message' => "Seeder gerado em {$blueprint->fileName()}",
        ]);
    }

    public function execute(SeederGeneratorRequest $request, SeederGeneratorService $service): JsonResponse
    {
        $blueprint = SeederBlueprint::fromArray($request->validated());
        $result = $service->executeDirectly($blueprint);

        return response()->json([
            'data' => $result,
            'message' => $result['message'],
        ], $result['exit_code'] === 0 ? 200 : 422);
    }

    public function run(SeederGeneratorRequest $request, SeederGeneratorService $service): JsonResponse
    {
        $result = $service->runSeeder($request->validated('class'));

        return response()->json([
            'data' => $result,
            'message' => $result['exit_code'] === 0 ? 'Seeder executado com sucesso.' : 'Erro ao executar o seeder.',
        ], $result['exit_code'] === 0 ? 200 : 422);
    }
}
