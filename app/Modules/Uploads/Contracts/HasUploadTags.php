<?php

declare(strict_types=1);

namespace App\Modules\Uploads\Contracts;

interface HasUploadTags
{
    /**
     * @return array<string, string>
     */
    public static function uploadTagSuggestions(): array;
}
