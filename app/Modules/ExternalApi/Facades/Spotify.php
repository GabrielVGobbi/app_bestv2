<?php

namespace App\Modules\ExternalApi\Facades;

use Illuminate\Support\Facades\Facade;

class Spotify extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'spotify';
    }
}
