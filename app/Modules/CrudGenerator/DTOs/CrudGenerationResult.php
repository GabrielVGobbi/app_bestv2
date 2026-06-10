<?php

declare(strict_types=1);

namespace App\Modules\CrudGenerator\DTOs;

final readonly class CrudGenerationResult
{
    /**
     * @param  array<int, CrudGeneratedFile>  $files
     * @param  array<int, string>  $changes
     */
    public function __construct(
        public CrudBlueprint $blueprint,
        public array $files,
        public array $changes,
    ) {}

    /**
     * @return array{model: string, target: string, module: string, files: array<int, array{path: string, exists: bool, status: string}>, changes: array<int, string>}
     */
    public function summary(): array
    {
        return [
            'model' => $this->blueprint->model,
            'target' => $this->blueprint->target,
            'module' => $this->blueprint->module,
            'files' => array_map(fn (CrudGeneratedFile $file): array => $file->summary(), $this->files),
            'changes' => $this->changes,
        ];
    }

    public function hasConflicts(): bool
    {
        foreach ($this->files as $file) {
            if ($file->exists) {
                return true;
            }
        }

        return false;
    }
}
