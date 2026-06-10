<?php

declare(strict_types=1);

namespace App\Supports\ActivityActions;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

class ActivityActionLogger
{
    public function __construct(
        private readonly ActivityActionRegistry $registry,
    ) {}

    /**
     * @param  array<string, mixed>  $properties
     */
    public function log(
        string $actionKey,
        ?Model $subject = null,
        Model|Authenticatable|null $causer = null,
        array $properties = [],
        ?Request $request = null,
    ): ?Activity {
        $definition = $this->registry->require($actionKey);

        if (! $definition->enabled && ! (bool) ($properties['test'] ?? false)) {
            return null;
        }

        $request ??= request();
        $causer = $causer instanceof Model ? $causer : auth()->user();
        $properties = $this->normalizeProperties($definition, $properties, $request);

        $logger = activity($properties['test'] ?? false ? 'playground' : $definition->logName)
            ->withProperties($properties)
            ->event($definition->event);

        if ($subject) {
            $logger->performedOn($subject);
        }

        if ($causer instanceof Model) {
            $logger->causedBy($causer);
        }

        return $logger->log($this->interpolateDescription($definition, $subject, $causer));
    }

    /**
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    private function normalizeProperties(ActivityActionDefinition $definition, array $properties, Request $request): array
    {
        return [
            'action_key' => $definition->key,
            'action_label' => $definition->label,
            'ip' => $properties['ip'] ?? $request->ip(),
            'user_agent' => $properties['user_agent'] ?? $request->userAgent(),
            ...$properties,
        ];
    }

    private function interpolateDescription(
        ActivityActionDefinition $definition,
        ?Model $subject,
        Model|Authenticatable|null $causer,
    ): string {
        return Str::of($definition->description)
            ->replace(':causer', $this->displayName($causer, 'Sistema'))
            ->replace(':subject', $this->displayName($subject, 'registro'))
            ->toString();
    }

    private function displayName(mixed $model, string $fallback): string
    {
        if (! $model) {
            return $fallback;
        }

        foreach (['name', 'email', 'uuid'] as $attribute) {
            if (isset($model->{$attribute}) && filled($model->{$attribute})) {
                return (string) $model->{$attribute};
            }
        }

        return method_exists($model, 'getKey') ? "#{$model->getKey()}" : $fallback;
    }
}
