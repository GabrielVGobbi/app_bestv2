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
use finfo;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class UploadController extends Controller
{
    /** @var array<string, list<string>> */
    private const INLINE_MIME_EXTENSIONS = [
        'application/pdf' => ['pdf'],
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/gif' => ['gif'],
        'image/webp' => ['webp'],
        'image/avif' => ['avif'],
        'image/bmp' => ['bmp'],
        'image/x-ms-bmp' => ['bmp'],
        'audio/mpeg' => ['mp3'],
        'audio/wav' => ['wav'],
        'audio/x-wav' => ['wav'],
        'audio/vnd.wave' => ['wav'],
        'audio/ogg' => ['ogg', 'oga', 'opus'],
        'audio/flac' => ['flac'],
        'audio/x-flac' => ['flac'],
        'audio/aac' => ['aac'],
        'audio/x-hx-aac-adts' => ['aac'],
        'audio/mp4' => ['m4a', 'mp4'],
        'audio/x-m4a' => ['m4a', 'mp4'],
        'audio/webm' => ['webm', 'weba'],
        'video/mp4' => ['mp4', 'm4v'],
        'video/webm' => ['webm'],
        'video/ogg' => ['ogv', 'ogg'],
        'video/quicktime' => ['mov', 'qt'],
        'video/mpeg' => ['mpg', 'mpeg'],
        'video/x-msvideo' => ['avi'],
    ];

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
            tag: $request->string('tag')->toString() !== '' ? $request->string('tag')->toString() : null,
            collection: $request->string('collection')->toString() !== '' ? $request->string('collection')->toString() : null,
            search: $request->string('search')->toString() !== '' ? $request->string('search')->toString() : null,
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
        Gate::authorize('setCover', $upload);

        return new UploadedResource($this->service->setCover($upload));
    }

    public function toggleFavorite(Uploaded $upload): UploadedResource
    {
        Gate::authorize('update', $upload);

        return new UploadedResource($this->service->toggleFavorite($upload));
    }

    public function preview(Uploaded $upload): StreamedResponse
    {
        if (! $this->isPublicUpload($upload)) {
            Gate::authorize('view', $upload);
        }

        $disk = Storage::disk($upload->disk);

        if (! $disk->exists($upload->path)) {
            abort(404, 'Arquivo não encontrado.');
        }

        $mime = $this->safeMimeType($disk, $upload);

        return $disk->response(
            $upload->path,
            $upload->original_name ?: $upload->file_name,
            $this->fileHeaders($mime),
            $mime === null ? 'attachment' : 'inline',
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

    public function download(Uploaded $upload): StreamedResponse
    {
        Gate::authorize('view', $upload);

        $disk = Storage::disk($upload->disk);

        if (! $disk->exists($upload->path)) {
            abort(404, 'Arquivo não encontrado.');
        }

        return $disk->download(
            $upload->path,
            $upload->original_name ?: $upload->file_name,
            $this->fileHeaders($this->safeMimeType($disk, $upload)),
        );
    }

    private function safeMimeType(FilesystemAdapter $disk, Uploaded $upload): ?string
    {
        if (! extension_loaded('fileinfo')) {
            return null;
        }
        $stream = null;
        try {
            $stream = $disk->readStream($upload->path);
            if (! is_resource($stream)) {
                return null;
            }
            $sample = fread($stream, 65536);
            if ($sample === false || $sample === '') {
                return null;
            }
            $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($sample);
            if ($mime === false || ! isset(self::INLINE_MIME_EXTENSIONS[$mime])) {
                return null;
            }
            $extensions = self::INLINE_MIME_EXTENSIONS[$mime];
            if (! in_array(strtolower($upload->extension ?? ''), $extensions, true)) {
                return null;
            }
            $names = [$upload->file_name, $upload->path];
            if ($upload->original_name !== null && $upload->original_name !== '') {
                $names[] = $upload->original_name;
            }
            foreach ($names as $name) {
                if (! in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), $extensions, true)) {
                    return null;
                }
            }

            return $mime;
        } catch (Throwable) {
            return null;
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    /** @return array<string, string> */
    private function fileHeaders(?string $mime): array
    {
        return [
            'Content-Type' => $mime ?? 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "script-src 'none'; base-uri 'none'; form-action 'none'; frame-ancestors 'self'",
        ];
    }

    public function tags(TagUploadRequest $request): JsonResponse
    {
        $tags = $this->service->tagSuggestionsFor((string) $request->input('parentable_type'));

        return response()->json(['data' => $tags]);
    }
}
