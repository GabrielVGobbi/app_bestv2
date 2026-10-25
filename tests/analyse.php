<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

require dirname(__DIR__).'/vendor/autoload.php';

$analysis = new class
{
    private ?string $reports = null;

    private ?string $runtime = null;

    private bool $ownsReports = false;

    public function run(): int
    {
        $exit = 1;
        $result = ['schema' => 'START_KIT_ANALYSIS_RESULT_V1', 'analysis_invocation_attempted' => false, 'termination_preflight' => ['status' => 'not_run'], 'parent_application_bootstrapped' => false, 'parent_environment_modified' => false, 'global_php_configuration_modified' => false];
        try {
            $withoutBaseline = false;
            $reportOption = null;
            $seen = [];
            $arguments = $_SERVER['argv'];
            for ($index = 1; $index < count($arguments); $index++) {
                [$option, $value] = array_pad(explode('=', $arguments[$index], 2), 2, null);
                if (! in_array($option, ['--without-baseline', '--report-directory', '--help'], true) || isset($seen[$option])) {
                    throw new RuntimeException('Unknown, duplicate or positional argument. Use --help.');
                }
                $seen[$option] = true;
                if ($option === '--report-directory') {
                    $value ??= $arguments[++$index] ?? '';
                    if ($value === '' || str_starts_with($value, '--')) {
                        throw new RuntimeException('--report-directory requires a new directory path.');
                    }
                    $reportOption = $value;
                } elseif ($value !== null) {
                    throw new RuntimeException($option.' does not accept a value.');
                } elseif ($option === '--without-baseline') {
                    $withoutBaseline = true;
                }
            }
            if (isset($seen['--help'])) {
                fwrite(STDOUT, "php tests/analyse.php [--without-baseline] [--report-directory=NEW_DIRECTORY]\n");

                return 0;
            }
            if (PHP_SAPI !== 'cli' || PHP_OS_FAMILY !== 'Windows') {
                throw new RuntimeException('Analysis supports local Windows CLI PHP only.');
            }
            $project = $this->directory(dirname(__DIR__));
            $php = $this->regular(PHP_BINARY);
            $phpstan = $this->regular($project.'/vendor/phpstan/phpstan/phpstan');
            $configuration = $withoutBaseline ? 'phpstan-base.neon' : 'phpstan.neon';
            $sourceFiles = ['tests/analyse.php', 'tests/analysis-guard.php', 'tests/analysis-bootstrap.php', 'tests/analysis-migrations.php', 'tests/TestEnvironment.php', 'bootstrap/app.php', 'phpstan-safety.neon', 'phpstan-base.neon', 'phpstan.neon', 'tests/stubs/sanctum.stub', 'composer.lock'];
            if (! $withoutBaseline) {
                $sourceFiles[] = 'phpstan-baseline.neon';
            }
            $sourceHashes = [];
            foreach ($sourceFiles as $name) {
                $sourceHashes[$name] = hash_file('sha256', $this->regular($project.'/'.$name));
            }
            $temporary = $this->directory(sys_get_temp_dir());
            $token = bin2hex(random_bytes(16));
            $this->runtime = $temporary.'/skanalysis-'.substr($token, 0, 16);
            $reportOption ??= $temporary.'/skanalysis-reports-'.substr($token, 0, 16);
            $reportParent = $this->directory(dirname($reportOption));
            $leaf = basename($reportOption);
            if ($leaf === '' || $leaf === '.' || $leaf === '..') {
                throw new RuntimeException('Report directory must have a new leaf name.');
            }
            $this->reports = $reportParent.'/'.$leaf;
            foreach ([$this->runtime, $this->reports] as $path) {
                if (file_exists($path) || is_link($path)) {
                    throw new RuntimeException('Analysis runtime/report directories must be new; existing paths are never adopted.');
                }
            }
            if (! mkdir($this->runtime, 0700) || ! mkdir($this->reports, 0700)) {
                throw new RuntimeException('Cannot create owned analysis directories.');
            }
            foreach (['bootstrap', 'bootstrap/cache', 'cache', 'tmp', 'home', 'appdata', 'localappdata', 'php-scan'] as $directory) {
                if (! mkdir($this->runtime.'/'.$directory, 0700)) {
                    throw new RuntimeException('Cannot prepare private analysis paths.');
                }
            }
            $this->write($this->runtime.'/bootstrap/app.php', '<?php'.PHP_EOL.'return require '.var_export($project.'/tests/analysis-bootstrap.php', true).';'.PHP_EOL);
            $this->write($this->runtime.'/php-scan/99-analysis.ini', 'auto_prepend_file='.PHP_EOL.'auto_append_file='.PHP_EOL);
            $this->write($this->runtime.'/phpstan.neon', 'includes:'.PHP_EOL.'    - '.json_encode(str_replace('\\', '/', $project.'/'.$configuration), JSON_THROW_ON_ERROR).PHP_EOL.'parameters:'.PHP_EOL.'    tmpDir: '.json_encode(str_replace('\\', '/', $this->runtime.'/cache'), JSON_THROW_ON_ERROR).PHP_EOL);
            $runtimeHashes = [];
            foreach (['bootstrap/app.php', 'phpstan.neon', 'php-scan/99-analysis.ini'] as $name) {
                $runtimeHashes[$name] = hash_file('sha256', $this->regular($this->runtime.'/'.$name));
            }
            $owner = ['schema' => 'START_KIT_ANALYSIS_V1', 'token' => $token, 'project' => $project, 'runtime' => $this->runtime, 'reports' => $this->reports, 'temporary_parent' => $temporary, 'configuration' => $configuration, 'source_files' => $sourceHashes, 'runtime_files' => $runtimeHashes];
            $this->writeJson($this->runtime.'/owner.json', $owner);
            $this->writeJson($this->reports.'/owner.json', $owner);
            $this->ownsReports = true;

            $environment = [];
            foreach (array_unique([...array_keys(getenv()), ...array_keys($_ENV), ...array_keys($_SERVER)]) as $name) {
                $environment[(string) $name] = false;
            }
            foreach (['SystemRoot', 'windir', 'ComSpec', 'PATHEXT', 'OS', 'NUMBER_OF_PROCESSORS', 'PROCESSOR_ARCHITECTURE', 'ProgramFiles', 'ProgramFiles(x86)', 'ProgramW6432', 'PHPRC'] as $name) {
                $value = getenv($name);
                if ($value !== false) {
                    $environment[$name] = $value;
                }
            }
            $systemRoot = getenv('SystemRoot') ?: throw new RuntimeException('Windows SystemRoot is required.');
            $this->directory($systemRoot.'/System32');
            $scan = getenv('PHP_INI_SCAN_DIR');
            if ($scan === false || $scan === '') {
                $scanDirectories = defined('PHP_CONFIG_FILE_SCAN_DIR') && PHP_CONFIG_FILE_SCAN_DIR !== '' ? [PHP_CONFIG_FILE_SCAN_DIR] : [];
                foreach (preg_split('/,\s*/', php_ini_scanned_files() ?: '', -1, PREG_SPLIT_NO_EMPTY) ?: [] as $file) {
                    $scanDirectories[] = dirname(trim($file));
                }
                $scan = implode(PATH_SEPARATOR, array_unique($scanDirectories));
            }
            $environment = array_replace($environment, [
                'PATH' => implode(PATH_SEPARATOR, [dirname($php), $systemRoot.'/System32', $systemRoot]),
                'TEMP' => $this->runtime.'/tmp', 'TMP' => $this->runtime.'/tmp', 'TMPDIR' => $this->runtime.'/tmp',
                'HOME' => $this->runtime.'/home', 'USERPROFILE' => $this->runtime.'/home',
                'APPDATA' => $this->runtime.'/appdata', 'LOCALAPPDATA' => $this->runtime.'/localappdata',
                'PHP_INI_SCAN_DIR' => ($scan === '' ? '' : $scan.PATH_SEPARATOR).$this->runtime.'/php-scan',
                'START_KIT_ANALYSIS_ROOT' => $this->runtime, 'START_KIT_ANALYSIS_TOKEN' => $token,
                'APP_ENV' => 'testing', 'APP_NAME' => 'IsolatedAnalysis', 'APP_DEBUG' => 'false', 'APP_URL' => 'http://localhost',
                'APP_KEY' => 'base64:'.base64_encode(str_repeat('t', 32)), 'APP_MAINTENANCE_DRIVER' => 'file',
                'BCRYPT_ROUNDS' => '4', 'BROADCAST_CONNECTION' => 'null', 'CACHE_STORE' => 'array',
                'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => '', 'MAIL_MAILER' => 'array',
                'QUEUE_CONNECTION' => 'sync', 'SESSION_DRIVER' => 'array', 'LOG_CHANNEL' => 'null', 'FILESYSTEM_DISK' => 'local',
                'PULSE_ENABLED' => 'false', 'TELESCOPE_ENABLED' => 'false', 'NIGHTWATCH_ENABLED' => 'false',
                'DEBUGBAR_ENABLED' => 'false', 'INERTIA_SSR_ENABLED' => 'false', 'LARAVEL_CLOUD' => '0',
            ]);
            $this->terminationPreflight($php, $this->regular($systemRoot.'/System32/taskkill.exe', true), $project, $environment, $result);
            $command = [$php, $phpstan, 'analyse', '--configuration='.$this->runtime.'/phpstan.neon', '--debug', '--no-progress', '--no-ansi', '--error-format=json', '--memory-limit=1G'];
            $process = new Process($command, $this->runtime, $environment, null, 1800);
            $process->setOptions(['create_new_console' => false]);
            $stdout = fopen($this->reports.'/analysis.stdout.log', 'x');
            $stderr = fopen($this->reports.'/analysis.stderr.log', 'x');
            if ($stdout === false || $stderr === false) {
                throw new RuntimeException('Cannot create new analysis logs.');
            }
            $result['started_utc'] = gmdate('c');
            $result['configuration'] = $configuration;
            $result['php_binary'] = $php;
            $result['php_version'] = PHP_VERSION;
            $result['source_files'] = $sourceHashes;
            try {
                $result['analysis_invocation_attempted'] = true;
                $actual = $process->run(static function (string $type, string $contents) use ($stdout, $stderr): void {
                    if (fwrite($type === Process::OUT ? $stdout : $stderr, $contents) !== strlen($contents)) {
                        throw new RuntimeException('Incomplete analysis log write.');
                    }
                });
                $actual = $process->getExitCode();
                $result['analysis_exit_code'] = $actual;
                $exit = $actual === 0 ? 0 : 1;
            } finally {
                if ($process->isRunning()) {
                    $process->stop(1);
                }
                $result['analysis_exit_code'] ??= $process->getExitCode();
                $result['owned_process_remaining'] = $process->isRunning();
                fclose($stdout);
                fclose($stderr);
            }
            if ($exit === 0) {
                $receipts = [];
                foreach (['guard-passed.json', 'bootstrap-passed.json', 'cleanup.json'] as $name) {
                    $receipts[$name] = json_decode((string) file_get_contents($this->regular($this->runtime.'/'.$name)), true, 512, JSON_THROW_ON_ERROR);
                    if (($receipts[$name]['token'] ?? null) !== $token) {
                        throw new RuntimeException('Analysis receipt ownership differs: '.$name);
                    }
                }
                $normalize = static fn (string $path): string => strtolower(str_replace('\\', '/', $path));
                $guard = $receipts['guard-passed.json'];
                $bootstrap = $receipts['bootstrap-passed.json'];
                $cleanup = $receipts['cleanup.json'];
                $testRuntime = $cleanup['test_runtime'] ?? '';
                if (($guard['schema'] ?? null) !== 'ANALYSIS_GUARD_V1' || $normalize($guard['cwd'] ?? '') !== $normalize($this->runtime) || $normalize($guard['project'] ?? '') !== $normalize($project)) {
                    throw new RuntimeException('The safety-before-Laravel receipt does not match the owned invocation.');
                }
                if (! is_string($testRuntime) || ! preg_match('/\Astart-kit-tests-[a-f0-9]{32}\z/', basename($testRuntime)) || $normalize(dirname($testRuntime)) !== $normalize($this->runtime.'/tmp')) {
                    throw new RuntimeException('The PREP-001 cleanup receipt points outside the owned runtime.');
                }
                if (($bootstrap['environment'] ?? null) !== 'testing' || ($bootstrap['database'] ?? null) !== 'sqlite :memory:' || $normalize($bootstrap['environment_path'] ?? '') !== $normalize($testRuntime.'/environment') || $normalize($bootstrap['storage_path'] ?? '') !== $normalize($testRuntime.'/storage') || $normalize($bootstrap['bootstrap_path'] ?? '') !== $normalize($testRuntime.'/bootstrap')) {
                    throw new RuntimeException('Application bootstrap receipt does not match the PREP-001 environment/storage/cache paths.');
                }
                if (($cleanup['removed'] ?? null) !== true || ! array_key_exists('failure', $cleanup) || $cleanup['failure'] !== null || file_exists($testRuntime) || is_link($testRuntime) || $result['owned_process_remaining'] !== false) {
                    throw new RuntimeException('Analysis exited zero without verified cleanup and process termination.');
                }
                $result['isolation_receipts_verified'] = true;
            }
        } catch (Throwable $exception) {
            $exit = 1;
            $result['failure'] = $exception->getMessage();
            fwrite(STDERR, 'Analysis failed: '.$exception->getMessage().PHP_EOL);
        } finally {
            if ($this->ownsReports) {
                $result['exit_code'] = $exit;
                $result['finished_utc'] = gmdate('c');
                $result['runtime_retained'] = $this->runtime;
                $result['reports'] = $this->reports;
                $this->writeJson($this->reports.'/result.json', $result);
                fwrite(STDOUT, 'Analysis reports: '.$this->reports.PHP_EOL.'Owned runtime/cache retained: '.$this->runtime.PHP_EOL);
            }
        }

        return $exit;
    }

    /**
     * @param  array<string, string|false>  $environment
     * @param  array<string, mixed>  $result
     */
    private function terminationPreflight(string $php, string $taskkill, string $project, array $environment, array &$result): void
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
        $environment = array_filter($environment, static fn (string|false $value): bool => $value !== false);
        $probe = null;
        $killer = null;
        $probeStatus = null;
        $killerStatus = null;
        $killerExit = null;
        $terminated = false;
        $failure = null;
        $started = microtime(true);
        try {
            $probe = proc_open([$php, '-n', '-r', $code, $ready, $token], [0 => ['pipe', 'r'], 1 => $logs['termination-probe.stdout.log'], 2 => $logs['termination-probe.stderr.log']], $pipes, $project, $environment, ['bypass_shell' => true, 'create_new_console' => false]);
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
            $killer = proc_open([$taskkill, '/F', '/T', '/PID', (string) $probePid], [0 => ['pipe', 'r'], 1 => $logs['termination-taskkill.stdout.log'], 2 => $logs['termination-taskkill.stderr.log']], $killerPipes, $project, $environment, ['bypass_shell' => true, 'create_new_console' => false]);
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
                throw new RuntimeException('The current Windows token cannot reliably terminate its owned process tree (taskkill exit '.var_export($killerExit, true).'). Inspect termination-taskkill logs; analysis was refused before the analyzer or application bootstrap.');
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
                    $failure = 'The finite owned PHP probe exceeded its natural lifetime; analysis was refused.';
                }
            }
            foreach ($logs as $stream) {
                fclose($stream);
            }
            $result['owned_process_remaining'] = ($probeStatus['running'] ?? false) || ($killerStatus['running'] ?? false);
            $result['termination_preflight'] = ['name' => 'windows-tree-termination', 'executable' => $taskkill, 'probe_pid' => $probeStatus['pid'] ?? null, 'taskkill_pid' => $killerStatus['pid'] ?? null, 'exit_code' => $killerExit, 'probe_exit_code' => $probeStatus['exitcode'] ?? null, 'probe_terminated_by_taskkill' => $killerExit === 0 && $terminated, 'probe_remaining' => $probeStatus['running'] ?? false, 'taskkill_remaining' => $killerStatus['running'] ?? false, 'exception' => $failure, 'elapsed_seconds' => round(microtime(true) - $started, 3), 'stdout' => 'termination-taskkill.stdout.log', 'stderr' => 'termination-taskkill.stderr.log'];
        }
        if ($failure !== null) {
            throw new RuntimeException('Windows process-tree termination preflight refused execution: '.$failure);
        }
        fwrite(STDOUT, 'windows-tree-termination: exit 0'.PHP_EOL);
    }

    private function directory(string $path): string
    {
        $actual = realpath($path);
        if ($actual === false || ! is_dir($path)) {
            throw new RuntimeException('An existing directory is required: '.$path);
        }
        for ($parent = $path; dirname($parent) !== $parent; $parent = dirname($parent)) {
            if (is_link($parent)) {
                throw new RuntimeException('Linked analysis paths are not accepted: '.$path);
            }
        }

        return $actual;
    }

    private function regular(string $path, bool $allowHardLinks = false): string
    {
        $stat = lstat($path);
        if ($stat === false || ! is_file($path) || is_link($path) || (! $allowHardLinks && $stat['nlink'] !== 1)) {
            throw new RuntimeException('A single regular input is required: '.$path);
        }
        $this->directory(dirname($path));

        return realpath($path) ?: throw new RuntimeException('Cannot resolve input: '.$path);
    }

    private function write(string $path, string $contents): void
    {
        $stream = fopen($path, 'x');
        if ($stream === false) {
            throw new RuntimeException('Refusing to overwrite analysis output: '.$path);
        }
        try {
            if (fwrite($stream, $contents) !== strlen($contents)) {
                throw new RuntimeException('Incomplete analysis output write.');
            }
        } finally {
            fclose($stream);
        }
    }

    /** @param array<string, mixed> $contents */
    private function writeJson(string $path, array $contents): void
    {
        $this->write($path, json_encode($contents, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL);
    }
};

exit($analysis->run());
