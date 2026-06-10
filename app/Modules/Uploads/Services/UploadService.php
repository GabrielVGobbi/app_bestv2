<?php

declare(strict_types=1);

namespace App\Modules\Uploads\Services;

use App\Models\User;
use App\Modules\Uploads\Contracts\HasUploadTags;
use App\Modules\Uploads\Models\Uploaded;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator as PaginatorImpl;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UploadService
{
    /**
     * Resolve a model instance from a string alias (registered in config).
     *
     * When `$id` is a UUID string we skip the primary-key lookup entirely:
     * MySQL silently casts a UUID string to its leading integer value
     * (e.g. '3f6a45b4-…' → 3), which can return the wrong record.
     * We detect the UUID format first and query the uuid column directly.
     */
    public function resolveModel(string $alias, int|string $id): ?Model
    {
        $class = $this->aliasToClass($alias);
        if ($class === null) {
            return null;
        }

        /** @var class-string<Model> $class */

        // UUID string → resolve via the model's uuid column (or findByToken if available)
        if (is_string($id) && Str::isUuid($id)) {
            if (method_exists($class, 'findByToken')) {
                return $class::findByToken($id);
            }

            try {
                return $class::query()->where('uuid', $id)->first();
            } catch (\Throwable) {
                return null;
            }
        }

        // Integer (or numeric string) → standard PK lookup
        $model = $class::query()->find($id);

        return $model instanceof Model ? $model : null;
    }

    /**
     * Return the class-string registered under the given alias, if any.
     *
     * @return class-string<Model>|null
     */
    public function aliasToClass(string $alias): ?string
    {
        /** @var array<string, class-string<Model>> $aliases */
        $aliases = config('uploads.aliases', []);
        $class = $aliases[$alias] ?? null;
        if (! $class || ! class_exists($class)) {
            return null;
        }

        return $class;
    }

    /**
     * Return the alias registered for the given model instance, if any.
     */
    public function classToAlias(Model|string $modelOrClass): ?string
    {
        $class = is_string($modelOrClass) ? $modelOrClass : $modelOrClass::class;
        /** @var array<string, class-string<Model>> $aliases */
        $aliases = config('uploads.aliases', []);
        $found = array_search($class, $aliases, true);

        return $found === false ? null : (string) $found;
    }

    /**
     * Return the tag suggestions registered for a given model alias.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function tagSuggestionsFor(string $alias): array
    {
        $class = $this->aliasToClass($alias);
        if ($class === null || ! is_subclass_of($class, HasUploadTags::class)) {
            return [];
        }
        /** @var class-string<HasUploadTags> $class */
        $suggestions = $class::uploadTagSuggestions();

        return array_map(
            fn (string $value, string $label) => ['value' => $value, 'label' => $label],
            array_keys($suggestions),
            array_values($suggestions),
        );
    }

    /**
     * Paginated list of uploads for a given parentable.
     */
    public function list(
        string $alias,
        int|string $id,
        int $perPage = 50,
        int $page = 1,
        ?string $tag = null,
        ?string $collection = null,
    ): LengthAwarePaginator {
        $parentable = $this->resolveModel($alias, $id);
        if ($parentable === null) {
            return new PaginatorImpl([], 0, $perPage, $page);
        }

        return $this->listForParent($parentable, $perPage, $page, $tag, $collection);
    }

    public function listForParent(
        Model $parentable,
        int $perPage = 50,
        int $page = 1,
        ?string $tag = null,
        ?string $collection = null,
    ): LengthAwarePaginator {
        $query = Uploaded::query()
            ->with('uploader:id,name')
            ->where('parentable_type', $parentable::class)
            ->where('parentable_id', $parentable->getKey());
        $this->applyFilters($query, $tag, $collection);

        return $query->latest()->paginate($perPage, ['*'], 'page', $page);
    }

    public function canViewParent(User $user, Model $parentable): bool
    {
        return $user->can('view', $parentable);
    }

    public function canCreateForParent(User $user, Model $parentable): bool
    {
        return $user->can('update', $parentable);
    }

    /**
     * Store a file and create an Uploaded record. If the same file (by hash)
     * already exists for the same parent + tag, returns the existing record
     * instead of duplicating the upload.
     */
    public function upload(
        Model $parentable,
        UploadedFile $file,
        ?string $name = null,
        ?string $tag = null,
        ?string $collection = null,
        bool $isCover = false,
        ?string $disk = null,
    ): Uploaded {
        $disk = $disk ?? $this->diskForUpload($parentable, $tag, $collection);
        $hash = hash_file('sha256', $file->getRealPath()) ?: null;
        if ($hash !== null) {
            $existing = Uploaded::query()
                ->where('parentable_type', $parentable::class)
                ->where('parentable_id', $parentable->getKey())
                ->where('file_hash', $hash)
                ->when($tag !== null, fn ($q) => $q->where('tag', $tag))
                ->first();
            if ($existing instanceof Uploaded) {
                return $existing;
            }
        }
        $originalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $fileName = (string) Str::uuid().($extension ? ".{$extension}" : '');
        $folder = sprintf(
            'uploads/%s/%s/%s',
            Str::snake(class_basename($parentable)),
            $this->pathIdentifierFor($parentable),
            date('Y/m'),
        );
        $path = "{$folder}/{$fileName}";
        Storage::disk($disk)->putFileAs($folder, $file, $fileName);
        if ($isCover) {
            $this->clearCoverFor($parentable);
        }
        $uploaded = Uploaded::create([
            'user_id' => auth()->id(),
            'uploader_id' => auth()->id(),
            'parentable_type' => $parentable::class,
            'parentable_id' => $parentable->getKey(),
            'name' => $name ?: pathinfo($originalName, PATHINFO_FILENAME),
            'original_name' => $originalName,
            'file_name' => $fileName,
            'extension' => $extension ?: null,
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'path' => $path,
            'disk' => $disk,
            'file_hash' => $hash,
            'size' => $file->getSize() ?: null,
            'tag' => $tag,
            'collection' => $collection,
            'is_cover' => $isCover,
            'source' => 'upload',
        ]);

        return $uploaded;
    }

    /**
     * Update metadata of an existing upload (name and/or tag).
     *
     * @param  array{name?: string, tag?: string|null, collection?: string|null}  $data
     */
    public function updateMetadata(Uploaded $upload, array $data): Uploaded
    {
        $payload = array_intersect_key($data, array_flip(['name', 'tag', 'collection']));
        if ($payload === []) {
            return $upload->fresh() ?? $upload;
        }
        $upload->update($payload);

        return $upload->fresh() ?? $upload;
    }

    public function setCover(Uploaded $upload): Uploaded
    {
        Uploaded::query()
            ->where('parentable_type', $upload->parentable_type)
            ->where('parentable_id', $upload->parentable_id)
            ->where('is_cover', true)
            ->where('id', '!=', $upload->id)
            ->update(['is_cover' => false]);
        $upload->update(['is_cover' => true]);

        return $upload->fresh() ?? $upload;
    }

    public function toggleFavorite(Uploaded $upload): Uploaded
    {
        $upload->update(['is_favorite' => ! (bool) $upload->is_favorite]);

        return $upload->fresh() ?? $upload;
    }

    public function delete(Uploaded $upload): bool
    {
        Storage::disk($upload->disk)->delete($upload->path);

        return (bool) $upload->delete();
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */
    /**
     * @param  Builder<Uploaded>  $query
     */
    protected function applyFilters(Builder $query, ?string $tag, ?string $collection): void
    {
        if ($tag !== null && $tag !== '') {
            $query->where('tag', $tag);
        }
        if ($collection !== null && $collection !== '') {
            $query->where('collection', $collection);
        }
    }

    protected function clearCoverFor(Model $parentable): void
    {
        Uploaded::query()
            ->where('parentable_type', $parentable::class)
            ->where('parentable_id', $parentable->getKey())
            ->where('is_cover', true)
            ->update(['is_cover' => false]);
    }

    private function diskForUpload(Model $parentable, ?string $tag, ?string $collection): string
    {
        $alias = $this->classToAlias($parentable);

        foreach ((array) config('uploads.public_rules', []) as $rule) {
            if (($rule['alias'] ?? null) !== $alias) {
                continue;
            }

            if (array_key_exists('tag', $rule) && ($rule['tag'] ?? null) !== $tag) {
                continue;
            }

            if (array_key_exists('collection', $rule) && ($rule['collection'] ?? null) !== $collection) {
                continue;
            }

            return (string) config('uploads.public_disk', 'public');
        }

        return (string) config('uploads.disk', 'local');
    }

    private function pathIdentifierFor(Model $parentable): string
    {
        if (isset($parentable->uuid)) {
            return (string) $parentable->uuid;
        }

        return (string) $parentable->getKey();
    }
}
