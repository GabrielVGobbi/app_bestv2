<?php

namespace App\Http\Controllers\Api;

use App\Concerns\Exportable;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateExportRequest;
use App\Http\Resources\ExportResource;
use App\Jobs\ProcessExport;
use App\Models\Export;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class ExportApiController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $exports = Export::where('user_id', $request->user()->id)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->latest()
            ->limit(10)
            ->get();

        return ExportResource::collection($exports);
    }

    public function store(CreateExportRequest $request): JsonResponse
    {
        $export = Export::create([
            'user_id' => $request->user()->id,
            'model_class' => $request->validated('model_class'),
            'status' => Export::STATUS_PENDING,
            'format' => $request->validated('format'),
            'columns' => $request->validated('columns') ?? [],
            'filters' => $request->validated('filters') ?? [],
            'search' => $request->validated('search'),
            'sort_column' => $request->validated('sort'),
            'sort_direction' => $request->validated('sort_direction'),
        ]);

        ProcessExport::dispatch($export);

        return (new ExportResource($export))->response()->setStatusCode(201);
    }

    public function show(Export $export, Request $request): ExportResource
    {
        abort_if(! $export->isOwnedBy($request->user()), 403);

        return new ExportResource($export);
    }

    public function columns(Request $request): JsonResponse
    {
        $allowedModels = config('exports.allowed_models', []);
        $request->validate([
            'model_class' => ['required', 'string', Rule::in(is_array($allowedModels) ? $allowedModels : [])],
        ]);

        /** @var string $modelClass */
        $modelClass = $request->input('model_class');
        $actor = $request->user();
        if (! $actor instanceof User || ! is_subclass_of($modelClass, Model::class)
            || ! in_array(Exportable::class, class_uses_recursive($modelClass), true)
            || ! method_exists($modelClass, 'authorizedExportQuery')
            || ! method_exists($modelClass, 'getExportableColumns')) {
            abort(403);
        }

        $modelClass::authorizedExportQuery($actor);

        $columns = $modelClass::getExportableColumns();

        $formatted = array_map(
            fn (string $key, string $label) => ['key' => $key, 'label' => $label],
            array_keys($columns),
            array_values($columns),
        );

        return response()->json(['data' => array_values($formatted)]);
    }
}
