<?php

declare(strict_types=1);

namespace App\Supports\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use LogicException;

trait GenerateUniqueSlugTrait
{
    public static function bootGenerateUniqueSlugTrait(): void
    {
        static::saving(function (Model $model): void {
            if (! $model->shouldGenerateSlug()) {
                return;
            }

            $slugColumn = $model->getSlugColumn();

            $model->{$slugColumn} = $model->generateUniqueSlug(
                $model->getSlugValueForGeneration()
            );
        });
    }

    protected function shouldGenerateSlug(): bool
    {
        $slugColumn = $this->getSlugColumn();
        $sourceColumn = $this->getSlugSourceColumn();

        if (! $this->exists) {
            return true;
        }

        if (blank($this->{$slugColumn})) {
            return true;
        }

        if ($this->isDirty($slugColumn)) {
            return true;
        }

        return $this->shouldRegenerateSlugOnUpdate()
            && $this->isDirty($sourceColumn);
    }

    protected function getSlugValueForGeneration(): string
    {
        $slugColumn = $this->getSlugColumn();
        $sourceColumn = $this->getSlugSourceColumn();

        if ($this->isDirty($slugColumn) && filled($this->{$slugColumn})) {
            return (string) $this->{$slugColumn};
        }

        if (
            $this->exists
            && $this->shouldRegenerateSlugOnUpdate()
            && $this->isDirty($sourceColumn)
        ) {
            return $this->getSlugSourceValue();
        }

        if (filled($this->{$slugColumn})) {
            return (string) $this->{$slugColumn};
        }

        return $this->getSlugSourceValue();
    }

    public function generateUniqueSlug(string $value): string
    {
        $slugColumn = $this->getSlugColumn();

        $baseSlug = Str::slug($value, '-');

        if (blank($baseSlug)) {
            $baseSlug = Str::lower(Str::random(8));
        }

        $existingSlugs = static::query()
            ->where(function (Builder $query) use ($slugColumn, $baseSlug): void {
                $query
                    ->where($slugColumn, $baseSlug)
                    ->orWhere($slugColumn, 'LIKE', $baseSlug . '-%');
            })
            ->when(
                $this->exists && filled($this->getKey()),
                fn(Builder $query) => $query->whereKeyNot($this->getKey())
            )
            ->pluck($slugColumn)
            ->all();

        if (! in_array($baseSlug, $existingSlugs, true)) {
            return $baseSlug;
        }

        $highestNumber = collect($existingSlugs)
            ->map(function (string $existingSlug) use ($baseSlug): ?int {
                $pattern = '/^' . preg_quote($baseSlug, '/') . '-(\d+)$/';

                if (preg_match($pattern, $existingSlug, $matches)) {
                    return (int) $matches[1];
                }

                return null;
            })
            ->filter(fn(?int $number): bool => $number !== null)
            ->max();

        return $baseSlug . '-' . (($highestNumber ?? 0) + 1);
    }

    protected function getSlugColumn(): string
    {
        return $this->slugColumn ?? 'slug';
    }

    protected function getSlugSourceColumn(): string
    {
        if (! empty($this->slugSourceColumn)) {
            return $this->slugSourceColumn;
        }

        $fillables = $this->getFillable();

        if (in_array('name', $fillables, true)) {
            return 'name';
        }

        if (! empty($fillables[0])) {
            return $fillables[0];
        }

        throw new LogicException(
            'Defina a propriedade $slugSourceColumn no model ou adicione um campo fillable para gerar o slug.'
        );
    }

    protected function getSlugSourceValue(): string
    {
        $sourceColumn = $this->getSlugSourceColumn();

        $value = $this->{$sourceColumn} ?? null;

        if (blank($value)) {
            throw new LogicException(
                "O campo [{$sourceColumn}] está vazio. Não foi possível gerar o slug."
            );
        }

        return (string) $value;
    }

    protected function shouldRegenerateSlugOnUpdate(): bool
    {
        return (bool) ($this->regenerateSlugOnUpdate ?? false);
    }
}
