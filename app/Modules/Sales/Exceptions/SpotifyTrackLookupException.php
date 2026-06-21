<?php

declare(strict_types=1);

namespace App\Modules\Sales\Exceptions;

use RuntimeException;

class SpotifyTrackLookupException extends RuntimeException
{
    public function __construct(
        string $message = '',
        private readonly ?int $httpStatus = null,
        private readonly ?int $retryAfterSeconds = null,
    ) {
        parent::__construct($message);
    }

    public function httpStatus(): ?int
    {
        return $this->httpStatus;
    }

    public function retryAfterSeconds(): ?int
    {
        return $this->retryAfterSeconds;
    }
}
