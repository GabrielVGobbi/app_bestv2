<?php

use App\Modules\Email\Contracts\EmailServiceInterface;
use App\Modules\Email\DTOs\SendEmailDTO;
use App\Modules\Email\Enums\EmailCategory;
use App\Modules\Email\Jobs\ProcessEmailJob;
use App\Modules\Email\Mail\DynamicMailable;
use App\Modules\Email\Models\EmailLog;
use App\Modules\Email\Services\EmailProviderMessageIdResolver;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\DisposableMysql;
use Tests\TestEnvironment;

require dirname(__DIR__).'/vendor/autoload.php';

$environment = null;
$app = null;
$exit = 0;
try {
    TestEnvironment::assertSafeEnvironment();
    if (getenv('MAIL_DELIVERY_MYSQL_TESTS') !== '1' || getenv('AUTHORITY_MYSQL_TESTS') !== '1'
        || ! isset($argv[1], $argv[2]) || ! in_array($argv[2], ['0', '1'], true)) {
        throw new RuntimeException('Explicit owned delivery worker invocation required.');
    }
    $stat = lstat($argv[1]);
    if ($stat === false || is_link($argv[1]) || $stat['nlink'] !== 1 || $stat['size'] > 8192) {
        throw new RuntimeException('Invalid delivery manifest.');
    }
    $manifest = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
    $settings = $manifest['settings'];
    DisposableMysql::verifiedConnection($settings);
    if (realpath($argv[1]) !== realpath($settings['path'].'/workers.json')
        || ! in_array($manifest['scenario'] ?? null, ['create', 'retry', 'job'], true)
        || ($manifest['key'] ?? null) !== 'mail001-'.$manifest['scenario']) {
        throw new RuntimeException('Invalid finite delivery scenario.');
    }
    $environment = new TestEnvironment;
    file_put_contents($environment->path.'/authority-owner', $settings['token']);
    file_put_contents($settings['path'].'/barrier/environment-'.$argv[2], $environment->path);
    $app = DisposableMysql::bootApplication($settings, $environment);
    view()->addNamespace('mail-race', $settings['path']);
    $log = $manifest['scenario'] === 'job' ? EmailLog::findOrFail($manifest['log_id']) : null;
    if ($log !== null && $log->idempotency_key !== $manifest['key']) {
        throw new RuntimeException('Delivery escaped the owned fixture.');
    }
    $job = $log === null ? null : new ProcessEmailJob($log);
    $connectionId = (int) $app['db']->connection()->scalar('SELECT CONNECTION_ID()');
    $barrier = $settings['path'].'/barrier/'.$manifest['scenario'];
    file_put_contents($barrier.'-ready-'.$argv[2], (string) $connectionId, LOCK_EX);
    $deadline = microtime(true) + 25;
    while (! is_file($barrier.'-go')) {
        if (microtime(true) >= $deadline) {
            throw new RuntimeException('Owned delivery start timed out.');
        }
        usleep(50000);
    }
    if ($job !== null) {
        $job->handle($app->make(EmailProviderMessageIdResolver::class));
    } else {
        $app->make(EmailServiceInterface::class)->queue(new SendEmailDTO(
            to: 'concurrency@example.test', subject: 'Owned fixture', template: 'mail-race::delivery',
            category: EmailCategory::System, idempotencyKey: $manifest['key'],
        ));
    }
    file_put_contents($barrier.'-result-'.$argv[2], json_encode([
        'queued' => Queue::pushed(ProcessEmailJob::class)->count(),
        'sent' => Mail::sent(DynamicMailable::class)->count(),
    ], JSON_THROW_ON_ERROR), LOCK_EX);
} catch (Throwable $exception) {
    fwrite(STDERR, get_class($exception).' at '.basename($exception->getFile()).':'.$exception->getLine());
    $exit = 1;
} finally {
    try {
        $app?->make('db')->disconnect();
        $app?->flush();
    } finally {
        $environment?->cleanUp();
    }
}
exit($exit);
