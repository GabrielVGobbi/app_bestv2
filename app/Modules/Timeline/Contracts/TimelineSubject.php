<?php

declare(strict_types=1);

namespace App\Modules\Timeline\Contracts;

interface TimelineSubject
{
    public static function timelineType(): string;
}
