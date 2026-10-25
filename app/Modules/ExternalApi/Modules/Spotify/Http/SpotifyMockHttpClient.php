<?php

namespace App\Modules\ExternalApi\Modules\Spotify\Http;

use App\Modules\ExternalApi\Core\Contracts\MockHttpClientInterface;
use App\Modules\ExternalApi\Core\DTOs\BaseResponse;
use App\Modules\ExternalApi\Modules\Spotify\Mocks\SpotifyMockData;

class SpotifyMockHttpClient extends SpotifyHttpClient implements MockHttpClientInterface
{
    protected function makeRequest(string $method, string $endpoint, array $options = []): BaseResponse
    {
        return new BaseResponse(
            data: SpotifyMockData::response($method, $endpoint, $options),
            statusCode: 200,
            headers: []
        );
    }
}
