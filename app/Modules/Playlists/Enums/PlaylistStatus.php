<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Enums;

use App\Supports\Contracts\BadgeableEnum;
use App\Supports\Enums\Concerns\HasBadgePresenter;

enum PlaylistStatus: string implements BadgeableEnum
{
    use HasBadgePresenter;

    case Active = 'active';
    case Inactive = 'inactive';
    case Archived = 'archived';

    /** @var array<string, array{label: string, color: string, icon: string}> */
    private const array PRESENTERS = [
        'active' => ['label' => 'Ativa', 'color' => 'emerald', 'icon' => 'Radio'],
        'inactive' => ['label' => 'Inativa', 'color' => 'zinc', 'icon' => 'PauseCircle'],
        'archived' => ['label' => 'Arquivada', 'color' => 'amber', 'icon' => 'Archive'],
    ];

    public function label(): string
    {
        return $this->getLabelText();
    }

    public function color(): string
    {
        return $this->getLabelColor();
    }
}
