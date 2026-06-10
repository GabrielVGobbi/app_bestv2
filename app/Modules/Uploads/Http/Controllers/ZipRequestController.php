<?php

declare(strict_types=1);

namespace App\Modules\Uploads\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Uploads\Http\Requests\BulkDestroyUploadRequest;
use App\Modules\Uploads\Http\Requests\StoreZipRequestRequest;
use App\Modules\Uploads\Jobs\GenerateZipJob;
use App\Modules\Uploads\Models\Uploaded;
use App\Modules\Uploads\Models\ZipRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ZipRequestController extends Controller
{
    public function store(StoreZipRequestRequest $request): JsonResponse
    {
        $ids = array_map('intval', (array) $request->input('ids', []));
        $uploads = Uploaded::query()->whereIn('id', $ids)->get();
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

        abort_unless($zip->isReady(), 422, 'Arquivo ZIP indisponível.');

        $relativePath = $zip->zip_path ?: "zip-requests/{$zip->uuid}.zip";
        $downloadName = Str::slug($zip->label).'.zip';

        $absoluteLegacyPath = storage_path('app/'.ltrim($relativePath, '/'));
        $absoluteLocalPath = storage_path('app/private/'.ltrim($relativePath, '/'));

        // Compatibilidade com ZIPs antigos gerados fora do disk local.
        if (is_file($absoluteLegacyPath)) {
            return response()->download($absoluteLegacyPath, $downloadName, [
                'Content-Type' => 'application/zip',
            ]);
        }

        if (is_file($absoluteLocalPath)) {
            return response()->download($absoluteLocalPath, $downloadName, [
                'Content-Type' => 'application/zip',
            ]);
        }

        // Em alguns cenários (worker fora do ar, limpeza de storage), o registro
        // fica "ready" mas o arquivo não existe mais. Tentamos regenerar uma vez.
        if (! Storage::disk('local')->exists($relativePath)) {
            $zip->update(['status' => 'pending', 'error_message' => null]);
            (new GenerateZipJob($zip->fresh()))->handle();
            $zip->refresh();
            $relativePath = $zip->zip_path ?: "zip-requests/{$zip->uuid}.zip";
        }

        abort_if(! $zip->isReady() || ! Storage::disk('local')->exists($relativePath), 404, 'ZIP não encontrado.');

        return Storage::disk('local')->download(
            $relativePath,
            $downloadName,
            ['Content-Type' => 'application/zip']
        );
    }

    public function bulkDestroy(BulkDestroyUploadRequest $request): JsonResponse
    {
        $ids = array_map('intval', (array) $request->input('ids', []));
        $uploads = Uploaded::query()->whereIn('id', $ids)->get();

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
