<?php

declare(strict_types=1);

namespace App\Modules\CrudGenerator\DTOs;

final readonly class CrudGeneratedFile
{
    public function __construct(
        public string $path,
        public string $content,
        public bool $exists,
    ) {}

    /**
     * @return array{path: string, exists: bool, status: string}
     */
    public function summary(): array
    {
        return [
            'path' => $this->path,
            'exists' => $this->exists,
            'status' => $this->exists ? 'conflict' : 'create',
        ];
    }
}
