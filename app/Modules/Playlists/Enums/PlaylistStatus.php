<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Enums;

enum PlaylistStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Ativa',
            self::Inactive => 'Inativa',
            self::Archived => 'Arquivada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'default',
            self::Inactive => 'outline',
            self::Archived => 'secondary',
        };
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return collect(self::cases())
            ->map(fn (self $case): array => [
                'value' => $case->value,
                'label' => $case->label(),
            ])
            ->all();
    }
}
