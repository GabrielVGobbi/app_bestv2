<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Marca tematizável
    |--------------------------------------------------------------------------
    |
    | Cada projeto derivado troca nome, sigla, acento e logos pelo .env.
    | `name` nulo usa app.name. `hue` fora de 0 a 360 cai em 215 (petróleo).
    | Logos são caminhos relativos a public/; arquivo ausente usa o símbolo do kit.
    |
    */

    'name' => env('BRAND_NAME'),
    'short_name' => env('BRAND_SHORT_NAME', 'SK'),
    'hue' => env('BRAND_HUE', 215),
    'logo' => [
        'mark' => env('BRAND_LOGO_MARK'),
        'light' => env('BRAND_LOGO_LIGHT'),
        'dark' => env('BRAND_LOGO_DARK'),
    ],

];
