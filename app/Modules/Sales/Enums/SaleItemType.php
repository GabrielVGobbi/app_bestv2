<?php

declare(strict_types=1);

namespace App\Modules\Sales\Enums;

enum SaleItemType: string
{
    case Service = 'service';
    case PlaylistSlot = 'playlist_slot';
}
