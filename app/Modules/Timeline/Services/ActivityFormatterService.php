<?php

declare(strict_types=1);

namespace App\Modules\Timeline\Services;

use App\Modules\Timeline\Support\TimelineActivityConfig;
use App\Supports\ActivityActions\ActivityActionDefinition;
use App\Supports\ActivityActions\ActivityActionRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;

class ActivityFormatterService
{
    public function __construct(
        private readonly ActivityActionRegistry $actions,
    ) {}

    /**
     * @return array{
     *     type: string,
     *     id: string,
     *     event: string,
     *     label: string|null,
     *     description: string,
     *     icon: string|null,
     *     severity: string,
     *     causer: array{id: int|string|null, name: string|null}|null,
     *     changes: array<int, array{field: string, label: string, old: string|null, new: string|null}>,
     *     created_at: string|null
     * }
     */
    public function format(Activity $activity): array
    {
        $definition = $this->definitionFor($activity);
        $changes = $this->changes($activity);

        return [
            'type' => 'activity',
            'id' => 'activity:'.$activity->id,
            'event' => (string) ($activity->event ?? 'activity'),
            'label' => $definition?->label,
            'description' => $this->description($activity, $definition, $changes),
            'icon' => $definition?->icon ?? $this->defaultIcon($activity),
            'severity' => $definition?->severity ?? $this->defaultSeverity($activity),
            'causer' => $this->actor($activity->causer),
            'changes' => $changes,
            'created_at' => $activity->created_at?->toIso8601String(),
        ];
    }

    private function definitionFor(Activity $activity): ?ActivityActionDefinition
    {
        $properties = $this->properties($activity);
        $actionKey = Arr::get($properties, 'action_key');

        if (is_string($actionKey) && $actionKey !== '') {
            return $this->actions->find($actionKey);
        }

        return $this->actions->findByLogAndEvent(
            $activity->log_name,
            $activity->event,
            $activity->subject_type,
        );
    }

    /**
     * @return array<int, array{field: string, label: string, old: string|null, new: string|null}>
     */
    private function changes(Activity $activity): array
    {
        $properties = $this->properties($activity);
        $attributes = Arr::wrap(Arr::get($properties, 'attributes', []));
        $old = Arr::wrap(Arr::get($properties, 'old', []));

        if ($attributes === []) {
            return [];
        }

        $subject = $activity->subject;
        $config = $subject instanceof Model
            ? TimelineActivityConfig::for($subject)
            : ['fields' => [], 'labels' => [], 'hidden' => ['password', 'remember_token']];

        $fields = $config['fields'];
        $hidden = $config['hidden'];
        $labels = $config['labels'];

        return collect($attributes)
            ->filter(fn (mixed $value, string|int $field): bool => is_string($field))
            ->reject(fn (mixed $value, string $field): bool => in_array($field, $hidden, true))
            ->when($fields !== [], fn (Collection $changes): Collection => $changes->only($fields))
            ->map(fn (mixed $value, string $field): array => [
                'field' => $field,
                'label' => $labels[$field] ?? str($field)->replace('_', ' ')->title()->toString(),
                'old' => $this->humanValue($old[$field] ?? null),
                'new' => $this->humanValue($value),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{field: string, label: string, old: string|null, new: string|null}>  $changes
     */
    private function description(Activity $activity, ?ActivityActionDefinition $definition, array $changes): string
    {
        if (count($changes) === 1) {
            $change = $changes[0];

            return "{$change['label']} alterado de {$this->displayValue($change['old'])} para {$this->displayValue($change['new'])}.";
        }

        if (count($changes) > 1) {
            return count($changes).' campos atualizados.';
        }

        if (filled($activity->description)) {
            return (string) $activity->description;
        }

        return match ($activity->event) {
            'created' => 'Registro criado.',
            'updated' => 'Registro atualizado.',
            'deleted' => 'Registro removido.',
            'restored' => 'Registro restaurado.',
            default => $definition?->description ?? 'Atividade registrada.',
        };
    }

    private function defaultIcon(Activity $activity): string
    {
        return match ($activity->event) {
            'created' => 'PlusCircle',
            'updated' => 'Pencil',
            'deleted' => 'Trash2',
            'restored' => 'RotateCcw',
            default => 'Activity',
        };
    }

    private function defaultSeverity(Activity $activity): string
    {
        return match ($activity->event) {
            'created', 'restored' => 'success',
            'updated' => 'warning',
            'deleted' => 'danger',
            default => 'muted',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function properties(Activity $activity): array
    {
        return $activity->properties instanceof Collection
            ? $activity->properties->toArray()
            : Arr::wrap($activity->properties);
    }

    private function humanValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'Sim' : 'Nao';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: null;
    }

    private function displayValue(?string $value): string
    {
        return $value ?? 'vazio';
    }

    /**
     * @return array{id: int|string|null, name: string|null}|null
     */
    private function actor(?Model $actor): ?array
    {
        if (! $actor) {
            return null;
        }

        return [
            'id' => $actor->getKey(),
            'name' => $this->displayName($actor),
        ];
    }

    private function displayName(Model $model): string
    {
        foreach (['name', 'email', 'uuid'] as $attribute) {
            if (filled($model->getAttribute($attribute))) {
                return (string) $model->getAttribute($attribute);
            }
        }

        return '#'.$model->getKey();
    }
}
