<?php

declare(strict_types=1);

namespace App\Modules\Timeline\Support;

use App\Modules\Timeline\Contracts\TimelineSubject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Schema;

class TimelineSubjectResolver
{
    public function resolve(string $type, string|int $id): Model
    {
        $class = $this->classFor($type);

        if (is_string($id) && ! is_numeric($id) && $this->hasUuidColumn($class)) {
            $subject = $class::query()->where('uuid', $id)->first();

            if (! $subject) {
                throw (new ModelNotFoundException)->setModel($class, [$id]);
            }

            return $subject;
        }

        $subject = $class::query()->whereKey($id)->first();

        if (! $subject) {
            throw (new ModelNotFoundException)->setModel($class, [$id]);
        }

        return $subject;
    }

    public function classFor(string $type): string
    {
        $aliases = config('timeline.aliases', []);
        $class = is_array($aliases) ? ($aliases[$type] ?? null) : null;

        abort_unless(
            is_string($class)
            && class_exists($class)
            && is_subclass_of($class, Model::class)
            && is_subclass_of($class, TimelineSubject::class),
            404
        );

        return $class;
    }

    private function hasUuidColumn(string $class): bool
    {
        /** @var Model $model */
        $model = new $class;

        return Schema::hasColumn($model->getTable(), 'uuid');
    }
}
