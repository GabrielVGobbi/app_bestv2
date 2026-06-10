<?php

declare(strict_types=1);

namespace App\Modules\Timeline\Traits;

use App\Modules\Timeline\Models\TimelineComment;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasComments
{
    public function comments(): MorphMany
    {
        return $this->morphMany(TimelineComment::class, 'commentable');
    }

    public function rootComments(): MorphMany
    {
        return $this->comments()->whereNull('parent_id');
    }
}
