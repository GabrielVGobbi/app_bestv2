<?php

declare(strict_types=1);

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

require dirname(__DIR__).'/vendor/autoload.php';

$runner = new class
{
    private string $root;

    private ?string $runtime = null;

    private ?string $reports = null;

    private bool $ownsReports = false;

    /** @var list<array<string, mixed>> */
    private array $receipts = [];

    /** @var list<string> */
    private array $failures = [];

    /** @var array<string, string|false> */
    private array $environment = [];

    public function __construct()
    {
        $this->root = dirname(__DIR__);
    }

    public function run(): int
    {
        $profile = 'common';
        $exit = 1;

        try {
            $options = [];
            $arguments = $_SERVER['argv'];
            for ($index = 1; $index < count($arguments); $index++) {
                [$option, $value] = array_pad(explode('=', $arguments[$index], 2), 2, null);
                if (! in_array($option, ['--profile', '--http-host', '--mysqld-binary', '--report-directory', '--help'], true)) {
                    throw new RuntimeException('Unknown option or positional argument. Use --help.');
                }
                $name = substr($option, 2);
                if (array_key_exists($name, $options)) {
                    throw new RuntimeException('Each option may be supplied only once: '.$option);
                }
                if ($name === 'help') {
                    if ($value !== null) {
                        throw new RuntimeException('--help does not accept a value.');
                    }
                    $options[$name] = true;

                    continue;
                }
                if ($value === null) {
                    $value = $arguments[++$index] ?? '';
                }
                if ($value === '' || str_starts_with($value, '--')) {
                    throw new RuntimeException('A nonempty value is required for '.$option);
                }
                $options[$name] = $value;
            }
            if (isset($options['help'])) {
                fwrite(STDOUT, "php tests/ci.php --profile=common|native|all --http-host=custom.loopback.test [--mysqld-binary=C:/mysql/bin/mysqld.exe] [--report-directory=NEW_DIRECTORY]\n");

                return 0;
            }
            $profile = $options['profile'] ?? 'common';
            if (! in_array($profile, ['common', 'native', 'all'], true)) {
                throw new RuntimeException('Profile must be common, native or all.');
            }
            if (PHP_SAPI !== 'cli' || PHP_OS_FAMILY !== 'Windows') {
                throw new RuntimeException('This entry point requires local Windows CLI PHP.');
            }
            $host = strtolower($options['http-host'] ?? (getenv('AUTHORITY_HTTP_HOST') ?: ''));
            if (! preg_match('/\A[a-z0-9_]+(?:[.-][a-z0-9_]+)*\z/', $host) || ! str_contains($host, '.') || filter_var($host, FILTER_VALIDATE_IP)) {
                throw new RuntimeException('Set --http-host or AUTHORITY_HTTP_HOST to an existing custom loopback hostname, without a scheme, port or path.');
            }
            $php = $this->regular(PHP_BINARY);
            $nodeCandidate = (new ExecutableFinder)->find('node');
            if ($nodeCandidate === null) {
                throw new RuntimeException('Install Node and make its real executable available on PATH.');
            }
            $nodeCandidate = realpath($nodeCandidate) ?: throw new RuntimeException('Cannot resolve the installed Node executable.');
            $mysqld = null;
            if ($profile !== 'common') {
                $mysqlOption = $options['mysqld-binary'] ?? (getenv('AUTHORITY_MYSQLD_BINARY') ?: '');
                if ($mysqlOption === '') {
                    throw new RuntimeException('Native requires --mysqld-binary or AUTHORITY_MYSQLD_BINARY pointing to installed Oracle MySQL mysqld.exe.');
                }
                $mysqld = $this->regular($mysqlOption);
            }
            $this->regular($this->root.'/vendor/bin/phpunit');
            $this->regular($this->root.'/tests/bootstrap.php');
            $token = bin2hex(random_bytes(16));
            $temporary = $this->directory(sys_get_temp_dir());
            $this->runtime = $temporary.'/skci-'.substr($token, 0, 16);
            $reportOption = $options['report-directory'] ?? $temporary.'/skci-reports-'.substr($token, 0, 16);
            $reportParent = $this->directory(dirname($reportOption));
            $reportLeaf = basename($reportOption);
            if ($reportLeaf === '' || $reportLeaf === '.' || $reportLeaf === '..') {
                throw new RuntimeException('Report directory must have a new directory name.');
            }
            $this->reports = $reportParent.'/'.$reportLeaf;
            if (file_exists($this->reports) || is_link($this->reports) || file_exists($this->runtime) || is_link($this->runtime)) {
                throw new RuntimeException('Runtime and report directories must be new; existing paths are never adopted.');
            }
            if (! mkdir($this->runtime, 0700) || ! mkdir($this->reports, 0700)) {
                throw new RuntimeException('Cannot create new owned runtime/report directories.');
            }
            $owner = ['schema' => 'START_KIT_LOCAL_CI_V1', 'token' => $token, 'runtime' => $this->runtime, 'reports' => $this->reports, 'profile' => $profile];
            $this->writeJson($this->runtime.'/owner.json', $owner);
            $this->writeJson($this->reports.'/owner.json', $owner);
            $this->ownsReports = true;
            foreach (['tmp', 'home', 'appdata', 'localappdata', 'php-scan'] as $directory) {
                if (! mkdir($this->runtime.'/'.$directory, 0700)) {
                    throw new RuntimeException('Cannot prepare an owned runtime directory.');
                }
            }
            $inherited = getenv();
            foreach (array_unique([...array_keys($inherited), ...array_keys($_ENV), ...array_keys($_SERVER)]) as $key) {
                $this->environment[(string) $key] = false;
            }
            foreach (['SystemRoot', 'windir', 'ComSpec', 'PATHEXT', 'NUMBER_OF_PROCESSORS', 'PROCESSOR_ARCHITECTURE', 'OS', 'ProgramFiles', 'ProgramFiles(x86)', 'ProgramW6432', 'PHPRC', 'PHP_INI_SCAN_DIR'] as $key) {
                $value = getenv($key);
                if ($value !== false) {
                    $this->environment[$key] = $value;
                }
            }
            $systemRoot = getenv('SystemRoot') ?: throw new RuntimeException('A valid Windows SystemRoot is required.');
            $this->directory($systemRoot.'/System32');
            $this->environment = array_replace($this->environment, [
                'PATH' => implode(PATH_SEPARATOR, [dirname($php), dirname($nodeCandidate), $systemRoot.'/System32', $systemRoot]),
                'TEMP' => $this->runtime.'/tmp', 'TMP' => $this->runtime.'/tmp', 'TMPDIR' => $this->runtime.'/tmp',
                'HOME' => $this->runtime.'/home', 'USERPROFILE' => $this->runtime.'/home',
                'APPDATA' => $this->runtime.'/appdata', 'LOCALAPPDATA' => $this->runtime.'/localappdata',
                'APP_ENV' => 'testing', 'APP_NAME' => 'LocalCI', 'APP_DEBUG' => 'false', 'APP_URL' => 'http://'.$host,
                'APP_KEY' => 'base64:'.base64_encode(str_repeat('t', 32)), 'APP_MAINTENANCE_DRIVER' => 'file',
                'BCRYPT_ROUNDS' => '4', 'BROADCAST_CONNECTION' => 'null', 'CACHE_STORE' => 'array',
                'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => '', 'MAIL_MAILER' => 'array',
                'QUEUE_CONNECTION' => 'sync', 'SESSION_DRIVER' => 'array', 'LOG_CHANNEL' => 'null', 'FILESYSTEM_DISK' => 'local',
                'LARAVEL_CLOUD' => '0', 'PULSE_ENABLED' => 'false', 'TELESCOPE_ENABLED' => 'false',
                'NIGHTWATCH_ENABLED' => 'false', 'DEBUGBAR_ENABLED' => 'false', 'INERTIA_SSR_ENABLED' => 'false',
                'HTTP_HOST' => $host, 'AUTHORITY_HTTP_HOST' => $host, 'STABILIZATION_SNS_NATIVE_TRANSPORT' => '1',
            ]);

            $this->terminationPreflight($php, $this->regular($systemRoot.'/System32/taskkill.exe', true));
            $nodeProbe = $this->process([$nodeCandidate, '--input-type=module', '-e', "import fs from 'node:fs'; process.stdout.write(JSON.stringify({version:process.version,binary:fs.realpathSync.native(process.execPath)}));"], 'node-preflight', 60);
            $this->successful($nodeProbe, 'Node preflight');
            $nodeRuntime = json_decode($nodeProbe['stdout'], true, 512, JSON_THROW_ON_ERROR);
            $node = $this->regular($nodeRuntime['binary']);
            foreach (['AXIOS', 'DEV_BUILD', 'EDITOR', 'SOCKET', 'SEC020', 'UI005'] as $wrapper) {
                $this->environment[$wrapper.'_NODE_TESTS'] = '1';
                $this->environment[$wrapper.'_NODE_BINARY'] = $node;
            }
            $this->environment['PATH'] = implode(PATH_SEPARATOR, [dirname($php), dirname($node), $systemRoot.'/System32', $systemRoot]);
            $probeCode = <<<'PHP'
$extensions = [];
foreach (['sockets', 'curl', 'pdo_sqlite', 'pdo_mysql'] as $extension) { $extensions[$extension] = extension_loaded($extension); }
echo json_encode(['version'=>PHP_VERSION, 'binary'=>PHP_BINARY, 'extensions'=>$extensions, 'ini'=>php_ini_loaded_file(), 'scanned'=>php_ini_scanned_files(), 'default_scan_dir'=>defined('PHP_CONFIG_FILE_SCAN_DIR') ? PHP_CONFIG_FILE_SCAN_DIR : '', 'auto_prepend_file'=>ini_get('auto_prepend_file'), 'auto_append_file'=>ini_get('auto_append_file')], JSON_THROW_ON_ERROR);
PHP;
            $initial = $this->process([$php, '-d', 'auto_prepend_file=', '-d', 'auto_append_file=', '-r', $probeCode], 'php-preflight-initial', 60);
            $this->successful($initial, 'Initial PHP preflight');
            $phpRuntime = json_decode($initial['stdout'], true, 512, JSON_THROW_ON_ERROR);
            $scan = $this->runtime.'/php-scan';
            $overlay = 'auto_prepend_file='.PHP_EOL.'auto_append_file='.PHP_EOL;
            if (! $phpRuntime['extensions']['sockets']) {
                $dll = $this->regular(dirname($php).'/ext/php_sockets.dll');
                $overlay .= 'extension="'.str_replace('\\', '/', $dll).'"'.PHP_EOL;
            }
            $this->write($scan.'/99-local-ci-overlay.ini', $overlay);
            $existing = $this->environment['PHP_INI_SCAN_DIR'] ?? '';
            if ($existing === false || $existing === '') {
                $directories = $phpRuntime['default_scan_dir'] === '' ? [] : [$phpRuntime['default_scan_dir']];
                foreach (preg_split('/,\s*/', $phpRuntime['scanned'] ?: '', -1, PREG_SPLIT_NO_EMPTY) as $ini) {
                    $directories[] = dirname(trim($ini));
                }
                $existing = implode(PATH_SEPARATOR, array_unique($directories));
            }
            $this->environment['PHP_INI_SCAN_DIR'] = $existing === '' ? $scan : $existing.PATH_SEPARATOR.$scan;
            $final = $this->process([$php, '-r', $probeCode], 'php-preflight-final', 60);
            $this->successful($final, 'Final PHP preflight');
            $phpRuntime = json_decode($final['stdout'], true, 512, JSON_THROW_ON_ERROR);
            if ($phpRuntime['auto_prepend_file'] !== '' || $phpRuntime['auto_append_file'] !== '') {
                throw new RuntimeException('The owned PHP overlay must disable auto_prepend_file and auto_append_file for child and descendant PHP processes.');
            }
            foreach ($profile === 'common' ? ['sockets', 'curl', 'pdo_sqlite'] : ['sockets', 'curl', 'pdo_sqlite', 'pdo_mysql'] as $extension) {
                if (! $phpRuntime['extensions'][$extension]) {
                    throw new RuntimeException('Required PHP extension is missing: '.$extension.'. No global php.ini was changed.');
                }
            }
            if ($mysqld !== null) {
                $mysql = $this->process([$mysqld, '--version'], 'mysql-version', 60);
                $this->successful($mysql, 'Oracle MySQL version preflight');
                if (! str_contains($mysql['stdout'], 'MySQL') || stripos($mysql['stdout'], 'MariaDB') !== false) {
                    throw new RuntimeException('Native requires Oracle MySQL; inspect mysql-version logs.');
                }
                $this->environment['AUTHORITY_MYSQLD_BINARY'] = $mysqld;
            }
            $guardCode = <<<'PHP'
require 'tests/bootstrap.php';
Tests\TestEnvironment::assertSafeEnvironment();
Tests\TestEnvironment::assertSafeDatabase(new Illuminate\Config\Repository(['app'=>['env'=>'testing'], 'database'=>['default'=>'sqlite', 'connections'=>['sqlite'=>['driver'=>'sqlite', 'database'=>':memory:', 'url'=>'']]]]));
Tests\AuthorityHttpEnvironment::assertLoopbackHost(getenv('AUTHORITY_HTTP_HOST'));
echo "Environment/database guards and every resolved loopback address accepted; no application bootstrap or database connection.\n";
PHP;
            $guard = $this->process([$php, '-d', 'auto_prepend_file=', '-d', 'auto_append_file=', '-r', $guardCode], 'database-host-guards', 60);
            $this->successful($guard, 'Environment/database/loopback guards');

            if ($profile !== 'native') {
                $this->suite($php, 'common', [], null, null, 1800);
            }
            if ($profile !== 'common') {
                $uploadsUiBuild = $this->process([
                    $node,
                    $this->regular($this->root.'/node_modules/vite/bin/vite.js'),
                    'build', '--config', 'tests/Fixtures/Frontend/ui-uploads-http-vite.ts',
                ], 'uploads-ui-build', 180);
                $this->successful($uploadsUiBuild, 'Isolated uploads UI fixture build');
                $timelineUiBuild = $this->process([
                    $node,
                    $this->regular($this->root.'/node_modules/vite/bin/vite.js'),
                    'build', '--config', 'tests/Fixtures/Frontend/ui-timeline-http-vite.ts',
                ], 'timeline-ui-build', 180);
                $this->successful($timelineUiBuild, 'Isolated timeline UI fixture build');
                $selectionUiBuild = $this->process([
                    $node,
                    $this->regular($this->root.'/node_modules/vite/bin/vite.js'),
                    'build', '--config', 'tests/Fixtures/Frontend/ui-selection-http-vite.ts',
                ], 'selection-ui-build', 180);
                $this->successful($selectionUiBuild, 'Isolated selection UI fixture build');
                $profiles = [
                    ['authority', 'AuthorityConcurrencyTest|AuthorityMysqlIdentityTest', [], 3],
                    ['users-bulk-revocation', 'UserBulkDeleteNativeTest', ['UI004_NATIVE_TESTS'], 2],
                    ['mfa', 'TwoFactorConcurrencyTest', ['AUTHORITY_MFA_MYSQL_TESTS'], 2],
                    ['mail-delivery', 'EmailDeliveryConcurrencyTest', ['MAIL_DELIVERY_MYSQL_TESTS'], 1],
                    ['mail-webhook', 'EmailWebhookConcurrencyTest', ['MAIL_WEBHOOK_MYSQL_TESTS'], 1],
                    ['notification-actions', 'NotificationActionConcurrencyTest', ['AUTHORITY_NOTIFICATION_MYSQL_TESTS'], 2],
                    ['seeder', 'SeederGeneratorMysqlTest', ['SEEDER_GENERATOR_MYSQL_TESTS'], 4],
                    ['verification', 'test_verification_http_profile', ['AUTHORITY_HTTP_TESTS', 'AUTHORITY_HTTP_VERIFICATION_TESTS'], 1],
                    ['password', 'test_password_http_profile', ['AUTHORITY_HTTP_TESTS', 'AUTHORITY_HTTP_PASSWORD_TESTS'], 1],
                    ['integrations', 'test_integration_settings_http', ['AUTHORITY_HTTP_TESTS', 'AUTHORITY_HTTP_INTEGRATIONS_TESTS'], 1],
                    ['activity', 'test_activity_readonly_http', ['AUTHORITY_HTTP_TESTS', 'AUTHORITY_HTTP_ACTIVITY_TESTS'], 1],
                    ['users', 'test_users_readonly_http', ['AUTHORITY_HTTP_TESTS', 'AUTHORITY_HTTP_USERS_TESTS'], 1],
                    ['authority-http', 'test_real_http_sessions_enforce', ['AUTHORITY_HTTP_TESTS'], 1],
                    ['uploads-transport', 'test_uploads_transport_http', ['AUTHORITY_HTTP_TESTS', 'AUTHORITY_HTTP_UPLOADS_TESTS', 'AUTHORITY_HTTP_UPLOADS_TRANSPORT_TESTS'], 1],
                    ['uploads-deduplication', 'UploadDeduplicationNativeTest', ['UPL001_NATIVE_TESTS'], 2],
                    ['uploads-ui', 'test_uploads_ui_http_profile_paginates_private_sources_and_downloads_the_real_matching_archive', ['AUTHORITY_HTTP_TESTS', 'AUTHORITY_HTTP_UPLOADS_UI_TESTS'], 1],
                    ['pulse', 'test_pulse_http_dashboard', ['AUTHORITY_HTTP_TESTS', 'AUTHORITY_HTTP_PULSE_TESTS'], 1],
                    ['devtools', 'test_devtools_http_settings', ['AUTHORITY_HTTP_TESTS', 'AUTHORITY_HTTP_DEVTOOLS_TESTS'], 1],
                    ['timeline', 'test_timeline_http_reads', ['AUTHORITY_HTTP_TESTS', 'AUTHORITY_HTTP_TIMELINE_TESTS'], 1],
                    ['timeline-ui', 'test_timeline_ui_http_profile_reads_all_mixed_roots_through_authenticated_cursor_pages', ['AUTHORITY_HTTP_TESTS', 'AUTHORITY_HTTP_TIMELINE_UI_TESTS'], 1],
                    ['selection-ui', 'test_selection_ui_http_profile_resolves_full_multipage_sets_and_commits_only_exact_owned_bulk_deletions', ['AUTHORITY_HTTP_TESTS', 'AUTHORITY_HTTP_SELECTION_UI_TESTS'], 1],
                    ['uploads', 'test_uploads_http_preview', ['AUTHORITY_HTTP_TESTS', 'AUTHORITY_HTTP_UPLOADS_TESTS'], 1],
                    ['editor', 'test_editor_http_scope', ['AUTHORITY_HTTP_TESTS', 'AUTHORITY_HTTP_EDITOR_TESTS'], 1],
                ];
                foreach ($profiles as [$name, $filter, $flags, $expected]) {
                    $this->suite($php, 'native-'.$name, array_fill_keys(['AUTHORITY_MYSQL_TESTS', ...$flags], '1'), $filter, $expected, 900);
                }
            }
            $exit = $this->failures === [] ? 0 : 1;
        } catch (Throwable $exception) {
            $this->failures[] = $exception->getMessage();
        } finally {
            if ($this->ownsReports && $this->reports !== null && is_file($this->reports.'/owner.json')) {
                $this->writeJson($this->reports.'/result.json', [
                    'schema' => 'START_KIT_LOCAL_CI_RESULT_V1', 'profile' => $profile, 'exit_code' => $exit,
                    'finished_utc' => gmdate('c'), 'reports' => $this->reports, 'runtime_retained' => $this->runtime,
                    'parent_environment_modified' => false, 'global_php_configuration_modified' => false,
                    'processes' => $this->receipts, 'failures' => $this->failures,
                ]);
                fwrite(STDOUT, 'Reports: '.$this->reports.PHP_EOL.'Owned runtime retained for audit: '.$this->runtime.PHP_EOL);
            }
        }
        foreach ($this->failures as $failure) {
            fwrite(STDERR, 'FAILED: '.$failure.PHP_EOL);
        }

        return $exit;
    }

    private function terminationPreflight(string $php, string $taskkill): void
    {
        $token = bin2hex(random_bytes(16));
        $ready = $this->runtime.'/termination-'.$token.'.json';
        $code = <<<'PHP'
$temporary = $argv[1].'.tmp';
$handle = fopen($temporary, 'x');
if ($handle === false) { exit(91); }
$json = json_encode(['token'=>$argv[2], 'pid'=>getmypid()], JSON_THROW_ON_ERROR);
if (fwrite($handle, $json) !== strlen($json)) { fclose($handle); exit(92); }
fclose($handle);
if (file_exists($argv[1]) || ! rename($temporary, $argv[1])) { exit(93); }
usleep(5000000);
PHP;
        $logs = [];
        foreach (['termination-probe.stdout.log', 'termination-probe.stderr.log', 'termination-taskkill.stdout.log', 'termination-taskkill.stderr.log'] as $name) {
            $logs[$name] = fopen($this->reports.'/'.$name, 'x');
            if ($logs[$name] === false) {
                throw new RuntimeException('Cannot create owned termination preflight logs.');
            }
        }
        $environment = array_filter($this->environment, static fn (string|false $value): bool => $value !== false);
        $probe = null;
        $killer = null;
        $probeStatus = null;
        $killerStatus = null;
        $killerExit = null;
        $terminated = false;
        $failure = null;
        $started = microtime(true);
        try {
            $probe = proc_open([$php, '-n', '-r', $code, $ready, $token], [0 => ['pipe', 'r'], 1 => $logs['termination-probe.stdout.log'], 2 => $logs['termination-probe.stderr.log']], $pipes, $this->root, $environment, ['bypass_shell' => true, 'create_new_console' => false]);
            if (! is_resource($probe)) {
                throw new RuntimeException('Cannot launch the finite owned PHP termination probe.');
            }
            fclose($pipes[0]);
            $probeStatus = proc_get_status($probe);
            $probePid = $probeStatus['pid'];
            $readyDeadline = microtime(true) + 2;
            while (! is_file($ready) && $probeStatus['running'] && microtime(true) < $readyDeadline) {
                usleep(10000);
                $probeStatus = proc_get_status($probe);
            }
            if (! is_file($ready)) {
                throw new RuntimeException('The owned finite PHP probe did not publish readiness.');
            }
            $this->regular($ready);
            if (filesize($ready) > 512) {
                throw new RuntimeException('Invalid owned termination readiness size.');
            }
            $readiness = json_decode(file_get_contents($ready), true, 8, JSON_THROW_ON_ERROR);
            if ($readiness !== ['token' => $token, 'pid' => $probePid] || ! $probeStatus['running']) {
                throw new RuntimeException('Owned probe token/PID does not match its process handle.');
            }
            $killer = proc_open([$taskkill, '/F', '/T', '/PID', (string) $probePid], [0 => ['pipe', 'r'], 1 => $logs['termination-taskkill.stdout.log'], 2 => $logs['termination-taskkill.stderr.log']], $killerPipes, $this->root, $environment, ['bypass_shell' => true, 'create_new_console' => false]);
            if (! is_resource($killer)) {
                throw new RuntimeException('Cannot launch taskkill for the owned probe PID.');
            }
            fclose($killerPipes[0]);
            $killerStatus = proc_get_status($killer);
            $killDeadline = microtime(true) + 2;
            while ($killerStatus['running'] && microtime(true) < $killDeadline) {
                usleep(10000);
                $killerStatus = proc_get_status($killer);
            }
            if ($killerStatus['running']) {
                proc_terminate($killer);
                throw new RuntimeException('Taskkill exceeded the owned preflight deadline.');
            }
            $killerExit = $killerStatus['exitcode'];
            $closed = proc_close($killer);
            $killer = null;
            if ($killerExit < 0) {
                $killerExit = $closed;
            }
            $terminatedDeadline = microtime(true) + 0.5;
            do {
                $probeStatus = proc_get_status($probe);
                if (! $probeStatus['running']) {
                    break;
                }
                usleep(10000);
            } while (microtime(true) < $terminatedDeadline);
            $terminated = ! $probeStatus['running'];
            if ($killerExit !== 0 || ! $terminated) {
                throw new RuntimeException('The current Windows token cannot reliably terminate its owned process tree (taskkill exit '.var_export($killerExit, true).'). Inspect termination-taskkill logs; tests were refused before database guards or suites.');
            }
        } catch (Throwable $exception) {
            $failure = $exception->getMessage();
        } finally {
            if (is_resource($killer)) {
                $killerStatus = proc_get_status($killer);
                if ($killerStatus['running']) {
                    proc_terminate($killer);
                    $deadline = microtime(true) + 1;
                    do {
                        usleep(10000);
                        $killerStatus = proc_get_status($killer);
                    } while ($killerStatus['running'] && microtime(true) < $deadline);
                }
                if (! $killerStatus['running']) {
                    $killerExit = $killerStatus['exitcode'];
                    $closed = proc_close($killer);
                    $killer = null;
                    if ($killerExit < 0) {
                        $killerExit = $closed;
                    }
                }
            }
            if (is_resource($probe)) {
                $naturalDeadline = $started + 6;
                do {
                    $probeStatus = proc_get_status($probe);
                    if (! $probeStatus['running']) {
                        break;
                    }
                    usleep(10000);
                } while (microtime(true) < $naturalDeadline);
                if (! $probeStatus['running']) {
                    proc_close($probe);
                    $probe = null;
                } else {
                    $failure = 'The finite owned PHP probe exceeded its natural lifetime; tests were refused.';
                }
            }
            foreach ($logs as $stream) {
                fclose($stream);
            }
            $this->receipts[] = ['name' => 'windows-tree-termination', 'executable' => $taskkill, 'probe_pid' => $probeStatus['pid'] ?? null, 'taskkill_pid' => $killerStatus['pid'] ?? null, 'exit_code' => $killerExit, 'probe_exit_code' => $probeStatus['exitcode'] ?? null, 'probe_terminated_by_taskkill' => $killerExit === 0 && $terminated, 'probe_remaining' => $probeStatus['running'] ?? false, 'taskkill_remaining' => $killerStatus['running'] ?? false, 'exception' => $failure, 'elapsed_seconds' => round(microtime(true) - $started, 3), 'stdout' => 'termination-taskkill.stdout.log', 'stderr' => 'termination-taskkill.stderr.log'];
        }
        if ($failure !== null) {
            throw new RuntimeException('Windows process-tree termination preflight refused execution: '.$failure);
        }
        fwrite(STDOUT, 'windows-tree-termination: exit 0'.PHP_EOL);
    }

    /** @param array<string, string> $flags */
    private function suite(string $php, string $name, array $flags, ?string $filter, ?int $expected, int $timeout): void
    {
        try {
            $report = $this->reports.'/'.$name.'.xml';
            $arguments = [$php, '-d', 'auto_prepend_file=', '-d', 'auto_append_file=', 'vendor/bin/phpunit', '--configuration', 'phpunit.xml', '--colors=never', '--do-not-cache-result', '--fail-on-risky', '--display-deprecations', '--display-phpunit-deprecations', '--display-notices', '--display-warnings', '--log-junit', $report];
            if ($filter !== null) {
                array_push($arguments, '--filter', $filter, '--fail-on-skipped', '--fail-on-empty-test-suite');
            }
            $result = $this->process($arguments, $name, $timeout, $flags);
            if ($result['exit_code'] !== 0 || $result['exception'] !== null) {
                $this->failures[] = $name.' failed; exit '.var_export($result['exit_code'], true).($result['exception'] === null ? '' : '; '.$result['exception']);
            }
            $this->regular($report);
            $xml = simplexml_load_file($report, SimpleXMLElement::class, LIBXML_NONET);
            if ($xml === false || $xml->getName() !== 'testsuites') {
                throw new RuntimeException('Invalid JUnit document.');
            }
            $cases = $xml->xpath('//testcase');
            $failures = $xml->xpath('//failure|//error');
            $skipped = $xml->xpath('//skipped');
            if ($cases === false || count($cases) === 0 || $failures === false || $failures !== []) {
                throw new RuntimeException('JUnit contains failures/errors or no test cases; inspect the preserved diagnostics.');
            }
            if ($expected !== null && (count($cases) !== $expected || $skipped === false || $skipped !== [])) {
                throw new RuntimeException('Mandatory native JUnit count/skip contract failed; expected '.$expected.' cases.');
            }
            if ($filter !== null) {
                $assertions = 0;
                foreach ($cases as $case) {
                    $assertions += (int) $case['assertions'];
                    if (preg_match('~'.$filter.'~', (string) $case['class'].'::'.(string) $case['name']) !== 1) {
                        throw new RuntimeException('Unexpected test in mandatory native JUnit.');
                    }
                }
                if ($assertions <= 0) {
                    throw new RuntimeException('Mandatory native JUnit must contain positive assertions.');
                }
            }
        } catch (Throwable $exception) {
            $this->failures[] = $name.': '.$exception->getMessage();
        }
    }

    /**
     * @param  list<string>  $arguments
     * @param  array<string, string>  $flags
     * @return array{exit_code: ?int, stdout: string, exception: ?string}
     */
    private function process(array $arguments, string $name, int $timeout, array $flags = []): array
    {
        $out = fopen($this->reports.'/'.$name.'.stdout.log', 'x');
        $err = fopen($this->reports.'/'.$name.'.stderr.log', 'x');
        if ($out === false || $err === false) {
            throw new RuntimeException('Cannot create new process logs.');
        }
        $process = new Process($arguments, $this->root, array_replace($this->environment, $flags), null, $timeout);
        $process->setOptions(['create_new_console' => false]);
        $exception = null;
        $exit = null;
        $started = gmdate('c');
        try {
            $exit = $process->run(static function (string $type, string $bytes) use ($out, $err): void {
                $stream = $type === Process::OUT ? $out : $err;
                if (fwrite($stream, $bytes) !== strlen($bytes)) {
                    throw new RuntimeException('Incomplete process log write.');
                }
            });
            $exit = $process->getExitCode();
        } catch (Throwable $failure) {
            $exception = $failure->getMessage();
        } finally {
            if ($process->isRunning()) {
                $process->stop(1);
            }
            $exit ??= $process->getExitCode();
            fclose($out);
            fclose($err);
            $this->receipts[] = ['name' => $name, 'executable' => $arguments[0], 'started_utc' => $started, 'finished_utc' => gmdate('c'), 'exit_code' => $exit, 'exception' => $exception, 'stdout' => $name.'.stdout.log', 'stderr' => $name.'.stderr.log', 'owned_process_remaining' => $process->isRunning()];
        }
        fwrite(STDOUT, $name.': exit '.var_export($exit, true).PHP_EOL);

        return ['exit_code' => $exit, 'stdout' => $process->getOutput(), 'exception' => $exception];
    }

    /** @param array{exit_code: ?int, stdout: string, exception: ?string} $result */
    private function successful(array $result, string $name): void
    {
        if ($result['exit_code'] !== 0 || $result['exception'] !== null) {
            throw new RuntimeException($name.' failed; inspect its stdout/stderr logs. '.($result['exception'] ?? ''));
        }
    }

    private function regular(string $path, bool $allowHardLinks = false): string
    {
        if (! preg_match('~\A(?:[A-Za-z]:/|//)~', str_replace('\\', '/', $path))) {
            throw new RuntimeException('An absolute file path is required: '.$path);
        }
        $stat = lstat($path);
        if ($stat === false || ! is_file($path) || is_link($path) || (! $allowHardLinks && $stat['nlink'] !== 1)) {
            throw new RuntimeException('A single regular file is required: '.$path);
        }
        $this->directory(dirname($path));

        return realpath($path) ?: throw new RuntimeException('Cannot resolve file: '.$path);
    }

    private function directory(string $path): string
    {
        $actual = realpath($path);
        if ($actual === false || ! is_dir($path)) {
            throw new RuntimeException('An existing directory is required: '.$path);
        }
        for ($parent = $path; ; $parent = dirname($parent)) {
            if (is_link($parent)) {
                throw new RuntimeException('Linked runtime/executable paths are not accepted: '.$path);
            }
            if (dirname($parent) === $parent) {
                break;
            }
        }

        return $actual;
    }

    private function write(string $path, string $contents): void
    {
        $stream = fopen($path, 'x');
        if ($stream === false) {
            throw new RuntimeException('Refusing to overwrite an output file: '.$path);
        }
        try {
            if (fwrite($stream, $contents) !== strlen($contents)) {
                throw new RuntimeException('Incomplete output write: '.$path);
            }
        } finally {
            fclose($stream);
        }
    }

    /** @param array<string, mixed> $value */
    private function writeJson(string $path, array $value): void
    {
        $this->write($path, json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL);
    }
};

exit($runner->run());
