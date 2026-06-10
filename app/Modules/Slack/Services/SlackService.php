<?php

declare(strict_types=1);

namespace App\Modules\Slack\Services;

use App\Modules\Slack\Contracts\SlackServiceInterface;
use App\Modules\Slack\DTOs\SlackMessageDTO;
use App\Modules\Slack\Enums\SlackAlertLevel;
use App\Supports\AppSettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;

class SlackService implements SlackServiceInterface
{
    private const STACK_TRACE_LINES = 8;

    private const REQUEST_TIMEOUT = 5;

    public function send(SlackMessageDTO $message): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        $webhookUrl = $this->webhookUrl();

        if (empty($webhookUrl)) {
            return false;
        }

        try {
            $response = Http::timeout(self::REQUEST_TIMEOUT)
                ->withHeader('Content-Type', 'application/json')
                ->post($webhookUrl, $message->toPayload());

            return $response->successful();
        } catch (ConnectionException $e) {
            Log::warning('SlackService: could not connect to webhook.', ['error' => $e->getMessage()]);

            return false;
        } catch (\Throwable $e) {
            Log::warning('SlackService: unexpected error sending message.', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function alert(string $message, SlackAlertLevel $level = SlackAlertLevel::Info, array $context = []): bool
    {
        $fields = [];

        foreach ($context as $key => $value) {
            $fields[] = [
                'title' => ucfirst((string) $key),
                'value' => is_array($value) ? json_encode($value) : (string) $value,
                'short' => true,
            ];
        }

        $fields[] = [
            'title' => 'Ambiente',
            'value' => config('slack.environment', app()->environment()),
            'short' => true,
        ];

        $dto = new SlackMessageDTO(
            text: $message,
            title: $level->label(),
            level: $level,
            fields: $fields,
            footer: config('slack.app_name').' | '.config('slack.app_url'),
        );

        return $this->send($dto);
    }

    public function exception(\Throwable $e, ?string $context = null): bool
    {
        $traceLines = collect(explode("\n", $e->getTraceAsString()))
            ->take(self::STACK_TRACE_LINES)
            ->implode("\n");

        $fields = [
            ['title' => 'Exceção',    'value' => get_class($e),                              'short' => false],
            ['title' => 'Mensagem',   'value' => $e->getMessage(),                            'short' => false],
            ['title' => 'Arquivo',    'value' => $e->getFile().':'.$e->getLine(),             'short' => false],
            ['title' => 'Ambiente',   'value' => config('slack.environment', 'unknown'),      'short' => true],
        ];

        if (Request::hasSession() || Request::getMethod() !== '') {
            $fields[] = ['title' => 'URL',    'value' => Request::fullUrl(),  'short' => false];
            $fields[] = ['title' => 'Método', 'value' => Request::method(),   'short' => true];
        }

        if ($context !== null) {
            $fields[] = ['title' => 'Contexto', 'value' => $context, 'short' => false];
        }

        $dto = new SlackMessageDTO(
            text: "```\n".$traceLines."\n```",
            title: 'Exceção não tratada',
            level: SlackAlertLevel::Critical,
            fields: $fields,
            footer: config('slack.app_name').' | '.now()->toDateTimeString(),
        );

        return $this->send($dto);
    }

    public function isEnabled(): bool
    {
        $enabled = AppSettings::get('slack', 'enabled', config('slack.enabled', true));

        return (bool) $enabled && ! empty($this->webhookUrl());
    }

    private function webhookUrl(): ?string
    {
        /** @var string|null $fromSettings */
        $fromSettings = AppSettings::get('slack', 'webhook_url');

        return $fromSettings ?: config('slack.webhook_url');
    }
}
