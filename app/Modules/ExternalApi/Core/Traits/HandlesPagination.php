<?php

namespace App\Modules\ExternalApi\Core\Traits;

trait HandlesPagination
{
    protected function buildPaginationParams(int $page = 1, int $perPage = 20): array
    {
        return [
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    protected function extractPaginationInfo(array $response): array
    {
        return [
            'current_page' => $response['current_page'] ?? 1,
            'per_page' => $response['per_page'] ?? 20,
            'total' => $response['total'] ?? 0,
            'last_page' => $response['last_page'] ?? 1,
            'has_more' => $response['has_more'] ?? false,
        ];
    }
}
