<?php

declare(strict_types=1);

namespace App\Modules\Email\DTOs;

use App\Modules\Email\Enums\EmailCategory;
use Carbon\Carbon;

final readonly class SendEmailDTO
{
    /**
     * @param  array<string, mixed>  $variables  Variables injected into the Blade template.
     */
    public function __construct(
        public string $to,
        public string $subject,
        public string $template,
        public EmailCategory $category,
        public array $variables = [],
        public ?string $toName = null,
        public ?string $replyTo = null,
        public ?string $notifiableType = null,
        public ?int $notifiableId = null,
        public ?string $idempotencyKey = null,
        public ?Carbon $scheduledAt = null,
    ) {}

    public function withTo(string $email, ?string $name = null): self
    {
        return new self(
            to: $email,
            subject: $this->subject,
            template: $this->template,
            category: $this->category,
            variables: $this->variables,
            toName: $name ?? $this->toName,
            replyTo: $this->replyTo,
            notifiableType: $this->notifiableType,
            notifiableId: $this->notifiableId,
            idempotencyKey: $this->idempotencyKey,
            scheduledAt: $this->scheduledAt,
        );
    }
}
