<?php

declare(strict_types=1);

namespace App\Modules\SeederGenerator\DTOs;

readonly class SeederBlueprint
{
    public function __construct(
        public string $table,
        public string $mode,
        public int $limit,
        public bool $truncate,
        public bool $idempotent,
        public ?string $uniqueColumn,
        public string $seederName,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            table: $data['table'],
            mode: $data['mode'],
            limit: (int) ($data['limit'] ?? 50),
            truncate: (bool) ($data['truncate'] ?? false),
            idempotent: (bool) ($data['idempotent'] ?? false),
            uniqueColumn: $data['unique_column'] ?? null,
            seederName: $data['seeder_name'],
        );
    }

    public function className(): string
    {
        return 'Generated'.str_replace(['-', '_'], '', ucwords($this->seederName, '-_')).'Seeder';
    }

    public function fileName(): string
    {
        return $this->className().'.php';
    }
}
