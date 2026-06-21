<?php

declare(strict_types=1);

namespace App\Supports\Contracts;

interface BadgeableEnum
{
    public function getValue(): string;

    public function getLabelText(): string;

    public function getLabelColor(): string;

    public function getIcon(): string;

    /** @return array{value: string, label: string, color: string, icon: string} */
    public function toPresenterArray(): array;
}
