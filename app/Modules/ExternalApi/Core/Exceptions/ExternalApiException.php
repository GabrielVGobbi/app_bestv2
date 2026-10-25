<?php

namespace App\Modules\ExternalApi\Core\Exceptions;

use Exception;

class ExternalApiException extends Exception
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Exception $previous = null,
        private array $context = []
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getContext(): array
    {
        return $this->context;
    }
}
