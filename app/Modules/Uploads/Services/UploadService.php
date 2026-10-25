<?php

declare(strict_types=1);

namespace App\Modules\Uploads\Services;

use App\Models\User;
use App\Modules\Timeline\Models\TimelineComment;
use App\Modules\Uploads\Contracts\HasUploadTags;
use App\Modules\Uploads\Models\Uploaded;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator as PaginatorImpl;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LogicException;
use RuntimeException;
use Throwable;

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
            } catch (Throwable) {
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
        ?string $search = null,
    ): LengthAwarePaginator {
        $parentable = $this->resolveModel($alias, $id);
        if ($parentable === null) {
            return new PaginatorImpl([], 0, $perPage, $page);
        }

        return $this->listForParent($parentable, $perPage, $page, $tag, $collection, $search);
    }

    public function listForParent(
        Model $parentable,
        int $perPage = 50,
        int $page = 1,
        ?string $tag = null,
        ?string $collection = null,
        ?string $search = null,
    ): LengthAwarePaginator {
        return $this->queryForParent($parentable, $tag, $collection, $search)
            ->paginate($perPage, ['*'], 'page', $page);
    }

    /** @return Builder<Uploaded> */
    public function queryForParent(Model $parentable, ?string $tag = null, ?string $collection = null, ?string $search = null): Builder
    {
        $query = Uploaded::query()
            ->with(['uploader:id,name', 'parentable'])
            ->where('parentable_type', $parentable::class)
            ->where('parentable_id', $parentable->getKey());
        $this->applyFilters($query, $tag, $collection);

        if ($parentable instanceof TimelineComment) {
            $query->with('parentable.commentable');
        }

        if ($search !== null && $search !== '') {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
            $grammar = $query->getQuery()->getGrammar();
            $query->where(function (Builder $searchQuery) use ($pattern, $grammar): void {
                foreach (['name', 'original_name', 'file_name', 'tag', 'extension'] as $field) {
                    $searchQuery->orWhereRaw($grammar->wrap($field)." LIKE ? ESCAPE '!'", [$pattern]);
                }
            });
        }

        return $query->latest()->orderByDesc('id');
    }

    public function canViewParent(User $user, Model $parentable): bool
    {
        return $this->parentDecision($user, $parentable, 'view');
    }

    public function canCreateForParent(User $user, Model $parentable): bool
    {
        return $this->parentDecision($user, $parentable, 'update');
    }

    /**
     * Reuse parent decisions only while serializing a read-only listing.
     * Mutations and subsequent requests always evaluate the policy again.
     */
    private function parentDecision(User $user, Model $parentable, string $ability): bool
    {
        $request = request();
        if (! $parentable->exists || ! $request->isMethod('GET') || ! $request->routeIs('api.uploads.index', 'api.timeline.show')) {
            return $user->can($ability, $parentable);
        }

        $attribute = self::class.'.parent_authorization';
        /** @var array<string, bool> $decisions */
        $decisions = $request->attributes->get($attribute, []);
        $token = $user->currentAccessToken();
        $key = implode(':', [spl_object_id($user), $token === null ? 'session' : spl_object_id($token), $parentable::class, (string) $parentable->getKey(), $ability]);
        if (! array_key_exists($key, $decisions)) {
            $decisions[$key] = $user->can($ability, $parentable);
            $request->attributes->set($attribute, $decisions);
        }

        return $decisions[$key];
    }

    /**
     * Store a file and create an Uploaded record. If the same file (by hash)
     * already exists for the same parent, collection and tag, return the
     * existing record without writing another file or replaying metadata.
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
        $connection = (new Uploaded)->getConnection();

        if ($parentable->getConnection() !== $connection) {
            throw new LogicException('Upload and parent must share the same database connection and session.');
        }

        $connectionName = $connection->getName();
        $disk = $disk ?? $this->diskForUpload($parentable, $tag, $collection);
        $hash = hash_file('sha256', $file->getRealPath()) ?: null;

        return $connection->transaction(function () use ($parentable, $file, $name, $tag, $collection, $isCover, $disk, $hash, $connectionName): Uploaded {
            $parentable->newQuery()->whereKey($parentable->getKey())->lockForUpdate()->firstOrFail();

            if ($hash !== null) {
                $existing = Uploaded::on($connectionName)
                    ->where('parentable_type', $parentable::class)
                    ->where('parentable_id', $parentable->getKey())
                    ->where('collection', $collection)
                    ->where('tag', $tag)
                    ->where('file_hash', $hash)
                    ->lockForUpdate()
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
            $filesystem = Storage::disk($disk);

            if ($filesystem->putFileAs($folder, $file, $fileName) === false) {
                $filesystem->delete($path);

                throw new RuntimeException('Unable to store the upload file.');
            }

            try {
                if ($isCover) {
                    $this->clearCoverFor($parentable, $connectionName);
                }

                return Uploaded::on($connectionName)->create([
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
            } catch (Throwable $exception) {
                // This catch runs before transaction() attempts COMMIT. A
                // commit failure must never delete a potentially referenced file.
                if (! $filesystem->delete($path)) {
                    throw new RuntimeException('Upload creation failed and its new file could not be removed.', 0, $exception);
                }

                throw $exception;
            }
        }, 1);
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

    protected function clearCoverFor(Model $parentable, ?string $connectionName = null): void
    {
        Uploaded::on($connectionName)
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
