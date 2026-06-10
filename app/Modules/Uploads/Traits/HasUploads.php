<?php

declare(strict_types=1);

namespace App\Modules\Uploads\Traits;

use App\Modules\Uploads\Models\Uploaded;
use App\Modules\Uploads\Services\UploadService;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\UploadedFile;

/**
 * Adds a morphMany relation to the Uploaded model plus convenience helpers
 * for attaching files. All IO/DB work is delegated to UploadService so the
 * trait stays thin and the logic lives in a single place.
 */
trait HasUploads
{
    public function uploads(): MorphMany
    {
        return $this->morphMany(Uploaded::class, 'parentable')->latest();
    }

    public function uploadsByTag(string $tag): MorphMany
    {
        return $this->uploads()->where('tag', $tag);
    }

    public function uploadsByCollection(string $collection): MorphMany
    {
        return $this->uploads()->where('collection', $collection);
    }

    public function coverImage(): ?Uploaded
    {
        /** @var Uploaded|null */
        return $this->uploads()->where('is_cover', true)->first();
    }

    public function addUpload(
        UploadedFile $file,
        ?string $name = null,
        ?string $tag = null,
        ?string $collection = null,
        bool $isCover = false,
        ?string $disk = null,
    ): Uploaded {
        return app(UploadService::class)->upload(
            parentable: $this,
            file: $file,
            name: $name,
            tag: $tag,
            collection: $collection,
            isCover: $isCover,
            disk: $disk,
        );
    }

    /**
     * @param  array<int, UploadedFile>  $files
     * @return array<int, Uploaded>
     */
    public function addMultipleUploads(
        array $files,
        ?string $tag = null,
        ?string $collection = null,
        ?string $disk = null,
    ): array {
        $service = app(UploadService::class);
        $result = [];
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }
            $result[] = $service->upload(
                parentable: $this,
                file: $file,
                tag: $tag,
                collection: $collection,
                disk: $disk,
            );
        }

        return $result;
    }

    public function removeUpload(Uploaded $upload): bool
    {
        if (
            $upload->parentable_id !== $this->getKey()
            || $upload->parentable_type !== static::class
        ) {
            return false;
        }

        return app(UploadService::class)->delete($upload);
    }

    public function removeAllUploads(?string $tag = null): int
    {
        $query = $this->uploads();
        if ($tag !== null) {
            $query->where('tag', $tag);
        }
        $service = app(UploadService::class);
        $count = 0;
        foreach ($query->get() as $upload) {
            if ($service->delete($upload)) {
                $count++;
            }
        }

        return $count;
    }
}
