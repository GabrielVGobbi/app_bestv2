<?php

declare(strict_types=1);

namespace App\Modules\Email\Actions;

use App\Modules\Email\Enums\EmailStatus;
use App\Modules\Email\Jobs\ProcessEmailJob;
use App\Modules\Email\Models\EmailLog;
use Illuminate\Validation\ValidationException;

class ResendEmailAction
{
    public function handle(EmailLog $log): EmailLog
    {
        if ($log->status !== EmailStatus::Failed) {
            throw ValidationException::withMessages([
                'log' => ['Only failed emails can be resent.'],
            ]);
        }

        $log->update([
            'status' => EmailStatus::Queued,
            'error_message' => null,
            'failed_at' => null,
        ]);

        ProcessEmailJob::dispatch($log);

        return $log->fresh();
    }
}
