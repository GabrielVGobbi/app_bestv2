<?php

namespace App\Modules\ExternalApi\Core\Enums;

enum ExternalApiProvider: string
{
    case Iugu = 'iugu';
    case Asaas = 'asaas';
    case Spotify = 'spotify';
}
