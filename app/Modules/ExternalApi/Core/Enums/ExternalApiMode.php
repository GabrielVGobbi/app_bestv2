<?php

namespace App\Modules\ExternalApi\Core\Enums;

enum ExternalApiMode: string
{
    case Fake = 'fake';
    case Real = 'real';
}
