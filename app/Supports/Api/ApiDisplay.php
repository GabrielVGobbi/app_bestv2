<?php

namespace App\Supports\Api;

use App\Http\Requests\PaginationRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use InvalidArgumentException;

class ApiDisplay
{
    /**
     * @param  class-string<Model>  $modelClass
     * @param  class-string<JsonResource>|null  $resourceClass
     */
    public function __construct(
        private readonly string $modelClass,
        private readonly ?string $resourceClass = null,
        private readonly ?Builder $query = null,
    ) {}

    /**
     * @param  class-string<Model>  $modelClass
     * @param  class-string<JsonResource>|null  $resourceClass
     */
    public static function for(string $modelClass, ?string $resourceClass = null): self
    {
        return new self($modelClass, $resourceClass);
    }

    public function usingQuery(Builder $query): self
    {
        return new self($this->modelClass, $this->resourceClass, $query);
    }

    public function paginate(PaginationRequest $request): AnonymousResourceCollection
    {
        $query = $request->applyTo($this->query ?? $this->modelClass::query());
        $model = $query->getModel();

        $includes = method_exists($model, 'getApiIncludes')
            ? array_intersect($request->includes(), $model->getApiIncludes())
            : [];

        if ($includes !== []) {
            $query->with($includes);
        }

        $resourceClass = $this->resourceClass ?? $this->guessResourceClass($model);

        return $resourceClass::collection(
            $query->paginate($request->perPage())->withQueryString()
        );
    }

    /**
     * @return class-string<JsonResource>
     */
    private function guessResourceClass(Model $model): string
    {
        $resourceClass = 'App\\Http\\Resources\\'.class_basename($model).'Resource';

        if (! class_exists($resourceClass)) {
            throw new InvalidArgumentException("Resource [{$resourceClass}] not found.");
        }

        return $resourceClass;
    }
}
