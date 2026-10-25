<?php

use App\Models\User;
use App\Modules\Notifications\Actions\CreateNotificationAction;
use App\Modules\Notifications\DTOs\CreateNotificationDTO;
use Illuminate\Contracts\Console\Kernel;
use Tests\AuthorityHttpEnvironment;
use Tests\TestEnvironment;

require dirname(__DIR__).'/vendor/autoload.php';
require __DIR__.'/optional-runtime-bootstrap.php';

$runtime = null;
try {
    AuthorityHttpEnvironment::configureGlobals(true);
    optionalRuntimeAssertCopy();
    if (count($argv) < 4 || ! in_array($argv[1], ['seed', 'reverb', 'publish'], true)) {
        throw new RuntimeException('A finite optional runtime operation is required.');
    }
    if ($argv[1] === 'seed') {
        $manifest = AuthorityHttpEnvironment::readManifest($argv[2]);
        $environment = new TestEnvironment;
        $registry = AuthorityHttpEnvironment::registerEnvironment($manifest, $environment);
        $app = AuthorityHttpEnvironment::bootApplication($manifest, $environment);
        $runtime = compact('app', 'environment', 'registry');
        if ($app->make(Kernel::class)->call('migrate', ['--force' => true, '--no-interaction' => true]) !== 0) {
            throw new RuntimeException('Disposable migration failed.');
        }
        $actors = [];
        foreach (['A', 'B'] as $label) {
            $user = User::factory()->create(['name' => 'OPS004 Account '.$label, 'email' => 'ops004-'.strtolower($label).'@example.test',
                'password' => 'Ops004-Fixture-123!', 'email_verified_at' => now(), 'must_change_password' => false]);
            $actors[] = ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'password' => 'Ops004-Fixture-123!'];
        }
        echo json_encode($actors, JSON_THROW_ON_ERROR)."\n";

        return;
    }
    $runtime = optionalRuntimeBoot($argv[2], $argv[3]);
    if ($argv[1] === 'reverb') {
        if (! $runtime['state']['realtime']) {
            throw new RuntimeException('Reverb is disabled in this mode.');
        }
        exit($runtime['app']->make(Kernel::class)->call('reverb:start', [
            '--host' => '127.0.0.1', '--port' => $runtime['state']['reverb_port'], '--no-interaction' => true,
        ]));
    }
    if (count($argv) !== 6 || ! ctype_digit($argv[4]) || ! preg_match('/\A[a-f0-9]{16}\z/', $argv[5])
        || ! in_array((int) $argv[4], array_column($runtime['state']['actors'], 'id'), true)) {
        throw new RuntimeException('Only a literal fixture actor may receive the synthetic notification.');
    }
    $notification = $runtime['app']->make(CreateNotificationAction::class)->handle(CreateNotificationDTO::system(
        recipientId: (int) $argv[4], title: 'OPS004 '.$argv[5], body: 'Owned realtime runtime proof.',
    ));
    echo json_encode(['uuid' => $notification->uuid, 'title' => $notification->title], JSON_THROW_ON_ERROR)."\n";
} finally {
    if ($runtime !== null) {
        $runtime['app']->make('db')->disconnect();
        $runtime['app']->flush();
        $runtime['environment']->cleanUp();
        if (is_file($runtime['registry'])) {
            unlink($runtime['registry']);
        }
    }
}
