<?php

declare(strict_types=1);

namespace App\Modules\Uploads\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Timeline\Models\TimelineComment;
use App\Modules\Uploads\Http\Requests\BulkDestroyUploadRequest;
use App\Modules\Uploads\Http\Requests\StoreZipRequestRequest;
use App\Modules\Uploads\Jobs\GenerateZipJob;
use App\Modules\Uploads\Models\Uploaded;
use App\Modules\Uploads\Models\ZipRequest;
use App\Modules\Uploads\Services\UploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ZipRequestController extends Controller
{
    public function __construct(private readonly UploadService $service) {}

    public function store(StoreZipRequestRequest $request): JsonResponse
    {
        if ($request->has('all_matching')) {
            $parentable = $this->service->resolveModel(
                $request->string('all_matching.parentable_type')->toString(),
                $request->input('all_matching.parentable_id'),
            );
            abort_if($parentable === null, 404, 'Registro de destino não encontrado.');
            abort_unless($this->service->canViewParent($request->user(), $parentable), 403);
            $uploads = $this->service->queryForParent(
                $parentable,
                $request->string('all_matching.tag')->toString() !== '' ? $request->string('all_matching.tag')->toString() : null,
                $request->string('all_matching.collection')->toString() !== '' ? $request->string('all_matching.collection')->toString() : null,
                $request->string('all_matching.search')->toString() !== '' ? $request->string('all_matching.search')->toString() : null,
            )->limit(201)->get();
            abort_if($uploads->count() > 200, 422, 'Há mais de 200 arquivos. Refine os filtros para solicitar o ZIP.');
        } else {
            $ids = array_map('intval', (array) $request->input('ids', []));
            $uploads = Uploaded::query()->with('parentable')->whereIn('id', $ids)->get();
        }
        $uploads->loadMorph('parentable', [TimelineComment::class => ['commentable']]);
        $authorizedIds = [];

        foreach ($uploads as $upload) {
            Gate::authorize('view', $upload);
            $authorizedIds[] = (int) $upload->id;
        }

        abort_if(count($authorizedIds) === 0, 422, 'Nenhum arquivo autorizado para compactação.');

        $zip = ZipRequest::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => auth()->id(),
            'label' => (string) ($request->input('label') ?: 'Download de anexos'),
            'file_ids' => $authorizedIds,
            'file_count' => count($authorizedIds),
            'status' => 'pending',
        ]);

        GenerateZipJob::dispatch($zip);

        return response()->json(['data' => $zip], 202);
    }

    public function show(string $uuid): JsonResponse
    {
        $zip = ZipRequest::query()
            ->where('uuid', $uuid)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $zip->expireIfNeeded();
        if ($zip->status === 'ready') {
            $zip->markUnavailable();
        }

        return response()->json([
            'data' => [
                'uuid' => $zip->uuid,
                'status' => $zip->status,
                'label' => $zip->label,
                'file_count' => $zip->file_count,
                'error_message' => $zip->error_message,
                'download_url' => $zip->isReady() ? route('api.uploads.zip-requests.download', ['uuid' => $zip->uuid]) : null,
                'expires_at' => $zip->expires_at?->toIso8601String(),
            ],
        ]);
    }

    public function download(string $uuid)
    {
        $zip = ZipRequest::query()
            ->where('uuid', $uuid)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $zip->expireIfNeeded();
        abort_unless($zip->isReady(), 422, 'Arquivo ZIP indisponível. Solicite um novo arquivo.');
        $zip->markUnavailable();
        abort_unless($zip->isReady(), 404, 'Arquivo ZIP indisponível. Solicite um novo arquivo.');

        $path = $zip->artifactPath();
        abort_if($path === null || $zip->resolveArtifactPath($path) === null || ! Storage::disk('local')->exists($path), 404, 'ZIP não encontrado.');

        return Storage::disk('local')->download($path, Str::slug($zip->label).'.zip', ['Content-Type' => 'application/zip']);
    }

    public function bulkDestroy(BulkDestroyUploadRequest $request): JsonResponse
    {
        $ids = array_map('intval', (array) $request->input('ids', []));
        $uploads = Uploaded::query()
            ->with('parentable')
            ->whereIn('id', $ids)
            ->get()
            ->loadMorph('parentable', [TimelineComment::class => ['commentable']]);

        foreach ($uploads as $upload) {
            Gate::authorize('delete', $upload);
        }

        $deleted = 0;
        foreach ($uploads as $upload) {
            Storage::disk($upload->disk)->delete($upload->path);
            $upload->delete();
            $deleted++;
        }

        return response()->json([
            'message' => $deleted === 1 ? 'Arquivo removido.' : "{$deleted} arquivos removidos.",
            'data' => null,
            'deleted' => $deleted,
        ]);
    }
}
