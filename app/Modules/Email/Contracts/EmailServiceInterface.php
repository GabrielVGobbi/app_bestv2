<?php

declare(strict_types=1);

namespace App\Modules\Email\Contracts;

use App\Modules\Email\DTOs\SendEmailDTO;
use App\Modules\Email\Models\EmailLog;

interface EmailServiceInterface
{
    /**
     * Dispatch the email as a queued job.
     * Returns null if the kill switch is active or the email is a duplicate.
     */
    public function queue(SendEmailDTO $dto): ?EmailLog;

    /**
     * Send the email synchronously (bypasses queue).
     * Returns null if the kill switch is active.
     */
    public function sendNow(SendEmailDTO $dto): ?EmailLog;
}
