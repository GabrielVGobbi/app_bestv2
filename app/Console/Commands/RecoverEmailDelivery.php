<?php

namespace App\Console\Commands;

use App\Modules\Email\Enums\EmailStatus;
use App\Modules\Email\Jobs\ProcessEmailJob;
use App\Modules\Email\Models\EmailLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RecoverEmailDelivery extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:recover {uuid} {token : Exact dispatch UUID, or legacy for NULL}
        {--outcome= : queued, sent, or not-sent}
        {--evidence= : Non-sensitive incident reference}
        {--message-id= : Confirmed provider ID for sent outcome}
        {--workers-stopped : Confirm all sending processes are stopped}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reconcile an isolated delivery generation after independent verification; never sends mail';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $uuid = $this->argument('uuid');
        $token = $this->argument('token');
        $outcome = $this->option('outcome');
        $evidence = $this->option('evidence');
        $messageId = $this->option('message-id');

        if (! $this->option('workers-stopped') || ! is_string($uuid) || ! Str::isUuid($uuid)
            || ! is_string($token) || ($token !== 'legacy' && ! Str::isUuid($token))
            || ! in_array($outcome, ['queued', 'sent', 'not-sent'], true)
            || ! is_string($evidence) || preg_match('/\A[A-Za-z0-9][A-Za-z0-9_.:-]{2,79}\z/', $evidence) !== 1
            || ($messageId !== null && (! is_string($messageId) || $messageId === '' || strlen($messageId) > 255 || preg_match('/[\x00-\x1f\x7f]/', $messageId)))
            || ($outcome !== 'sent' && $messageId !== null)) {
            $this->error('Invalid recovery request. Stop all senders, verify the provider independently and supply an incident reference.');

            return self::FAILURE;
        }

        $recovered = DB::transaction(function () use ($uuid, $token, $outcome, $evidence, $messageId): bool {
            $log = EmailLog::query()->where('uuid', $uuid)->where('dispatch_token', $token === 'legacy' ? null : $token)->lockForUpdate()->first();

            if ($log === null) {
                return false;
            }

            if ($outcome === 'queued') {
                $driver = config('queue.connections.'.config('queue.default').'.driver');

                if ($log->status !== EmailStatus::Queued || $token === 'legacy' || $log->sent_at !== null || $log->message_id !== null
                    || ! in_array($driver, ['database', 'redis', 'sqs', 'beanstalkd'], true)
                    || ($log->scheduled_at?->isFuture() && ! in_array($driver, ['database', 'redis', 'beanstalkd'], true))) {
                    return false;
                }
            } elseif ((! in_array($log->status, [EmailStatus::Processing, EmailStatus::Failed], true)
                    && ! ($log->status === EmailStatus::Queued && $token === 'legacy'))
                || ($log->status === EmailStatus::Failed && $log->retry_safe)
                || ($outcome === 'not-sent' && ($log->sent_at !== null || $log->message_id !== null))
                || ($outcome === 'sent' && $messageId !== null && $log->message_id !== null && $log->message_id !== $messageId)) {
                return false;
            }

            $log->dispatch_token ??= (string) Str::uuid();
            $metadata = $log->metadata ?? [];
            $metadata['delivery_recovery'] = ['outcome' => $outcome, 'reference' => $evidence, 'at' => now()->toIso8601String()];
            $log->metadata = $metadata;

            if ($outcome === 'sent') {
                $log->status = EmailStatus::Sent;
                $log->sent_at ??= now();
                $log->message_id ??= $messageId;
                $log->retry_safe = false;
                $log->error_message = null;
            } elseif ($outcome === 'not-sent') {
                $log->status = EmailStatus::Failed;
                $log->failed_at = now();
                $log->retry_safe = true;
                $log->error_message = 'Delivery independently confirmed not sent; explicit retry is available.';
            }

            $log->save();

            if ($outcome === 'queued') {
                $job = new ProcessEmailJob($log);
                DB::afterCommit(fn () => dispatch($job->afterCommit()));
            }

            return true;
        });

        if (! $recovered) {
            $this->error('Recovery refused: generation, state or provider evidence no longer matches.');

            return self::FAILURE;
        }

        $this->info('Recovery recorded. No mail was sent by this command.');

        return self::SUCCESS;
    }
}
