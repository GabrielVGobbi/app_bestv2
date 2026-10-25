<?php

namespace App\Console\Commands;

use App\Jobs\ProcessExport;
use App\Models\Export;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('exports:recover')]
#[Description('Requeue abandoned export processing leases without replaying completed work')]
class RecoverExports extends Command
{
    public function handle(): int
    {
        $driver = config('queue.connections.'.config('queue.default').'.driver');
        if (! in_array($driver, ['database', 'redis', 'beanstalkd'], true)) {
            $this->error('Export recovery requires a durable queue connection.');

            return self::FAILURE;
        }
        $count = 0;
        $pendingTimeout = max(1020, (int) config('queue.connections.'.config('queue.default').'.retry_after', 1020));
        Export::query()->whereIn('status', [Export::STATUS_PENDING, Export::STATUS_PROCESSING])->chunkById(100, function ($exports) use (&$count, $pendingTimeout): void {
            foreach ($exports as $export) {
                $export->getConnection()->transaction(function () use ($export, &$count, $pendingTimeout): void {
                    $current = Export::query()->whereKey($export->id)->lockForUpdate()->first();
                    if ($current === null || ! in_array($current->status, [Export::STATUS_PENDING, Export::STATUS_PROCESSING], true)
                        || $current->hasActiveProcessingLease()
                        || ($current->status === Export::STATUS_PENDING && $current->updated_at?->copy()->addSeconds($pendingTimeout)->isFuture() === true)
                        || ($current->status === Export::STATUS_PROCESSING && $current->processing_token === null && $current->updated_at?->copy()->addSeconds(600)->isFuture() === true)) {
                        return;
                    }
                    if (! $current->update(['status' => Export::STATUS_PENDING, 'processing_expires_at' => null, 'dispatch_token' => (string) Str::uuid()])) {
                        throw new \RuntimeException('Export recovery transition was refused.');
                    }
                    $current->getConnection()->afterCommit(fn () => ProcessExport::dispatch($current));
                    $count++;
                });
            }
        });
        $this->info("Requeued {$count} abandoned export(s).");

        return self::SUCCESS;
    }
}
