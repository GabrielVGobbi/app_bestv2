<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\HandleExceptions;
use Illuminate\Foundation\Bootstrap\RegisterProviders;
use Illuminate\Support\Facades\Facade;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PragmaRX\Google2FA\Google2FA;
use Symfony\Component\Process\Process;
use Tests\DisposableMysql;
use Tests\TestEnvironment;

class TwoFactorConcurrencyTest extends TestCase
{
    protected function tearDown(): void
    {
        try {
            HandleExceptions::flushState($this);
            RegisterProviders::flushState();
            Facade::clearResolvedInstances();
            Facade::setFacadeApplication(null);
        } finally {
            parent::tearDown();
        }
    }

    #[DataProvider('mfaRaces')]
    public function test_real_mysql_mfa_consumption_has_only_one_winner(string $scenario): void
    {
        if (getenv('AUTHORITY_MYSQL_TESTS') !== '1' || getenv('AUTHORITY_MFA_MYSQL_TESTS') !== '1') {
            $this->markTestSkipped('MFA concurrency requires both explicit disposable MySQL opt-ins.');
        }
        TestEnvironment::assertSafeEnvironment();
        $mysql = new DisposableMysql;
        $environment = null;
        $app = null;
        $connection = null;
        $workers = [];

        try {
            $mysql->start();
            $environment = new TestEnvironment;
            $app = DisposableMysql::bootApplication($mysql->settings(), $environment);
            $connection = $app['db']->connection();
            $this->assertSame('REPEATABLE-READ', $connection->scalar('SELECT @@transaction_isolation'));
            $this->assertSame(0, $app->make(Kernel::class)->call('migrate', ['--force' => true, '--no-interaction' => true]));
            $user = User::factory()->create(['email' => 'mfa@authority.example.test', 'password' => 'MfaFixture-123!', 'remember_token' => null]);
            $secret = (new Google2FA)->generateSecretKey();
            $user->forceFill([
                'two_factor_secret' => Fortify::currentEncrypter()->encrypt($secret),
                'two_factor_confirmed_at' => now(),
                'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(json_encode(['abcde12345-fghij67890', 'klmno12345-pqrst67890'], JSON_THROW_ON_ERROR)),
            ])->save();
            $beforeUser = $user->refresh()->getAttributes();
            unset($beforeUser['two_factor_recovery_codes'], $beforeUser['updated_at']);
            $cache = $this->configureOwnedCache($app, $mysql->settings()['token']);
            $code = $scenario === 'recovery-mixed' ? 'abcde12345-fghij67890' : (new Google2FA)->getCurrentOtp($secret);
            $operations = $scenario === 'recovery-mixed' ? ['mfa-recovery-web', 'mfa-recovery-api'] : ['mfa-totp-provider', 'mfa-totp-provider'];
            $manifest = $mysql->path.'/workers.json';
            $stream = fopen($manifest, 'xb');
            $this->assertIsResource($stream);
            try {
                $bytes = json_encode([
                    'settings' => $mysql->settings(),
                    'mfa' => ['scenario' => $scenario, 'user_id' => $user->getKey(), 'code' => $code],
                    'operations' => array_map(fn (string $type): array => ['type' => $type, 'actor' => $user->getKey(), 'target' => $user->getKey()], $operations),
                ], JSON_THROW_ON_ERROR);
                $this->assertSame(strlen($bytes), fwrite($stream, $bytes));
                $this->assertTrue(fflush($stream));
            } finally {
                fclose($stream);
            }
            $dataKey = 'fortify.2fa_codes.'.md5($code);
            if ($scenario === 'totp-provider') {
                $this->assertTrue($cache->put($dataKey, 0, 120));
            }
            $connection->beginTransaction();
            if ($scenario === 'recovery-mixed') {
                $this->assertNotNull($connection->table('users')->where('id', $user->getKey())->lockForUpdate()->first());
            } else {
                $this->assertNotNull($connection->table('cache')->where('key', 'sec013-'.$mysql->settings()['token'].'-'.$dataKey)->lockForUpdate()->first());
            }
            foreach ([0, 1] as $index) {
                $process = new Process([PHP_BINARY, dirname(__DIR__, 2).'/authority-worker.php', $manifest, (string) $index], dirname(__DIR__, 3), [
                    'AUTHORITY_MYSQL_TESTS' => '1', 'AUTHORITY_MFA_MYSQL_TESTS' => '1',
                    'APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => '',
                    'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array',
                    'BROADCAST_CONNECTION' => 'null', 'SLACK_ENABLED' => 'false', 'TELESCOPE_ENABLED' => 'false', 'PULSE_ENABLED' => 'false', 'DEBUGBAR_ENABLED' => 'false',
                    'FILESYSTEM_DISK' => 'local', 'APP_CLOUD_ENABLED' => 'false',
                ]);
                $process->setTimeout(45);
                $process->start();
                $workers[] = $process;
            }
            $this->waitForMfa(fn (): bool => is_file($mysql->path.'/barrier/ready-0') && is_file($mysql->path.'/barrier/ready-1'), $workers);
            $ids = [(int) file_get_contents($mysql->path.'/barrier/ready-0'), (int) file_get_contents($mysql->path.'/barrier/ready-1')];
            $this->assertGreaterThan(0, $ids[0]);
            $this->assertGreaterThan(0, $ids[1]);
            $this->assertNotSame($ids[0], $ids[1]);
            $this->assertSame(2, file_put_contents($mysql->path.'/barrier/go', 'go'));
            $this->waitForMfa(function () use ($mysql, $ids, $scenario): bool {
                $waiting = $mysql->waitingConnections($ids);
                if ($scenario === 'recovery-mixed' || $waiting === 2) {
                    return $waiting === 2;
                }
                foreach ([0, 1] as $index) {
                    $path = $mysql->path.'/barrier/result-'.$index.'.json';
                    if (is_file($path) && ! is_link($path)) {
                        $result = json_decode((string) file_get_contents($path), true);
                        if ($waiting === 1 && is_array($result) && ($result['result'] ?? null) === 'refused') {
                            return true;
                        }
                    }
                }

                return false;
            }, $workers);
            $connection->commit();
            foreach ($workers as $worker) {
                $worker->wait();
                $this->assertSame(0, $worker->getExitCode(), $worker->getErrorOutput());
                $this->assertSame('', $worker->getErrorOutput());
            }
            $results = [];
            foreach ([0, 1] as $index) {
                $path = $mysql->path.'/barrier/result-'.$index.'.json';
                $stat = lstat($path);
                $this->assertIsArray($stat);
                $this->assertSame(0100000, $stat['mode'] & 0170000);
                $this->assertSame(1, $stat['nlink']);
                $this->assertLessThan(1024, $stat['size']);
                $results[$index] = json_decode((string) file_get_contents($path), true, 16, JSON_THROW_ON_ERROR);
            }
            $this->assertEqualsCanonicalizing(['refused', 'success'], array_column($results, 'result'));
            if ($scenario === 'recovery-mixed') {
                $this->assertSame('web', $results[0]['channel']);
                $this->assertSame('api', $results[1]['channel']);
                $this->assertSame($results[0]['result'] === 'success', $results[0]['authenticated']);
                $this->assertFalse($results[1]['authenticated']);
                $this->assertSame($results[1]['result'] === 'success' ? 1 : 0, $user->tokens()->count());
                $remaining = $user->refresh()->recoveryCodes();
                $this->assertCount(2, $remaining);
                $this->assertNotContains($code, $remaining);
                $this->assertContains('klmno12345-pqrst67890', $remaining);
            } else {
                $this->assertSame(['provider', 'provider'], array_column($results, 'channel'));
                $this->assertSame(0, $user->tokens()->count());
                $this->assertIsInt($cache->get($dataKey));
                $this->assertGreaterThan(0, $cache->get($dataKey));
                $this->assertFalse($app->make(TwoFactorAuthenticationProvider::class)->verify($secret, $code));
            }
            $afterUser = $user->refresh()->getAttributes();
            unset($afterUser['two_factor_recovery_codes'], $afterUser['updated_at']);
            $this->assertSame($beforeUser, $afterUser);
            $this->assertSame(0, (int) $connection->table('cache_locks')->count(), 'Every acquired shared MFA lock must be released.');
        } finally {
            try {
                foreach ($workers as $worker) {
                    if ($worker->isRunning()) {
                        $worker->stop(2);
                    }
                    $this->assertFalse($worker->isRunning());
                }
                if ($connection?->transactionLevel() > 0) {
                    $connection->rollBack();
                }
                $app?->make('db')->disconnect();
                $app?->flush();
            } finally {
                try {
                    $environment?->cleanUp();
                } finally {
                    $mysql->cleanUp();
                }
            }
        }
        $this->assertDirectoryDoesNotExist($mysql->path);
        $this->assertDirectoryDoesNotExist($environment->path);
    }

    /** @return array<string, array{string}> */
    public static function mfaRaces(): array
    {
        return ['recovery web versus API' => ['recovery-mixed'], 'shared TOTP provider' => ['totp-provider']];
    }

    private function configureOwnedCache(Application $app, string $token): Repository
    {
        $app['config']->set([
            'cache.default' => 'mfa', 'cache.prefix' => 'sec013-'.$token.'-',
            'cache.stores.mfa' => ['driver' => 'database', 'connection' => 'authority_sandbox', 'table' => 'cache', 'lock_connection' => 'authority_sandbox', 'lock_table' => 'cache_locks'],
        ]);
        $cache = $app->make('cache')->store('mfa');
        $app->instance('cache.store', $cache);
        $app->forgetInstance(TwoFactorAuthenticationProvider::class);

        return $cache;
    }

    /** @param list<Process> $workers */
    private function waitForMfa(callable $condition, array $workers): void
    {
        $deadline = microtime(true) + 20;
        while (! $condition()) {
            foreach ($workers as $worker) {
                $worker->checkTimeout();
                if (! $worker->isRunning() && $worker->getExitCode() !== 0) {
                    $this->fail('Owned MFA worker failed: '.$worker->getErrorOutput());
                }
            }
            if (microtime(true) >= $deadline) {
                $this->fail('The owned MFA workers did not reach their bounded real-lock barrier.');
            }
            usleep(20000);
        }

        $this->assertTrue($condition());
    }
}
