<?php

declare(strict_types=1);

namespace App\Modules\Email\Exceptions;

use RuntimeException;

class TemplateNotFoundException extends RuntimeException
{
    public static function forTemplate(string $template): self
    {
        return new self("Email template not found: [{$template}]");
    }
}
