<?php

declare(strict_types=1);

namespace App\Modules\Email\Services;

use App\Modules\Email\Contracts\WebhookHandlerInterface;
use App\Modules\Email\Webhooks\PostmarkWebhookHandler;
use App\Modules\Email\Webhooks\ResendWebhookHandler;
use App\Modules\Email\Webhooks\SesWebhookHandler;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EmailWebhookService
{
    /** @var array<string, class-string<WebhookHandlerInterface>> */
    private array $handlers = [
        'resend' => ResendWebhookHandler::class,
        'ses' => SesWebhookHandler::class,
        'postmark' => PostmarkWebhookHandler::class,
    ];

    public function handle(string $provider, Request $request): void
    {
        $request->attributes->remove(EmailWebhookEventRecorder::AUTHENTICATED_REQUEST);
        $handlerClass = $this->handlers[$provider] ?? null;

        if (! $handlerClass) {
            throw new NotFoundHttpException("Unknown email webhook provider: [{$provider}]");
        }

        /** @var WebhookHandlerInterface $handler */
        $handler = app($handlerClass);

        if (! $handler->verifySignature($request)) {
            throw new HttpException(401, 'Invalid webhook signature.');
        }

        $request->attributes->set(EmailWebhookEventRecorder::AUTHENTICATED_REQUEST, [
            'provider' => $provider,
            'hash' => hash('sha256', $request->getContent()),
        ]);

        $handler->handle($request);
    }

    /**
     * Register a custom provider handler at runtime.
     *
     * @param  class-string<WebhookHandlerInterface>  $handlerClass
     */
    public function register(string $provider, string $handlerClass): void
    {
        $this->handlers[$provider] = $handlerClass;
    }

    /** @return array<string> */
    public function supportedProviders(): array
    {
        return array_keys($this->handlers);
    }
}
