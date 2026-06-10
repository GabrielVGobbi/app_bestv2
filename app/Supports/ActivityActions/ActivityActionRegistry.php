<?php

declare(strict_types=1);

namespace App\Supports\ActivityActions;

use Illuminate\Support\Collection;

class ActivityActionRegistry
{
    public function find(string $key): ?ActivityActionDefinition
    {
        $actions = config('activity-actions.actions', []);
        $config = is_array($actions) ? ($actions[$key] ?? null) : null;

        if (! is_array($config)) {
            return null;
        }

        return ActivityActionDefinition::fromConfig($key, $config);
    }

    public function require(string $key): ActivityActionDefinition
    {
        $definition = $this->find($key);

        if (! $definition) {
            throw new \InvalidArgumentException("Activity action [{$key}] is not configured.");
        }

        return $definition;
    }

    /**
     * @return Collection<string, ActivityActionDefinition>
     */
    public function all(bool $includeDisabled = false): Collection
    {
        return collect(config('activity-actions.actions', []))
            ->filter(fn (mixed $config): bool => is_array($config))
            ->map(fn (array $config, string $key): ActivityActionDefinition => ActivityActionDefinition::fromConfig($key, $config))
            ->when(! $includeDisabled, fn (Collection $actions): Collection => $actions->filter->enabled);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function catalog(bool $includeDisabled = false): array
    {
        return $this->all($includeDisabled)
            ->mapWithKeys(fn (ActivityActionDefinition $definition): array => [
                $definition->key => $definition->toArray(),
            ])
            ->all();
    }

    public function findByLogAndEvent(?string $logName, ?string $event, ?string $subjectType = null): ?ActivityActionDefinition
    {
        return $this->all(includeDisabled: true)
            ->first(function (ActivityActionDefinition $definition) use ($logName, $event, $subjectType): bool {
                if ($definition->logName !== $logName || $definition->event !== $event) {
                    return false;
                }

                return $subjectType === null || $definition->subjectType === null || $definition->subjectType === $subjectType;
            });
    }
}
