<?php

declare(strict_types=1);

namespace App\Supports\ActivityActions;

use Illuminate\Support\Arr;

class ActivityActionDefinition
{
    public function __construct(
        public string $key,
        public string $label,
        public string $logName,
        public string $event,
        public string $description,
        public string $icon,
        public string $severity,
        public ?string $subjectType,
        public bool $enabled,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromConfig(string $key, array $config): self
    {
        return new self(
            key: $key,
            label: (string) Arr::get($config, 'label', $key),
            logName: (string) Arr::get($config, 'log_name', 'default'),
            event: (string) Arr::get($config, 'event', $key),
            description: (string) Arr::get($config, 'description', $key),
            icon: (string) Arr::get($config, 'icon', 'Activity'),
            severity: (string) Arr::get($config, 'severity', 'muted'),
            subjectType: Arr::get($config, 'subject_type'),
            enabled: (bool) Arr::get($config, 'enabled', true),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'log_name' => $this->logName,
            'event' => $this->event,
            'description' => $this->description,
            'icon' => $this->icon,
            'severity' => $this->severity,
            'subject_type' => $this->subjectType,
            'enabled' => $this->enabled,
        ];
    }
}
