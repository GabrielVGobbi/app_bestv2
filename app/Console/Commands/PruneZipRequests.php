<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Uploads\Models\ZipRequest;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

#[Signature('uploads:prune-zips')]
#[Description('Expire abandoned ZIP requests and clean their private artifacts')]
class PruneZipRequests extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $failures = 0;

        ZipRequest::query()->chunkById(100, function (Collection $requests) use (&$failures): void {
            foreach ($requests as $request) {
                if (! $request->expireIfNeeded()) {
                    $failures++;
                }
            }
        });

        if ($failures > 0) {
            $this->error("ZIP cleanup left {$failures} request(s) requiring attention.");

            return self::FAILURE;
        }

        $this->info('ZIP request expiration and cleanup completed.');

        return self::SUCCESS;
    }
}
