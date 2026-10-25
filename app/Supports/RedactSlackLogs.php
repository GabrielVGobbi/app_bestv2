<?php

namespace App\Supports;

use Illuminate\Log\Logger;
use LogicException;
use Monolog\Handler\ProcessableHandlerInterface;
use Monolog\Logger as Monolog;
use Monolog\LogRecord;

class RedactSlackLogs
{
    public function __construct(private readonly SensitiveDataRedactor $redactor) {}

    public function __invoke(Logger $logger): void
    {
        $monolog = $logger->getLogger();
        if (! $monolog instanceof Monolog) {
            throw new LogicException('Slack redaction requires a Monolog logger.');
        }
        $sanitize = fn (LogRecord $record): LogRecord => $record->with(
            message: $this->redactor->text($record->message),
            context: $this->context($record->context),
            extra: $this->context($record->extra),
        );
        $monolog->pushProcessor($sanitize);
        foreach ($monolog->getHandlers() as $handler) {
            if ($handler instanceof ProcessableHandlerInterface) {
                $handler->pushProcessor($sanitize);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function context(array $context): array
    {
        $redacted = $this->redactor->value($context);

        return is_array($redacted) ? $redacted : [];
    }
}
