<?php

declare(strict_types=1);

namespace App\Modules\Timeline\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class TimelineActivityConfig
{
    /**
     * @return array{
     *     fields: array<int, string>,
     *     labels: array<string, string>,
     *     hidden: array<int, string>
     * }
     */
    public static function for(Model $subject): array
    {
        $config = method_exists($subject, 'timelineActivityConfig')
            ? $subject->timelineActivityConfig()
            : [];

        if (! is_array($config)) {
            $config = [];
        }

        return [
            'fields' => array_values(array_filter(Arr::wrap($config['fields'] ?? []), 'is_string')),
            'labels' => array_filter(Arr::wrap($config['labels'] ?? []), 'is_string'),
            'hidden' => array_values(array_unique([
                'password',
                'remember_token',
                ...array_values(array_filter(Arr::wrap($config['hidden'] ?? []), 'is_string')),
            ])),
        ];
    }
}
