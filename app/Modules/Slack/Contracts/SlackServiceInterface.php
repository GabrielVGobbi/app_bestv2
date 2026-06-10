<?php

declare(strict_types=1);

namespace App\Modules\Slack\Contracts;

use App\Modules\Slack\DTOs\SlackMessageDTO;
use App\Modules\Slack\Enums\SlackAlertLevel;

interface SlackServiceInterface
{
    /**
     * Send a fully constructed message DTO to Slack.
     */
    public function send(SlackMessageDTO $message): bool;

    /**
     * Send a simple alert with optional context fields.
     *
     * @param  array<string, mixed>  $context
     */
    public function alert(string $message, SlackAlertLevel $level = SlackAlertLevel::Info, array $context = []): bool;

    /**
     * Report an exception to Slack with formatted stack trace.
     */
    public function exception(\Throwable $e, ?string $context = null): bool;

    /**
     * Whether Slack integration is active and configured.
     */
    public function isEnabled(): bool;
}
