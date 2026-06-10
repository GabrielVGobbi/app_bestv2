<?php

declare(strict_types=1);

namespace App\Modules\CrudGenerator\DTOs;

use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class CrudField
{
    /**
     * @param  array<int, string>  $validation
     */
    public function __construct(
        public string $name,
        public string $type = 'string',
        public bool $nullable = false,
        public bool $unique = false,
        public bool $searchable = false,
        public bool $filterable = false,
        public bool $sortable = true,
        public bool $exportable = true,
        public array $validation = [],
    ) {
        if (! preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
            throw new InvalidArgumentException("Invalid field name [{$name}]. Use snake_case.");
        }
    }

    /**
     * Parse CLI format: name:type:required:searchable:unique.
     */
    public static function fromCli(string $definition): self
    {
        $parts = array_values(array_filter(explode(':', $definition), fn (string $part): bool => $part !== ''));
        $name = $parts[0] ?? null;

        if (! $name) {
            throw new InvalidArgumentException('Field definition must start with a field name.');
        }

        $type = $parts[1] ?? 'string';
        $flags = array_slice($parts, 2);

        return new self(
            name: Str::snake($name),
            type: $type,
            nullable: in_array('nullable', $flags, true),
            unique: in_array('unique', $flags, true),
            searchable: in_array('searchable', $flags, true),
            filterable: in_array('filterable', $flags, true) || in_array('searchable', $flags, true),
            sortable: ! in_array('unsortable', $flags, true),
            exportable: ! in_array('no-export', $flags, true),
            validation: array_values(array_filter($flags, fn (string $flag): bool => ! in_array($flag, [
                'nullable',
                'required',
                'unique',
                'searchable',
                'filterable',
                'unsortable',
                'no-export',
            ], true))),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: Str::snake((string) $data['name']),
            type: (string) ($data['type'] ?? 'string'),
            nullable: (bool) ($data['nullable'] ?? false),
            unique: (bool) ($data['unique'] ?? false),
            searchable: (bool) ($data['searchable'] ?? false),
            filterable: (bool) ($data['filterable'] ?? false) || (bool) ($data['searchable'] ?? false),
            sortable: (bool) ($data['sortable'] ?? true),
            exportable: (bool) ($data['exportable'] ?? true),
            validation: array_values($data['validation'] ?? []),
        );
    }

    public function label(): string
    {
        return Str::headline($this->name);
    }

    public function tsType(): string
    {
        return match ($this->type) {
            'integer', 'bigInteger', 'decimal', 'float', 'double' => 'number',
            'boolean' => 'boolean',
            'json' => 'unknown[] | Record<string, unknown> | null',
            default => 'string',
        };
    }

    public function inputType(): string
    {
        return match ($this->type) {
            'integer', 'bigInteger', 'decimal', 'float', 'double' => 'number',
            'date' => 'date',
            'dateTime', 'timestamp' => 'datetime-local',
            default => 'text',
        };
    }
}
