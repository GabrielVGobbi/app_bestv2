<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Http\Requests\Top;

use App\Modules\Playlists\Models\Top;

class UpdateTopRequest extends StoreTopRequest
{
    public function authorize(): bool
    {
        $top = $this->route('top');

        return $top instanceof Top
            && ($this->user()?->can('update', $top) ?? false);
    }
}
