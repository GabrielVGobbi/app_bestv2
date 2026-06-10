<?php

declare(strict_types=1);

namespace App\Modules\Email\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Email\Services\EmailWebhookService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class EmailWebhookController extends Controller
{
    public function __construct(private readonly EmailWebhookService $webhookService) {}

    public function handle(string $provider, Request $request): Response
    {
        $this->webhookService->handle($provider, $request);

        return response()->noContent();
    }
}
