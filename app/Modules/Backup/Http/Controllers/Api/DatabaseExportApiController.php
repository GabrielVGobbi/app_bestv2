<?php

declare(strict_types=1);

namespace App\Modules\Backup\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Backup\Http\Requests\CreateDatabaseExportRequest;
use App\Modules\Backup\Http\Resources\DatabaseExportResource;
use App\Modules\Backup\Jobs\ProcessDatabaseExport;
use App\Modules\Backup\Models\DatabaseExport;
use App\Supports\DatabaseDumpOptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DatabaseExportApiController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()?->hasRole('developer'), 403);

        $exports = DatabaseExport::where('user_id', $request->user()->id)
            ->where(function ($query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->latest()
            ->limit(10)
            ->get();

        return DatabaseExportResource::collection($exports);
    }

    public function store(CreateDatabaseExportRequest $request): JsonResponse
    {
        $export = DatabaseExport::create([
            'user_id' => $request->user()->id,
            'tables' => $request->validated('tables'),
            'export_type' => $request->validated('export_type'),
            'status' => DatabaseExport::STATUS_PENDING,
        ]);

        ProcessDatabaseExport::dispatch($export);

        return (new DatabaseExportResource($export))->response()->setStatusCode(201);
    }

    public function show(DatabaseExport $databaseExport, Request $request): DatabaseExportResource
    {
        abort_unless($request->user()?->hasRole('developer'), 403);
        abort_if(! $databaseExport->isOwnedBy($request->user()), 403);

        return new DatabaseExportResource($databaseExport);
    }

    public function download(DatabaseExport $databaseExport, Request $request): StreamedResponse
    {
        abort_unless($request->user()?->hasRole('developer'), 403);
        abort_if(! $databaseExport->isOwnedBy($request->user()), 403);
        abort_if($databaseExport->status !== DatabaseExport::STATUS_COMPLETED, 404);

        if ($databaseExport->isExpired()) {
            abort(410, 'Este arquivo expirou.');
        }

        $disk = config('database-exports.disk', 'local');

        abort_if(! $databaseExport->file_path, 404);
        abort_unless(Storage::disk($disk)->exists($databaseExport->file_path), 404);

        activity('database_exports')
            ->performedOn($databaseExport)
            ->causedBy($request->user())
            ->log('downloaded');

        return Storage::disk($disk)
            ->download($databaseExport->file_path, $databaseExport->file_name);
    }

    public function tables(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasRole('developer'), 403);

        $connection = DB::connection();
        abort_if(DatabaseDumpOptions::supportedTypes($connection->getDriverName()) === [], 422, 'Driver de dump não suportado.');
        $tables = array_map(fn (string $name): array => ['name' => $name], DatabaseDumpOptions::tables($connection));

        return response()->json(['data' => $tables]);
    }
}
