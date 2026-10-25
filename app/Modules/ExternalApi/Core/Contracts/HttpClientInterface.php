<?php

namespace App\Modules\ExternalApi\Core\Contracts;

use App\Modules\ExternalApi\Core\DTOs\BaseResponse;

interface HttpClientInterface
{
    public function get(string $endpoint, array $params = []): BaseResponse;

    public function post(string $endpoint, array $data = []): BaseResponse;

    public function put(string $endpoint, array $data = []): BaseResponse;

    public function delete(string $endpoint): BaseResponse;
}
