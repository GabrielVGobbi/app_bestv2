<?php

declare(strict_types=1);

namespace App\Modules\Email\Actions;

use App\Modules\Email\Contracts\EmailServiceInterface;
use App\Modules\Email\DTOs\SendEmailDTO;
use App\Modules\Email\Models\EmailLog;

class SendEmailAction
{
    public function __construct(private readonly EmailServiceInterface $emailService) {}

    public function handle(SendEmailDTO $dto): ?EmailLog
    {
        return $this->emailService->queue($dto);
    }
}
