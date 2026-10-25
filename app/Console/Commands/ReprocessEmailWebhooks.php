<?php

namespace App\Console\Commands;

use App\Modules\Email\Models\EmailWebhookEvent;
use App\Modules\Email\Services\EmailWebhookEventRecorder;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ReprocessEmailWebhooks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:webhooks:reprocess {uuid? : A trusted pending event UUID} {--limit=100 : Maximum number of pending events, 1 to 1000}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reprocess trusted pending Resend inbox events without calling providers or sending mail';

    /**
     * Execute the console command.
     */
    public function handle(EmailWebhookEventRecorder $recorder): int
    {
        $uuid = $this->argument('uuid');
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);
        if ($limit === false || ($uuid !== null && (! is_string($uuid) || ! Str::isUuid($uuid)))) {
            $this->error('Invalid event selection or limit.');

            return self::FAILURE;
        }

        $events = EmailWebhookEvent::query()->where('provider', 'resend')->whereNotNull('authenticated_at')->whereNull('processed_at')
            ->when($uuid !== null, fn ($query) => $query->where('uuid', $uuid))->orderBy('id')->limit($limit)->get();
        if ($uuid !== null && $events->isEmpty()) {
            $this->error('No trusted pending event matches the selection.');

            return self::FAILURE;
        }

        $completed = 0;
        foreach ($events as $event) {
            if ($recorder->process($event)) {
                $completed++;
            }
        }
        $this->info('Completed: '.$completed.'; still pending: '.($events->count() - $completed).'. No external requests or mail sent.');

        return self::SUCCESS;
    }
}
