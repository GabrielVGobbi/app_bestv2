<?php

use App\Http\Controllers\Api\NotificationApiController;
use App\Http\Requests\ExecuteNotificationActionRequest;
use App\Models\User;
use App\Modules\Notifications\Models\AppNotification;
use App\Modules\Notifications\Services\NotificationActionResolver;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\DisposableMysql;
use Tests\Feature\NotificationActionProbe;
use Tests\Feature\NotificationActionProbeAction;
use Tests\TestEnvironment;

require dirname(__DIR__).'/vendor/autoload.php';
require __DIR__.'/Feature/NotificationApiTest.php';

$environment = null;
$app = null;
$exitCode = 0;

try {
    if (getenv('AUTHORITY_MYSQL_TESTS') !== '1' || getenv('AUTHORITY_NOTIFICATION_MYSQL_TESTS') !== '1'
        || ! isset($argv[1], $argv[2]) || ! in_array($argv[2], ['0', '1'], true)) {
        throw new RuntimeException('Explicit disposable notification concurrency invocation required.');
    }
    TestEnvironment::assertSafeEnvironment();
    $stat = lstat($argv[1]);
    if ($stat === false || is_link($argv[1]) || ($stat['mode'] & 0170000) !== 0100000 || $stat['nlink'] !== 1 || $stat['size'] > 16384) {
        throw new RuntimeException('Invalid owned notification worker manifest.');
    }
    $manifest = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
    if (! is_array($manifest) || count($manifest) !== 5 || ! isset($manifest['settings'], $manifest['scenario'], $manifest['actor_id'], $manifest['notification_id'], $manifest['probe_id'])
        || ! is_array($manifest['settings']) || ! in_array($manifest['scenario'], ['success', 'rollback-first'], true)) {
        throw new RuntimeException('Invalid finite notification concurrency profile.');
    }
    foreach (['actor_id', 'notification_id', 'probe_id'] as $field) {
        if (! is_int($manifest[$field]) || $manifest[$field] < 1) {
            throw new RuntimeException('Invalid synthetic notification fixture identifier.');
        }
    }
    $settings = $manifest['settings'];
    DisposableMysql::verifiedConnection($settings);
    if (realpath($argv[1]) !== realpath($settings['path'].'/workers.json') || ! is_dir($settings['path'].'/barrier')
        || is_link($settings['path'].'/barrier') || realpath(dirname($settings['path'].'/barrier')) !== realpath($settings['path'])) {
        throw new RuntimeException('Notification concurrency barrier escaped its owned directory.');
    }
    $worker = (int) $argv[2];
    $environment = new TestEnvironment;
    file_put_contents($environment->path.'/authority-owner', $settings['token']);
    file_put_contents($settings['path'].'/barrier/environment-'.$worker, $environment->path);
    $app = DisposableMysql::bootApplication($settings, $environment);
    Notification::fake();
    $actor = User::query()->findOrFail($manifest['actor_id']);
    $stale = AppNotification::query()->findOrFail($manifest['notification_id']);
    $stalePending = $stale->action_status === 'pending';
    if ($actor->email !== 'ntf002@authority.example.test' || $stale->recipient_id !== $actor->id
        || $stale->notifiable_type !== NotificationActionProbe::class || $stale->notifiable_id !== $manifest['probe_id']
        || ! $stalePending || ! $stale->hasActionOfType('probe_action')) {
        throw new RuntimeException('Notification worker escaped its fixed synthetic fixture.');
    }
    Gate::define('execute-notification-probe', fn (User $user, NotificationActionProbe $probe): bool => $user->id === $probe->recipient_id);
    $app->make(NotificationActionResolver::class)->register('probe_action', NotificationActionProbeAction::class);
    $request = ExecuteNotificationActionRequest::create('/api/v1/notifications/'.$stale->uuid.'/execute-action', 'POST', [
        'action_type' => 'probe_action',
        'payload' => ['fail_after_mutation' => $manifest['scenario'] === 'rollback-first' && $worker === 0],
    ], [], [], ['HTTP_ACCEPT' => 'application/json']);
    $request->setContainer($app)->setRedirector($app->make('redirect'));
    $request->setUserResolver(fn (): User => $actor);
    $request->validateResolved();
    $connectionId = (int) $app['db']->connection()->scalar('SELECT CONNECTION_ID()');
    $ready = $settings['path'].'/barrier/ready-'.$worker;
    $temporary = $ready.'.tmp';
    $stream = fopen($temporary, 'xb');
    if ($stream === false) {
        throw new RuntimeException('The notification readiness signal must be new.');
    }
    try {
        $bytes = (string) $connectionId;
        if ($connectionId < 1 || fwrite($stream, $bytes) !== strlen($bytes) || ! fflush($stream)) {
            throw new RuntimeException('Notification readiness signal was incomplete.');
        }
    } finally {
        fclose($stream);
    }
    if (file_exists($ready) || is_link($ready) || ! rename($temporary, $ready)) {
        throw new RuntimeException('Notification readiness signal could not be published.');
    }
    $deadline = microtime(true) + 30;
    while (! is_file($settings['path'].'/barrier/go-'.$worker)) {
        if (microtime(true) >= $deadline) {
            throw new RuntimeException('Notification action start barrier timed out.');
        }
        usleep(10000);
    }
    try {
        $response = $app->make(NotificationApiController::class)->executeAction($request, $stale);
        $status = $response->getStatusCode();
        $result = $response->getData(true);
    } catch (HttpExceptionInterface $exception) {
        $status = $exception->getStatusCode();
        $result = 'refused';
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() !== 'NTF002 controlled handler failure') {
            throw $exception;
        }
        $status = 500;
        $result = 'controlled-handler-failure';
    }
    Mail::assertNothingOutgoing();
    Queue::assertNothingPushed();
    Notification::assertNothingSent();
    Http::assertNothingSent();
    $stream = fopen($settings['path'].'/barrier/result-'.$worker.'.json', 'xb');
    if ($stream === false) {
        throw new RuntimeException('The owned notification worker result must be new.');
    }
    try {
        $bytes = json_encode(['status' => $status, 'result' => $result, 'stale_pending' => $stalePending, 'integrations_isolated' => true], JSON_THROW_ON_ERROR);
        if (fwrite($stream, $bytes) !== strlen($bytes) || ! fflush($stream)) {
            throw new RuntimeException('The notification worker result was incomplete.');
        }
    } finally {
        fclose($stream);
    }
} catch (Throwable $exception) {
    fwrite(STDERR, 'Notification concurrency worker failed: '.$exception::class."\n");
    $exitCode = 1;
} finally {
    try {
        $app?->make('db')->disconnect();
        $app?->flush();
    } finally {
        $environment?->cleanUp();
    }
}

exit($exitCode);
