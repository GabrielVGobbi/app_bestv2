<?php

declare(strict_types=1);

namespace App\Modules\Slack\Services;

use App\Modules\Slack\Contracts\SlackServiceInterface;
use App\Modules\Slack\DTOs\SlackMessageDTO;
use App\Modules\Slack\Enums\SlackAlertLevel;
use App\Supports\AppSettings;
use App\Supports\SensitiveDataRedactor;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use Throwable;

class SlackService implements SlackServiceInterface
{
    private const REQUEST_TIMEOUT = 5;

    public function __construct(private readonly SensitiveDataRedactor $redactor) {}

    public function send(SlackMessageDTO $message): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }
        $webhookUrl = $this->webhookUrl();
        if ($webhookUrl === null) {
            return false;
        }
        try {
            $payload = $this->redactor->value($message->toPayload(), secrets: [$webhookUrl]);
            if (! is_array($payload)) {
                return false;
            }
            $response = Http::timeout(self::REQUEST_TIMEOUT)
                ->withHeader('Content-Type', 'application/json')
                ->post($webhookUrl, $payload);

            return $response->successful();
        } catch (Throwable $exception) {
            Log::warning('SlackService: message delivery failed.', ['exception_type' => $exception::class]);

            return false;
        }
    }

    /** @param array<string, mixed> $context */
    public function alert(string $message, SlackAlertLevel $level = SlackAlertLevel::Info, array $context = []): bool
    {
        $safeContext = $this->redactor->value($context, secrets: [$this->webhookUrl() ?? '']);
        if (! is_array($safeContext)) {
            return false;
        }
        $fields = [];
        foreach ($safeContext as $key => $value) {
            $fields[] = [
                'title' => ucfirst((string) $key),
                'value' => is_array($value) ? json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : (string) $value,
                'short' => true,
            ];
        }
        $fields[] = [
            'title' => 'Ambiente',
            'value' => config('slack.environment', app()->environment()),
            'short' => true,
        ];

        return $this->send(new SlackMessageDTO(
            text: $message,
            title: $level->label(),
            level: $level,
            fields: $fields,
            footer: config('slack.app_name').' | '.config('slack.app_url'),
        ));
    }

    public function exception(Throwable $exception, ?string $context = null): bool
    {
        $fields = [
            ['title' => 'Exceção', 'value' => $exception::class, 'short' => false],
            ['title' => 'Mensagem', 'value' => 'Detalhes da exceção omitidos por segurança.', 'short' => false],
            ['title' => 'Arquivo', 'value' => $exception->getFile().':'.$exception->getLine(), 'short' => false],
            ['title' => 'Ambiente', 'value' => config('slack.environment', 'unknown'), 'short' => true],
        ];
        if (Request::hasSession() || Request::getMethod() !== '') {
            $fields[] = ['title' => 'URL', 'value' => $this->redactor->url(Request::fullUrl()), 'short' => false];
            $fields[] = ['title' => 'Método', 'value' => Request::method(), 'short' => true];
        }
        if ($context !== null) {
            $fields[] = ['title' => 'Contexto', 'value' => $context, 'short' => false];
        }

        return $this->send(new SlackMessageDTO(
            text: 'Ocorreu uma exceção não tratada.',
            title: 'Exceção não tratada',
            level: SlackAlertLevel::Critical,
            fields: $fields,
            footer: config('slack.app_name').' | '.now()->toDateTimeString(),
        ));
    }

    public function isEnabled(): bool
    {
        $enabled = AppSettings::get('slack', 'enabled', config('slack.enabled', true));

        return (bool) $enabled && $this->webhookUrl() !== null;
    }

    private function webhookUrl(): ?string
    {
        $url = AppSettings::get('slack', 'webhook_url') ?: config('slack.webhook_url');

        return is_string($url) && $url !== '' ? $url : null;
    }
}
