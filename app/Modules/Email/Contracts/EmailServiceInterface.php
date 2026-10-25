<?php

declare(strict_types=1);

namespace App\Modules\Email\Contracts;

use App\Modules\Email\DTOs\SendEmailDTO;
use App\Modules\Email\Models\EmailLog;

interface EmailServiceInterface
{
    /**
     * Dispatch the email as a queued job after the outer transaction commits.
     * Returns null if the kill switch is active or the email is a duplicate.
     */
    public function queue(SendEmailDTO $dto): ?EmailLog;

    /**
     * Send without queueing, after the outer transaction commits.
     * Returns null if disabled, duplicate or unsafe to retry; may return queued until commit.
     */
    public function sendNow(SendEmailDTO $dto): ?EmailLog;
}
