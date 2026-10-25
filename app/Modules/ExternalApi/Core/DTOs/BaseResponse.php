<?php

namespace App\Modules\ExternalApi\Core\DTOs;

class BaseResponse
{
    public function __construct(
        private array $data = [],
        private int $statusCode = 200,
        private array $headers = []
    ) {}

    public function getData(): array
    {
        return $this->data;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function isSuccessful(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    public function toArray(): array
    {
        return [
            'data' => $this->data,
            'status_code' => $this->statusCode,
            'headers' => $this->headers,
        ];
    }
}
