<?php

declare(strict_types=1);

namespace App\Modules\Email\Actions;

use App\Modules\Email\Jobs\ProcessEmailJob;
use App\Modules\Email\Models\EmailLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResendEmailAction
{
    public function handle(EmailLog $log): EmailLog
    {
        return DB::transaction(function () use ($log): EmailLog {
            $queued = $log->queueAgain();

            if ($queued === null) {
                throw ValidationException::withMessages([
                    'log' => ['Only confirmed pre-delivery failures can be resent. Reconcile uncertain deliveries first.'],
                ]);
            }

            $job = new ProcessEmailJob($queued);
            DB::afterCommit(fn () => dispatch($job->afterCommit()));

            return $queued;
        });
    }
}
