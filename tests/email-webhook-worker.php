<?php

use App\Modules\Email\Mail\DynamicMailable;
use App\Modules\Email\Services\EmailWebhookService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\DisposableMysql;
use Tests\Fixtures\Email\SvixMessageFixture;
use Tests\TestEnvironment;

require dirname(__DIR__).'/vendor/autoload.php';
$app = null;
$environment = null;
$exit = 0;
try {
    TestEnvironment::assertSafeEnvironment();
    if (getenv('MAIL_WEBHOOK_MYSQL_TESTS') !== '1' || getenv('AUTHORITY_MYSQL_TESTS') !== '1' || ! isset($argv[1], $argv[2]) || ! in_array($argv[2], ['0', '1'], true)) {
        throw new RuntimeException('Explicit owned webhook worker required.');
    }
    $stat = lstat($argv[1]);
    if ($stat === false || is_link($argv[1]) || $stat['nlink'] !== 1 || $stat['size'] > 8192) {
        throw new RuntimeException('Invalid finite manifest.');
    }
    $manifest = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
    $settings = $manifest['settings'];
    DisposableMysql::verifiedConnection($settings);
    if (realpath($argv[1]) !== realpath($settings['path'].'/workers.json') || ! in_array($manifest['scenario'] ?? null, ['duplicate', 'out-of-order'], true)
        || ($manifest['message_id'] ?? null) !== 'mail002-'.$manifest['scenario']) {
        throw new RuntimeException('Unowned webhook scenario.');
    }
    $environment = new TestEnvironment;
    file_put_contents($environment->path.'/authority-owner', $settings['token']);
    file_put_contents($settings['path'].'/barrier/environment-'.$argv[2], $environment->path);
    $app = DisposableMysql::bootApplication($settings, $environment);
    config(['services.resend.webhook_secret' => SvixMessageFixture::secret()]);
    $projectionWrites = 0;
    DB::listen(function ($query) use (&$projectionWrites): void {
        if (str_starts_with($query->sql, 'update') && str_contains($query->sql, 'email_logs') && (in_array('delivered', $query->bindings, true) || in_array('failed', $query->bindings, true))) {
            $projectionWrites++;
        }
    });
    $newer = $manifest['scenario'] === 'out-of-order' && $argv[2] === '1';
    $payload = ['type' => $newer ? 'email.failed' : 'email.delivered', 'created_at' => $newer ? '2026-10-17T00:00:00.654321Z' : '2026-10-17T00:00:00.123456Z', 'data' => ['email_id' => $manifest['message_id']]];
    $id = 'evt-mail002-'.$manifest['scenario'].($manifest['scenario'] === 'duplicate' ? '' : '-'.$argv[2]);
    $raw = SvixMessageFixture::raw($payload);
    $request = Request::create('/', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], $raw);
    foreach (SvixMessageFixture::headers($raw, $id, (string) now()->timestamp) as $key => $value) {
        $request->headers->set($key, $value);
    }
    $barrier = $settings['path'].'/barrier/'.$manifest['scenario'];
    file_put_contents($barrier.'-ready-'.$argv[2], (string) $app['db']->connection()->scalar('SELECT CONNECTION_ID()'), LOCK_EX);
    $deadline = microtime(true) + 25;
    while (! is_file($barrier.'-go')) {
        if (microtime(true) >= $deadline) {
            throw new RuntimeException('Owned start timeout.');
        } usleep(50000);
    }
    app(EmailWebhookService::class)->handle('resend', $request);
    file_put_contents($barrier.'-result-'.$argv[2], json_encode(['projection_writes' => $projectionWrites, 'sent' => Mail::sent(DynamicMailable::class)->count(), 'queued' => count(Queue::pushedJobs())], JSON_THROW_ON_ERROR), LOCK_EX);
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
