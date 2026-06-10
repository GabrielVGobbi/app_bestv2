<?php

declare(strict_types=1);

namespace App\Modules\Uploads\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Uploads\Http\Requests\IndexUploadRequest;
use App\Modules\Uploads\Http\Requests\StoreUploadRequest;
use App\Modules\Uploads\Http\Requests\TagUploadRequest;
use App\Modules\Uploads\Http\Requests\UpdateUploadRequest;
use App\Modules\Uploads\Http\Resources\UploadedResource;
use App\Modules\Uploads\Models\Uploaded;
use App\Modules\Uploads\Services\UploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class UploadController extends Controller
{
    public function __construct(private readonly UploadService $service) {}

    public function index(IndexUploadRequest $request): AnonymousResourceCollection
    {
        $parentable = $this->service->resolveModel(
            (string) $request->input('parentable_type'),
            $request->input('parentable_id'),
        );

        abort_if($parentable === null, 404, 'Registro de destino não encontrado.');
        abort_unless($this->service->canViewParent($request->user(), $parentable), 403);

        $uploads = $this->service->listForParent(
            parentable: $parentable,
            perPage: (int) $request->integer('per_page', 50),
            page: (int) $request->integer('page', 1),
            tag: $request->string('tag')->toString() ?: null,
            collection: $request->string('collection')->toString() ?: null,
        );

        return UploadedResource::collection($uploads);
    }

    public function store(StoreUploadRequest $request): JsonResponse
    {
        $parentable = $this->service->resolveModel(
            (string) $request->input('parentable_type'),
            $request->input('parentable_id'),
        );

        if ($parentable === null) {
            return response()->json(['message' => 'Registro de destino não encontrado.'], 404);
        }

        abort_unless($this->service->canCreateForParent($request->user(), $parentable), 403);

        $items = [];
        foreach ((array) $request->file('files', []) as $index => $file) {
            $items[] = $this->service->upload(
                parentable: $parentable,
                file: $file,
                name: $index === 0 ? $request->input('name') : null,
                tag: $request->input('tag'),
                collection: $request->input('collection'),
                isCover: $index === 0 && $request->boolean('is_cover'),
            );
        }

        return response()->json([
            'message' => count($items) > 1 ? 'Arquivos enviados com sucesso.' : 'Arquivo enviado com sucesso.',
            'data' => UploadedResource::collection(collect($items)),
        ], 201);
    }

    public function show(Uploaded $upload): UploadedResource
    {
        Gate::authorize('view', $upload);

        return new UploadedResource($upload->load('uploader:id,name'));
    }

    public function update(UpdateUploadRequest $request, Uploaded $upload): UploadedResource
    {
        Gate::authorize('update', $upload);

        return new UploadedResource($this->service->updateMetadata($upload, $request->validated()));
    }

    public function destroy(Uploaded $upload): JsonResponse
    {
        Gate::authorize('delete', $upload);
        $this->service->delete($upload);

        return response()->json(['message' => 'Arquivo removido com sucesso.']);
    }

    public function setCover(Uploaded $upload): UploadedResource
    {
        Gate::authorize('update', $upload);

        return new UploadedResource($this->service->setCover($upload));
    }

    public function toggleFavorite(Uploaded $upload): UploadedResource
    {
        Gate::authorize('update', $upload);

        return new UploadedResource($this->service->toggleFavorite($upload));
    }

    public function preview(Uploaded $upload)
    {
        if (! $this->isPublicUpload($upload)) {
            Gate::authorize('view', $upload);
        }

        $disk = Storage::disk($upload->disk);

        if (! $disk->exists($upload->path)) {
            abort(404, 'Arquivo não encontrado.');
        }

        return $disk->response(
            $upload->path,
            $upload->original_name ?: $upload->file_name,
            ['Content-Type' => $upload->mime_type]
        );
    }

    private function isPublicUpload(Uploaded $upload): bool
    {
        $rules = config('uploads.public_rules', []);
        $aliases = config('uploads.aliases', []);

        return collect($rules)->contains(function (array $rule) use ($upload, $aliases) {
            $modelClass = $aliases[$rule['alias'] ?? ''] ?? null;

            if ($modelClass && $upload->parentable_type !== $modelClass) {
                return false;
            }

            if (! empty($rule['tag']) && $upload->tag !== $rule['tag']) {
                return false;
            }

            if (! empty($rule['collection']) && $upload->collection !== $rule['collection']) {
                return false;
            }

            return true;
        });
    }

    public function download(Uploaded $upload)
    {
        Gate::authorize('view', $upload);

        $disk = Storage::disk($upload->disk);

        if (! $disk->exists($upload->path)) {
            abort(404, 'Arquivo não encontrado.');
        }

        return $disk->download(
            $upload->path,
            $upload->original_name ?: $upload->file_name,
            ['Content-Type' => $upload->mime_type]
        );
    }

    public function tags(TagUploadRequest $request): JsonResponse
    {
        $tags = $this->service->tagSuggestionsFor((string) $request->input('parentable_type'));

        return response()->json(['data' => $tags]);
    }
}
