<?php

namespace Tests\Feature;

use DOMDocument;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use stdClass;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\AuthorityHttpEnvironment;
use Tests\DisposableMysql;
use Throwable;

class DevToolsHttpIntegrationTest extends TestCase
{
    public function test_uploads_transport_http_uses_real_multipart_and_preserves_old_fixtures_through_errors_and_recovery(): void
    {
        if (! AuthorityHttpEnvironment::uploadsTransportEnabled()) {
            $this->markTestSkipped('Uploads transport requires its exclusive HTTP, MySQL, Uploads and transport opt-ins.');
        }
        $host = AuthorityHttpEnvironment::requiredHost();
        $before = $this->directories();
        $input = new InputStream;
        $process = new Process([PHP_BINARY, dirname(__DIR__).'/authority-http-server.php'], dirname(__DIR__, 2), [
            'AUTHORITY_HTTP_TESTS' => '1', 'AUTHORITY_MYSQL_TESTS' => '1', 'AUTHORITY_HTTP_UPLOADS_TESTS' => '1', 'AUTHORITY_HTTP_UPLOADS_TRANSPORT_TESTS' => '1',
            'AUTHORITY_HTTP_EDITOR_TESTS' => '0', 'AUTHORITY_HTTP_PULSE_TESTS' => '0', 'AUTHORITY_HTTP_TIMELINE_TESTS' => '0', 'AUTHORITY_HTTP_DEVTOOLS_TESTS' => '0',
            'AUTHORITY_HTTP_VERIFICATION_TESTS' => '0', 'AUTHORITY_HTTP_HOST' => $host, 'APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => '',
        ]);
        $process->setInput($input);
        $process->setTimeout(240);
        $buffer = '';
        $started = false;
        try {
            $process->start();
            $started = true;
            $ready = $this->message($process, $buffer, 'url', 180);
            $this->assertSame('uploads', $ready['mode']);
            $origin = $ready['url'];
            $this->assertOrigin($origin);
            $root = $this->ownedRoot($before, 'uploads');
            $manifest = AuthorityHttpEnvironment::readManifest($root.'/http.json');
            $fixture = $ready['uploads_transport'];
            $this->assertSame($fixture, $manifest['uploads_transport']);
            $this->assertNotSame($ready['uploads']['client_id'], $fixture['client_id']);
            $this->assertNotSame($ready['uploads']['client_uuid'], $fixture['client_uuid']);
            $pdo = DisposableMysql::verifiedConnection($manifest['settings']);
            $oldRows = $pdo->query('SELECT * FROM uploadeds ORDER BY id')->fetchAll(\PDO::FETCH_ASSOC);
            $this->assertCount(4, $oldRows);
            $oldFiles = [];
            foreach ($ready['uploads']['files'] as $file) {
                $oldFiles[$file['path']] = file_get_contents($root.'/http-upload-files/'.$file['path']);
                $this->assertIsString($oldFiles[$file['path']]);
            }
            $png = file_get_contents(__DIR__.'/../Fixtures/upload-preview.png');
            $pdf = file_get_contents(__DIR__.'/../Fixtures/upload-preview.pdf');
            $this->assertIsString($png);
            $this->assertIsString($pdf);
            $this->assertSame('a24ee14097e9e0e191f75a1df7450dbcf5cab9466a1fab5f5a8d068f0e8216ae', hash('sha256', $png));
            $this->assertSame('605f64a75a8b224606c223ed7487ecd100a67662fa22fd9fcf9e5957b8d77bcd', hash('sha256', $pdf));
            $parts = $this->transportMultipart($fixture, 'Café transporte.png', $png);
            $guestJar = new CookieJar;
            $guest = $this->client($origin, $guestJar);
            $this->assertSame(401, $this->request($guest, $origin, 'GET', '/api/v1/clients')->getStatusCode());
            $this->assertSame(200, $this->request($guest, $origin, 'GET', '/login', ['headers' => ['Accept' => 'text/html']])->getStatusCode());
            $this->assertSame(401, $this->request($guest, $origin, 'POST', '/api/v1/uploads', ['headers' => $this->csrf($guestJar), 'multipart' => $parts])->getStatusCode());
            $this->assertCount(0, $this->transportRows($root, $oldRows, $oldFiles));

            $jar = new CookieJar;
            $developer = $this->client($origin, $jar);
            $this->login($developer, $jar, $origin, $ready['fixtures']['developer']);
            $this->assertSame('app/clients/index', $this->page($this->request($developer, $origin, 'GET', '/clients', ['headers' => ['Accept' => 'text/html']]))['component']);
            $list = $this->request($developer, $origin, 'GET', '/api/v1/clients', ['query' => ['page' => '1', 'per_page' => '30', 'search' => 'DEP001 Transport']]);
            $this->assertSame(200, $list->getStatusCode());
            $clients = json_decode((string) $list->getBody(), true, 512, JSON_THROW_ON_ERROR);
            $this->assertCount(1, $clients['data']);
            $this->assertSame($fixture['client_uuid'], $clients['data'][0]['uuid']);
            $this->assertSame(1, $clients['meta']['current_page']);
            $this->assertSame(1, $clients['meta']['total']);
            $ordinaryJar = new CookieJar;
            $ordinary = $this->client($origin, $ordinaryJar);
            $this->login($ordinary, $ordinaryJar, $origin, $ready['fixtures']['ordinary']);
            $this->assertSame(200, $this->request($ordinary, $origin, 'GET', '/api/v1/clients', ['query' => ['page' => '1', 'per_page' => '15']])->getStatusCode());
            $this->assertSame(403, $this->request($ordinary, $origin, 'POST', '/api/v1/uploads', ['headers' => $this->csrf($ordinaryJar), 'multipart' => $parts])->getStatusCode(), 'Only the synthetic developer may write this bounded test fixture.');
            $this->assertCount(0, $this->transportRows($root, $oldRows, $oldFiles));

            $this->assertSame(419, $this->request($developer, $origin, 'POST', '/api/v1/uploads', ['multipart' => $parts])->getStatusCode());
            $missing = $this->request($developer, $origin, 'POST', '/api/v1/uploads', [
                'headers' => $this->csrf($jar), 'form_params' => ['parentable_type' => 'client', 'parentable_id' => (string) $fixture['client_id']],
            ]);
            $this->assertSame(422, $missing->getStatusCode());
            $this->assertArrayHasKey('files', json_decode((string) $missing->getBody(), true, 512, JSON_THROW_ON_ERROR)['errors']);
            $this->assertCount(0, $this->transportRows($root, $oldRows, $oldFiles));

            $invalidParts = [];
            $wrongTarget = $parts;
            $wrongTarget[1]['contents'] = (string) $ready['uploads']['client_id'];
            $invalidParts[] = $wrongTarget;
            $invalidParts[] = [...$parts, ['name' => 'tag', 'contents' => 'unexpected']];
            $invalidParts[] = $this->transportMultipart($fixture, 'Café transporte.png', 'not the fixture bytes');
            $invalidParts[] = $this->transportMultipart($fixture, 'unexpected.png', $png);
            $invalidParts[] = [...$parts, $parts[2]];
            $nestedFile = $parts;
            $nestedFile[2]['name'] = 'files[0][nested]';
            $invalidParts[] = $nestedFile;
            foreach ($invalidParts as $invalid) {
                $this->assertSame(404, $this->request($developer, $origin, 'POST', '/api/v1/uploads', ['headers' => $this->csrf($jar), 'multipart' => $invalid])->getStatusCode());
                $this->assertCount(0, $this->transportRows($root, $oldRows, $oldFiles));
            }
            $created = $this->request($developer, $origin, 'POST', '/api/v1/uploads', ['headers' => $this->csrf($jar), 'multipart' => $parts]);
            $this->assertSame(201, $created->getStatusCode());
            $uploaded = json_decode((string) $created->getBody(), true, 512, JSON_THROW_ON_ERROR)['data'];
            $this->assertCount(1, $uploaded);
            $this->assertSame('Café transporte.png', $uploaded[0]['original_name']);
            $rows = $this->transportRows($root, $oldRows, $oldFiles);
            $this->assertCount(1, $rows);
            $this->assertSame((int) $rows[0]['id'], $uploaded[0]['id']);
            $this->assertSame('authority_transport', $rows[0]['disk']);
            $this->assertSame($png, file_get_contents($root.'/http-transport-files/'.$rows[0]['path']));
            $this->assertNull($rows[0]['tag']);
            $this->assertNull($rows[0]['collection']);
            $duplicate = $this->request($developer, $origin, 'POST', '/api/v1/uploads', ['headers' => $this->csrf($jar), 'multipart' => $parts]);
            $this->assertSame(201, $duplicate->getStatusCode());
            $this->assertSame($uploaded[0]['id'], json_decode((string) $duplicate->getBody(), true, 512, JSON_THROW_ON_ERROR)['data'][0]['id']);
            $this->assertSame($rows, $this->transportRows($root, $oldRows, $oldFiles));
            $second = $this->request($developer, $origin, 'POST', '/api/v1/uploads', [
                'headers' => $this->csrf($jar), 'multipart' => $this->transportMultipart($fixture, 'Recuperação transporte.pdf', $pdf),
            ]);
            $this->assertSame(201, $second->getStatusCode());
            $secondData = json_decode((string) $second->getBody(), true, 512, JSON_THROW_ON_ERROR)['data'][0];
            $this->assertSame('Recuperação transporte.pdf', $secondData['original_name']);
            $this->assertNotSame($uploaded[0]['id'], $secondData['id']);
            $rows = $this->transportRows($root, $oldRows, $oldFiles);
            $this->assertCount(2, $rows);
            $this->assertSame($pdf, file_get_contents($root.'/http-transport-files/'.$rows[1]['path']));
            foreach ($rows as $row) {
                $preview = $this->request($developer, $origin, 'GET', '/uploads/'.$row['id'].'/preview');
                $this->assertSame(200, $preview->getStatusCode());
                $this->assertSame($row['extension'] === 'png' ? 'image/png' : 'application/pdf', $preview->getHeaderLine('Content-Type'));
                $this->assertSame('nosniff', $preview->getHeaderLine('X-Content-Type-Options'));
                $this->assertStringStartsWith('inline;', $preview->getHeaderLine('Content-Disposition'));
                $this->assertSame($row['file_hash'], hash('sha256', (string) $preview->getBody()));
                $this->assertSame($row['extension'] === 'png' ? $png : $pdf, (string) $preview->getBody());
            }
            $this->assertSame(404, $this->request($developer, $origin, 'GET', '/uploads/999999/preview')->getStatusCode());
            $uploads = $this->request($developer, $origin, 'GET', '/api/v1/uploads', ['query' => ['parentable_type' => 'client', 'parentable_id' => $fixture['client_uuid']]]);
            $this->assertSame(200, $uploads->getStatusCode());
            $this->assertEqualsCanonicalizing([$uploaded[0]['id'], $secondData['id']], array_column(json_decode((string) $uploads->getBody(), true, 512, JSON_THROW_ON_ERROR)['data'], 'id'));
            foreach ([['POST', '/clients'], ['POST', '/api/v1/clients'], ['POST', '/api/v1/uploads/image'], ['POST', '/api/v1/uploads/zip-requests'], ['GET', '/__uploads_control'], ['GET', '/api/v1/exports']] as [$method, $path]) {
                $this->assertSame(404, $this->request($developer, $origin, $method, $path, ['headers' => $this->csrf($jar)])->getStatusCode());
            }
            $this->assertSame($rows, $this->transportRows($root, $oldRows, $oldFiles));
            $input->write("status\n");
            $status = $this->message($process, $buffer, 'audit', 10);
            $this->assertSame($ready['uploads']['snapshot'], $status['uploads_snapshot']);
            $this->assertSame($fixture['clients_snapshot'], $status['transport_clients_snapshot']);
            $this->assertNotEmpty($status['audit']);
            foreach ($status['audit'] as $record) {
                $this->assertFalse($record['running_in_console']);
                $this->assertTrue($record['running_unit_tests']);
            }
            $tmpEntries = array_values(array_diff(scandir($root.'/http-upload-tmp'), ['.', '..']));
            $this->assertSame(['owner'], $tmpEntries, 'PHP must remove its real multipart temporary files after every request.');
        } finally {
            if ($started) {
                try {
                    if ($process->isRunning()) {
                        $input->write("stop\n");
                        $input->close();
                    }
                    $process->setTimeout(microtime(true) - $process->getStartTime() + 30);
                    try {
                        $process->wait();
                    } catch (ProcessTimedOutException) {
                        $process->stop(3);
                    }
                } finally {
                    $process->stop(3);
                }
                $this->assertFalse($process->isRunning());
                $this->assertSame(0, $process->getExitCode(), 'The owned transport launcher must finish cleanup.');
            }
            $this->assertCount(0, array_diff($this->directories(), $before), 'The transport sandbox must leave no temporary directories.');
        }
    }

    /**
     * @param  array<string, mixed>  $fixture
     * @return list<array<string, string>>
     */
    private function transportMultipart(array $fixture, string $filename, string $bytes): array
    {
        return [
            ['name' => 'parentable_type', 'contents' => 'client'],
            ['name' => 'parentable_id', 'contents' => (string) $fixture['client_id']],
            ['name' => 'files[]', 'contents' => $bytes, 'filename' => $filename],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $oldRows
     * @param  array<string, string>  $oldFiles
     * @return list<array<string, mixed>>
     */
    private function transportRows(string $root, array $oldRows, array $oldFiles): array
    {
        $manifest = AuthorityHttpEnvironment::readManifest($root.'/http.json');
        $pdo = DisposableMysql::verifiedConnection($manifest['settings']);
        $rows = $pdo->query('SELECT * FROM uploadeds ORDER BY id')->fetchAll(\PDO::FETCH_ASSOC);
        $oldIds = array_column($oldRows, 'id');
        $this->assertSame($oldRows, array_values(array_filter($rows, fn (array $row): bool => in_array($row['id'], $oldIds, true))));
        foreach ($oldFiles as $path => $bytes) {
            $this->assertSame($bytes, file_get_contents($root.'/http-upload-files/'.$path));
        }

        return array_values(array_filter($rows, fn (array $row): bool => ! in_array($row['id'], $oldIds, true)));
    }

    public function test_pulse_http_dashboard_and_livewire_refresh_preserve_authorization_csrf_and_owned_metrics(): void
    {
        if (getenv('AUTHORITY_HTTP_TESTS') !== '1' || getenv('AUTHORITY_MYSQL_TESTS') !== '1' || getenv('AUTHORITY_HTTP_PULSE_TESTS') !== '1') {
            $this->markTestSkipped('Pulse HTTP validation requires all three explicit sandbox opt-ins.');
        }

        $host = AuthorityHttpEnvironment::requiredHost();
        $before = $this->directories();
        $input = new InputStream;
        $process = new Process([PHP_BINARY, dirname(__DIR__).'/authority-http-server.php'], dirname(__DIR__, 2), [
            'AUTHORITY_HTTP_TESTS' => '1', 'AUTHORITY_MYSQL_TESTS' => '1', 'AUTHORITY_HTTP_PULSE_TESTS' => '1',
            'AUTHORITY_HTTP_EDITOR_TESTS' => '0', 'AUTHORITY_HTTP_UPLOADS_TESTS' => '0',
            'AUTHORITY_HTTP_TIMELINE_TESTS' => '0', 'AUTHORITY_HTTP_DEVTOOLS_TESTS' => '0',
            'AUTHORITY_HTTP_VERIFICATION_TESTS' => '0', 'AUTHORITY_HTTP_HOST' => $host, 'APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => '',
        ]);
        $process->setInput($input);
        $process->setTimeout(240);
        $buffer = '';
        $started = false;

        try {
            $process->start();
            $started = true;
            $ready = $this->message($process, $buffer, 'url', 180);
            $this->assertSame('pulse', $ready['mode'] ?? null);
            $origin = $ready['url'];
            $this->assertOrigin($origin);
            $ownedRoot = $this->ownedRoot($before, 'pulse');
            $fixture = $ready['pulse'];
            $this->assertSame($fixture, AuthorityHttpEnvironment::readManifest($ownedRoot.'/http.json')['pulse']);
            $this->assertSame($ready['fixtures']['developer']['id'], $fixture['user_id']);
            $this->assertSame($ready['fixtures']['developer']['uuid'], $fixture['user_uuid']);
            $update = $fixture['update_path'];
            $this->assertMatchesRegularExpression('~\A/livewire-[a-f0-9]{8}/update\z~', $update);

            $guestJar = new CookieJar;
            $guest = $this->client($origin, $guestJar);
            $this->assertSame(403, $this->request($guest, $origin, 'GET', '/pulse')->getStatusCode());
            $this->assertSame(200, $this->request($guest, $origin, 'GET', '/login', ['headers' => ['Accept' => 'text/html']])->getStatusCode());
            $ordinaryJar = new CookieJar;
            $ordinary = $this->client($origin, $ordinaryJar);
            $this->login($ordinary, $ordinaryJar, $origin, $ready['fixtures']['ordinary']);
            $this->assertSame(403, $this->request($ordinary, $origin, 'GET', '/pulse')->getStatusCode());

            $jar = new CookieJar;
            $developer = $this->client($origin, $jar);
            $this->login($developer, $jar, $origin, $ready['fixtures']['developer']);
            $page = $this->request($developer, $origin, 'GET', '/pulse', ['headers' => ['Accept' => 'text/html']]);
            $snapshot = $this->pulsePeriodSnapshot($page);
            $this->assertSame(AuthorityHttpEnvironment::contentSecurityPolicy('pulse'), $page->getHeaderLine('Content-Security-Policy'));
            $this->assertSame(1, preg_match('/window\.livewireScriptConfig\s*=\s*(\{.*?\});/s', (string) $page->getBody(), $configMatches));
            $scriptConfig = json_decode($configMatches[1], true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame($origin.$update, $scriptConfig['uri']);
            $payload = ['components' => [[
                'snapshot' => $snapshot, 'updates' => new stdClass,
                'calls' => [['path' => '', 'method' => '$refresh', 'params' => []]],
            ]]];
            $headers = [...$this->csrf($jar), 'X-Livewire' => 'true'];

            $this->assertSame(419, $this->request($developer, $origin, 'POST', $update, [
                'headers' => ['X-Livewire' => 'true'], 'json' => $payload,
            ])->getStatusCode());
            $this->assertSame(404, $this->request($developer, $origin, 'POST', $update, [
                'headers' => $this->csrf($jar), 'json' => $payload,
            ])->getStatusCode());

            $refresh = $this->request($developer, $origin, 'POST', $update, ['headers' => $headers, 'json' => $payload]);
            $this->assertSame(200, $refresh->getStatusCode());
            $data = json_decode((string) $refresh->getBody(), true, 512, JSON_THROW_ON_ERROR);
            $this->assertCount(1, $data['components']);
            $this->assertIsString($data['components'][0]['snapshot']);
            $this->assertStringContainsString('setPeriod', $data['components'][0]['effects']['html']);
            $nextSnapshot = json_decode($data['components'][0]['snapshot'], true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame('pulse.period-selector', $nextSnapshot['memo']['name']);
            $this->assertSame('1_hour', $nextSnapshot['data']['period']);
            $payload['components'][0]['snapshot'] = $data['components'][0]['snapshot'];

            foreach ([[$guest, $guestJar], [$ordinary, $ordinaryJar]] as [$unauthorized, $unauthorizedJar]) {
                $this->assertSame(403, $this->request($unauthorized, $origin, 'POST', $update, [
                    'headers' => [...$this->csrf($unauthorizedJar), 'X-Livewire' => 'true'], 'json' => $payload,
                ])->getStatusCode(), 'Persistent Pulse authorization must reject a developer snapshot in another session.');
            }
            $this->assertSame(200, $this->request($developer, $origin, 'POST', $update, ['headers' => $headers, 'json' => $payload])->getStatusCode());

            $pdo = DisposableMysql::verifiedConnection(AuthorityHttpEnvironment::readManifest($ownedRoot.'/http.json')['settings']);
            $actorId = $fixture['user_id'];
            $verifiedAt = $pdo->query('SELECT email_verified_at FROM users WHERE id = '.(int) $actorId)->fetchColumn();
            $this->assertIsString($verifiedAt);
            $setVerification = $pdo->prepare('UPDATE users SET email_verified_at = ? WHERE id = ?');
            try {
                $setVerification->execute([null, $actorId]);
                $unverified = $this->request($developer, $origin, 'POST', $update, ['headers' => $headers, 'json' => $payload]);
                $this->assertSame(403, $unverified->getStatusCode());
                $this->assertStringStartsWith('application/json', $unverified->getHeaderLine('Content-Type'));
                $this->assertArrayNotHasKey('components', json_decode((string) $unverified->getBody(), true, 512, JSON_THROW_ON_ERROR));
            } finally {
                $setVerification->execute([$verifiedAt, $actorId]);
            }
            $this->assertSame(200, $this->request($developer, $origin, 'POST', $update, ['headers' => $headers, 'json' => $payload])->getStatusCode());

            foreach (['1_hour', '6_hours', '24_hours', '7_days'] as $period) {
                $periodSnapshot = $this->pulsePeriodSnapshot($this->request($developer, $origin, 'GET', '/pulse', [
                    'headers' => ['Accept' => 'text/html'], 'query' => ['period' => $period],
                ]));
                $this->assertSame($period, json_decode($periodSnapshot, true, 512, JSON_THROW_ON_ERROR)['data']['period']);
            }
            foreach ([['period' => ['1_hour']], ['period' => 'unknown'], ['period' => '6_hours', 'extra' => '1']] as $query) {
                $this->assertSame(404, $this->request($developer, $origin, 'GET', '/pulse', ['query' => $query])->getStatusCode());
            }
            foreach ([['GET', $update], ['POST', '/pulse'], ['POST', '/livewire-deadbeef/update'], ['POST', str_replace('/update', '/upload-file', $update)], ['GET', str_replace('/update', '/livewire.min.js.map', $update)], ['POST', '/admin/dev/dev-tools/env/update'], ['POST', '/api/v1/uploads'], ['POST', '/api/v1/timeline/comments'], ['GET', '/__timeline_control'], ['GET', '/__uploads_control'], ['HEAD', '/__authority_health']] as [$method, $path]) {
                $this->assertSame(404, $this->request($developer, $origin, $method, $path, ['headers' => $headers])->getStatusCode(), $method.' '.$path);
            }

            $logout = $this->request($developer, $origin, 'POST', '/logout', ['headers' => $this->csrf($jar)]);
            $this->assertSame(204, $logout->getStatusCode());
            $this->assertSame('', (string) $logout->getBody());
            $this->assertSame(403, $this->request($developer, $origin, 'GET', '/pulse')->getStatusCode());
            $this->assertSame(403, $this->request($developer, $origin, 'POST', $update, [
                'headers' => [...$this->csrf($jar), 'X-Livewire' => 'true'], 'json' => $payload,
            ])->getStatusCode());
            $this->login($developer, $jar, $origin, $ready['fixtures']['developer']);
            $this->pulsePeriodSnapshot($this->request($developer, $origin, 'GET', '/pulse', ['headers' => ['Accept' => 'text/html']]));
            $this->assertSame($fixture, AuthorityHttpEnvironment::readManifest($ownedRoot.'/http.json')['pulse']);

            $input->write("status\n");
            $status = $this->message($process, $buffer, 'audit', 10);
            $this->assertSame($fixture['snapshot'], $status['pulse_snapshot']);
            $this->assertNotEmpty($status['audit']);
            $this->assertNotEmpty(array_filter($status['audit'], fn (array $record): bool => $record['method'] === 'POST' && $record['path'] === $update && $record['status'] === 200));
            foreach ($status['audit'] as $record) {
                $this->assertFalse($record['running_in_console']);
                $this->assertTrue($record['running_unit_tests']);
            }
        } finally {
            if ($started) {
                try {
                    if ($process->isRunning()) {
                        $input->write("stop\n");
                        $input->close();
                    }
                    $process->setTimeout(microtime(true) - $process->getStartTime() + 30);
                    try {
                        $process->wait();
                    } catch (ProcessTimedOutException) {
                        $process->stop(3);
                    }
                } finally {
                    $process->stop(3);
                }
                $this->assertFalse($process->isRunning());
                $this->assertSame(0, $process->getExitCode(), 'The owned Pulse launcher must finish cleanup.');
            }
            $this->assertCount(0, array_diff($this->directories(), $before), 'The owned Pulse sandbox must leave no temporary directories.');
        }
    }

    private function pulsePeriodSnapshot(ResponseInterface $response): string
    {
        $this->assertSame(200, $response->getStatusCode());
        $html = (string) $response->getBody();
        $this->assertStringContainsString('Laravel Pulse', $html);
        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument;
        try {
            $this->assertTrue($document->loadHTML($html, LIBXML_NONET));
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $snapshots = [];
        foreach ($document->getElementsByTagName('*') as $element) {
            if (! $element->hasAttribute('wire:snapshot')) {
                continue;
            }
            $snapshot = $element->getAttribute('wire:snapshot');
            $data = json_decode($snapshot, true, 512, JSON_THROW_ON_ERROR);
            if (($data['memo']['name'] ?? null) === 'pulse.period-selector') {
                $this->assertSame('pulse', $data['memo']['path']);
                $this->assertSame('GET', $data['memo']['method']);
                $snapshots[] = $snapshot;
            }
        }
        $this->assertCount(1, $snapshots, 'The real Pulse dashboard must emit its period selector snapshot.');

        return $snapshots[0];
    }

    public function test_devtools_http_settings_and_maintenance_are_isolated_and_recoverable(): void
    {
        if (getenv('AUTHORITY_HTTP_TESTS') !== '1' || getenv('AUTHORITY_MYSQL_TESTS') !== '1' || getenv('AUTHORITY_HTTP_DEVTOOLS_TESTS') !== '1') {
            $this->markTestSkipped('DevTools HTTP validation requires all three explicit sandbox opt-ins.');
        }

        $host = AuthorityHttpEnvironment::requiredHost();
        $before = $this->directories();
        $input = new InputStream;
        $process = new Process([PHP_BINARY, dirname(__DIR__).'/authority-http-server.php'], dirname(__DIR__, 2), [
            'AUTHORITY_HTTP_TESTS' => '1', 'AUTHORITY_MYSQL_TESTS' => '1', 'AUTHORITY_HTTP_DEVTOOLS_TESTS' => '1', 'AUTHORITY_HTTP_VERIFICATION_TESTS' => '0', 'AUTHORITY_HTTP_HOST' => $host,
            'APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => '',
        ]);
        $process->setInput($input);
        $process->setTimeout(240);
        $buffer = '';
        $started = false;

        try {
            $process->start();
            $started = true;
            $ready = $this->message($process, $buffer, 'url', 180);
            $this->assertSame('devtools', $ready['mode'] ?? null);
            $origin = $ready['url'];
            $this->assertOrigin($origin);
            $ownedRoot = $this->ownedRoot($before);
            $developerJar = new CookieJar;
            $developer = $this->client($origin, $developerJar);
            $this->login($developer, $developerJar, $origin, $ready['fixtures']['developer']);
            $page = $this->page($this->request($developer, $origin, 'GET', '/admin/dev/dev-tools'));
            $this->assertSettings($page, 'DevTools Browser Fixture');
            $this->assertSame(419, $this->request($developer, $origin, 'POST', '/admin/dev/dev-tools/env/update', [
                'json' => ['key' => 'APP_NAME', 'value' => 'Without CSRF'],
            ])->getStatusCode());

            $this->assertRedirect($this->request($developer, $origin, 'POST', '/admin/dev/dev-tools/env/update', [
                'headers' => $this->csrf($developerJar), 'json' => ['key' => 'APP_NAME', 'value' => 'Browser Safe Setting'],
            ]));
            $page = $this->page($this->request($developer, $origin, 'GET', '/admin/dev/dev-tools'));
            $this->assertSettings($page, 'Browser Safe Setting');

            foreach ([['key' => 'APP_KEY', 'value' => 'Rejected'], ['key' => 'APP_NAME', 'value' => "Unsafe\"\nAPP_KEY=injected"]] as $payload) {
                $this->assertSame(422, $this->request($developer, $origin, 'POST', '/admin/dev/dev-tools/env/update', [
                    'headers' => $this->csrf($developerJar), 'json' => $payload,
                ])->getStatusCode());
                $this->assertSettings($this->page($this->request($developer, $origin, 'GET', '/admin/dev/dev-tools')), 'Browser Safe Setting');
            }

            $this->assertRedirect($this->request($developer, $origin, 'POST', '/admin/dev/dev-tools/env/update', [
                'headers' => $this->csrf($developerJar), 'json' => ['key' => 'TELESCOPE_ENABLED', 'value' => 'true'],
            ]));
            $page = $this->page($this->request($developer, $origin, 'GET', '/admin/dev/dev-tools'));
            $this->assertSettings($page, 'Browser Safe Setting');
            $this->assertTrue($page['props']['operationalSettings']['TELESCOPE_ENABLED']);

            $ordinaryJar = new CookieJar;
            $ordinary = $this->client($origin, $ordinaryJar);
            $this->login($ordinary, $ordinaryJar, $origin, $ready['fixtures']['ordinary']);
            $this->assertSame(403, $this->request($ordinary, $origin, 'GET', '/admin/dev/dev-tools')->getStatusCode());
            $this->assertSame(403, $this->request($ordinary, $origin, 'POST', '/admin/dev/dev-tools/env/update', [
                'headers' => $this->csrf($ordinaryJar), 'json' => ['key' => 'APP_NAME', 'value' => 'Unauthorized'],
            ])->getStatusCode());
            $this->assertSettings($this->page($this->request($developer, $origin, 'GET', '/admin/dev/dev-tools')), 'Browser Safe Setting');

            $down = $this->request($developer, $origin, 'POST', '/admin/dev/dev-tools/maintenance/toggle', ['headers' => $this->csrf($developerJar)]);
            $this->assertRedirect($down);
            $firstCookie = $developerJar->getCookieByName('laravel_maintenance');
            $this->assertInstanceOf(SetCookie::class, $firstCookie);
            $this->assertTrue($firstCookie->getHttpOnly());
            $this->assertSame($host, $firstCookie->getDomain());
            $this->assertTrue($firstCookie->getHostOnly());
            $firstValue = $firstCookie->getValue();
            $firstSecret = $this->maintenanceSecret($ownedRoot);
            $this->assertFalse(str_contains($down->getHeaderLine('Location'), $firstValue));
            $this->assertFalse(str_contains($down->getHeaderLine('Location'), $firstSecret));
            $this->assertFalse(str_contains((string) $down->getBody(), $firstSecret));
            $page = $this->page($this->request($developer, $origin, 'GET', '/admin/dev/dev-tools'), [$firstValue, $firstSecret]);
            $this->assertTrue($page['props']['maintenanceMode']);
            $this->assertSettings($page, 'Browser Safe Setting', [$firstValue, $firstSecret]);
            $outsider = $this->client($origin);
            $unavailable = $this->request($outsider, $origin, 'GET', '/admin/dev/dev-tools');
            $this->assertSame(503, $unavailable->getStatusCode());
            $this->assertFalse(str_contains((string) $unavailable->getBody(), $firstSecret));

            $up = $this->request($developer, $origin, 'POST', '/admin/dev/dev-tools/maintenance/toggle', ['headers' => $this->csrf($developerJar)]);
            $this->assertRedirect($up, [$firstSecret]);
            $expiredCookies = array_values(array_filter(
                array_map(SetCookie::fromString(...), $up->getHeader('Set-Cookie')),
                fn (SetCookie $cookie): bool => $cookie->getName() === 'laravel_maintenance',
            ));
            $this->assertCount(1, $expiredCookies);
            $this->assertTrue($expiredCookies[0]->isExpired());
            $this->assertSame(0, $expiredCookies[0]->getMaxAge());
            $this->assertTrue($expiredCookies[0]->getHttpOnly());
            $this->assertSame('/', $expiredCookies[0]->getPath());
            $this->assertNull($expiredCookies[0]->getDomain());
            $expiredCookie = $developerJar->getCookieByName('laravel_maintenance');
            $this->assertNull($expiredCookie, 'The deletion response must remove the host-only maintenance cookie from the jar.');
            $nextRequest = $developerJar->withCookieHeader(new Request('GET', $origin.'/admin/dev/dev-tools'));
            $this->assertFalse(str_contains($nextRequest->getHeaderLine('Cookie'), 'laravel_maintenance='));
            $this->assertFalse($this->page($this->request($developer, $origin, 'GET', '/admin/dev/dev-tools'))['props']['maintenanceMode']);
            $this->assertSame(200, $this->request($outsider, $origin, 'GET', '/login')->getStatusCode());

            $secondDown = $this->request($developer, $origin, 'POST', '/admin/dev/dev-tools/maintenance/toggle', ['headers' => $this->csrf($developerJar)]);
            $secondSecret = $this->maintenanceSecret($ownedRoot);
            $this->assertRedirect($secondDown, [$firstSecret, $secondSecret]);
            $secondCookie = $developerJar->getCookieByName('laravel_maintenance');
            $this->assertInstanceOf(SetCookie::class, $secondCookie);
            $this->assertTrue($secondCookie->getHttpOnly());
            $this->assertFalse(hash_equals($firstValue, $secondCookie->getValue()), 'Every activation must rotate the maintenance credential.');
            $this->assertFalse(hash_equals($firstSecret, $secondSecret), 'Every activation must rotate its private maintenance secret.');
            $oldJar = new CookieJar;
            $oldJar->setCookie(new SetCookie(['Name' => 'laravel_maintenance', 'Value' => $firstValue, 'Domain' => $host, 'Path' => '/', 'HttpOnly' => true]));
            $this->assertSame(503, $this->request($this->client($origin, $oldJar), $origin, 'GET', '/admin/dev/dev-tools')->getStatusCode());
            $privateValues = [$firstValue, $secondCookie->getValue(), $firstSecret, $secondSecret];
            $page = $this->page($this->request($developer, $origin, 'GET', '/admin/dev/dev-tools'), $privateValues);
            $this->assertTrue($page['props']['maintenanceMode']);
            $this->assertSettings($page, 'Browser Safe Setting', $privateValues);
            $this->assertRedirect($this->request($developer, $origin, 'POST', '/admin/dev/dev-tools/maintenance/toggle', ['headers' => $this->csrf($developerJar)]), [$firstSecret, $secondSecret]);
            $this->assertSame(200, $this->request($outsider, $origin, 'GET', '/login')->getStatusCode());

            $input->write("status\n");
            $audit = $this->message($process, $buffer, 'audit', 10)['audit'];
            $this->assertNotEmpty($audit);
            $this->assertCount(4, array_filter($audit, fn (array $record): bool => $record['method'] === 'POST'
                && $record['path'] === '/admin/dev/dev-tools/maintenance/toggle'
                && in_array($record['status'], [302, 303], true)
                && $record['fixture_user_id'] === $ready['fixtures']['developer']['id']));

            foreach ($privateValues as $private) {
                $this->assertFalse(str_contains(json_encode($audit, JSON_THROW_ON_ERROR), $private), 'The HTTP audit must not contain maintenance credentials.');
            }

            foreach ($audit as $record) {
                $this->assertSame(['method', 'path', 'status', 'fixture_user_id', 'running_in_console', 'running_unit_tests'], array_keys($record));
                $this->assertFalse($record['running_in_console']);
                $this->assertTrue($record['running_unit_tests']);
                $this->assertFalse(str_contains($record['path'], $firstValue));
                $this->assertFalse(str_contains($record['path'], $secondCookie->getValue()));
            }
        } finally {
            if ($process->isRunning()) {
                $input->write("stop\n");
                $input->close();
                $deadline = microtime(true) + 30;

                while ($process->isRunning() && microtime(true) < $deadline) {
                    $process->getIncrementalOutput();
                    usleep(20000);
                }

                if ($process->isRunning()) {
                    $process->stop(3);
                }
            }

            if ($started) {
                $this->assertFalse($process->isRunning());
                $this->assertSame(0, $process->getExitCode(), 'The owned DevTools launcher must finish cleanup.');
            }

            $this->assertCount(0, array_diff($this->directories(), $before), 'The owned DevTools sandbox must leave no temporary directories.');
        }
    }

    public function test_timeline_http_reads_old_root_and_reply_without_mutations_in_its_owned_scope(): void
    {
        if (getenv('AUTHORITY_HTTP_TESTS') !== '1' || getenv('AUTHORITY_MYSQL_TESTS') !== '1' || getenv('AUTHORITY_HTTP_TIMELINE_TESTS') !== '1') {
            $this->markTestSkipped('Timeline HTTP validation requires all three explicit sandbox opt-ins.');
        }

        $host = AuthorityHttpEnvironment::requiredHost();
        $before = $this->directories();
        $input = new InputStream;
        $process = new Process([PHP_BINARY, dirname(__DIR__).'/authority-http-server.php'], dirname(__DIR__, 2), [
            'AUTHORITY_HTTP_TESTS' => '1', 'AUTHORITY_MYSQL_TESTS' => '1', 'AUTHORITY_HTTP_TIMELINE_TESTS' => '1',
            'AUTHORITY_HTTP_DEVTOOLS_TESTS' => '0', 'AUTHORITY_HTTP_VERIFICATION_TESTS' => '0', 'AUTHORITY_HTTP_HOST' => $host,
            'APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => '',
        ]);
        $process->setInput($input);
        $process->setTimeout(240);
        $buffer = '';
        $started = false;

        try {
            $process->start();
            $started = true;
            $ready = $this->message($process, $buffer, 'url', 180);
            $this->assertSame('timeline', $ready['mode'] ?? null);
            $origin = $ready['url'];
            $this->assertOrigin($origin);
            $ownedRoot = $this->ownedRoot($before, 'timeline');
            $fixture = $ready['timeline'];
            $this->assertSame($fixture, AuthorityHttpEnvironment::readManifest($ownedRoot.'/http.json')['timeline']);
            $jar = new CookieJar;
            $client = $this->client($origin, $jar);
            $this->login($client, $jar, $origin, $ready['fixtures']['developer']);
            $pageResponse = $this->request($client, $origin, 'GET', '/admin/dev/components/activity-timeline', ['headers' => ['Accept' => 'text/html']]);
            $page = $this->page($pageResponse);
            $this->assertSame('admin/dev/components/activity-timeline', $page['component']);
            $this->assertSame('client', $page['props']['defaultSubject']['type']);
            $this->assertSame($fixture['client_uuid'], $page['props']['defaultSubject']['id']);
            $api = '/api/v1/timeline/client/'.$fixture['client_uuid'];
            $response = $this->request($client, $origin, 'GET', $api, ['query' => ['types' => ['comment']]]);
            $this->assertSame(200, $response->getStatusCode());
            $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR)['data'];
            $this->assertCount(1, $data);
            $this->assertSame($fixture['root_id'], $data[0]['id']);
            $this->assertSame('html', $data[0]['body_format']);
            $this->assertSame('<p><strong>SEC010 safe</strong></p>', $data[0]['body']);
            $this->assertCount(1, $data[0]['replies']);
            $this->assertSame($fixture['reply_id'], $data[0]['replies'][0]['id']);
            $this->assertSame('html', $data[0]['replies'][0]['body_format']);
            $this->assertSame('<a>SEC010 link</a><p><em>SEC010 emphasis</em></p>', $data[0]['replies'][0]['body']);

            $control = $this->request($client, $origin, 'GET', '/__timeline_control', ['headers' => ['Accept' => 'text/html']]);
            $this->assertSame(200, $control->getStatusCode());
            $this->assertSame($pageResponse->getHeaderLine('Content-Security-Policy'), $control->getHeaderLine('Content-Security-Policy'));
            $this->assertStringContainsString("script-src 'self' 'unsafe-inline'", $control->getHeaderLine('Content-Security-Policy'));
            $this->assertStringContainsString('window.__sec010ControlImage=1', (string) $control->getBody());
            $this->assertStringContainsString('window.__sec010ControlLink=1;void(0)', (string) $control->getBody());
            $this->assertSame(404, $this->request($client, $origin, 'GET', '/__timeline_missing_control')->getStatusCode());

            $build = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/public/build/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
            $asset = '/build/'.$build['resources/js/app.tsx']['file'];
            $this->assertNotNull(AuthorityHttpEnvironment::assetPath($asset, dirname(__DIR__, 2).'/public'));
            $this->assertSame(200, $this->request($client, $origin, 'GET', $asset)->getStatusCode());
            $this->assertSame(404, $this->request($client, $origin, 'POST', $asset)->getStatusCode());
            $this->assertSame(404, $this->request($client, $origin, 'POST', '/__authority_health')->getStatusCode());
            $this->assertSame(404, $this->request($client, $origin, 'HEAD', '/__authority_health')->getStatusCode());

            foreach ([['POST', $api.'/comments'], ['PUT', '/api/v1/timeline/comments/'.$fixture['root_id']], ['DELETE', '/api/v1/timeline/comments/'.$fixture['root_id']], ['POST', '/api/v1/timeline/comments/'.$fixture['root_id'].'/replies'], ['POST', '/admin/dev/dev-tools/env/update'], ['GET', '/api/v1/users'], ['POST', '/__timeline_control']] as [$method, $path]) {
                $this->assertSame(404, $this->request($client, $origin, $method, $path, ['headers' => $this->csrf($jar)])->getStatusCode(), $method.' '.$path);
            }

            $this->assertSame($fixture, AuthorityHttpEnvironment::readManifest($ownedRoot.'/http.json')['timeline']);
            $input->write("status\n");
            $status = $this->message($process, $buffer, 'audit', 10);
            $this->assertSame($fixture['snapshot'], $status['timeline_snapshot']);
            $reads = array_values(array_filter($status['audit'], fn (array $record): bool => $record['path'] === $api));
            $this->assertCount(1, $reads);
            $this->assertSame('GET', $reads[0]['method']);
            $this->assertSame(200, $reads[0]['status']);
            $this->assertSame($ready['fixtures']['developer']['id'], $reads[0]['fixture_user_id']);
            $this->assertFalse($reads[0]['running_in_console']);
            $this->assertTrue($reads[0]['running_unit_tests']);
        } finally {
            if ($process->isRunning()) {
                $input->write("stop\n");
                $input->close();
                $deadline = microtime(true) + 30;

                while ($process->isRunning() && microtime(true) < $deadline) {
                    $process->getIncrementalOutput();
                    usleep(20000);
                }

                if ($process->isRunning()) {
                    $process->stop(3);
                }
            }

            if ($started) {
                $this->assertFalse($process->isRunning());
                $this->assertSame(0, $process->getExitCode(), 'The owned Timeline launcher must finish cleanup.');
            }

            $this->assertCount(0, array_diff($this->directories(), $before), 'The owned Timeline sandbox must leave no temporary directories.');
        }
    }

    public function test_uploads_http_preview_and_download_protect_old_bytes_in_the_owned_scope(): void
    {
        if (getenv('AUTHORITY_HTTP_TESTS') !== '1' || getenv('AUTHORITY_MYSQL_TESTS') !== '1' || getenv('AUTHORITY_HTTP_UPLOADS_TESTS') !== '1') {
            $this->markTestSkipped('Uploads HTTP validation requires all three explicit sandbox opt-ins.');
        }

        $host = AuthorityHttpEnvironment::requiredHost();
        $before = $this->directories();
        $input = new InputStream;
        $process = new Process([PHP_BINARY, dirname(__DIR__).'/authority-http-server.php'], dirname(__DIR__, 2), [
            'AUTHORITY_HTTP_TESTS' => '1', 'AUTHORITY_MYSQL_TESTS' => '1', 'AUTHORITY_HTTP_UPLOADS_TESTS' => '1',
            'AUTHORITY_HTTP_DEVTOOLS_TESTS' => '0', 'AUTHORITY_HTTP_TIMELINE_TESTS' => '0', 'AUTHORITY_HTTP_VERIFICATION_TESTS' => '0', 'AUTHORITY_HTTP_HOST' => $host,
            'APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => '',
        ]);
        $process->setInput($input);
        $process->setTimeout(240);
        $buffer = '';
        $started = false;

        try {
            $process->start();
            $started = true;
            $ready = $this->message($process, $buffer, 'url', 180);
            $this->assertSame('uploads', $ready['mode'] ?? null);
            $origin = $ready['url'];
            $this->assertOrigin($origin);
            $ownedRoot = $this->ownedRoot($before, 'uploads');
            $fixture = $ready['uploads'];
            $this->assertSame(1, $fixture['client_id'], 'The existing playground initially reads client 1.');
            $this->assertSame($fixture, AuthorityHttpEnvironment::readManifest($ownedRoot.'/http.json')['uploads']);
            $jar = new CookieJar;
            $client = $this->client($origin, $jar);
            $this->login($client, $jar, $origin, $ready['fixtures']['developer']);
            $pageResponse = $this->request($client, $origin, 'GET', '/admin/dev/components/file-attachments', ['headers' => ['Accept' => 'text/html']]);
            $this->assertSame('admin/dev/components/file-attachments', $this->page($pageResponse)['component']);
            $list = $this->request($client, $origin, 'GET', '/api/v1/uploads', ['query' => ['parentable_type' => 'client', 'parentable_id' => (string) $fixture['client_id']]]);
            $this->assertSame(200, $list->getStatusCode());
            $data = json_decode((string) $list->getBody(), true, 512, JSON_THROW_ON_ERROR)['data'];
            $this->assertCount(4, $data);
            $this->assertSame(200, $this->request($client, $origin, 'GET', '/api/v1/uploads/tags', ['query' => ['parentable_type' => 'client']])->getStatusCode());
            $byId = array_column($data, null, 'id');
            $policy = "script-src 'none'; base-uri 'none'; form-action 'none'; frame-ancestors 'self'";
            foreach ($fixture['files'] as $key => $file) {
                $this->assertSame($origin.'/uploads/'.$file['id'].'/preview', $byId[$file['id']]['preview_url']);
                $this->assertSame($origin.'/uploads/'.$file['id'].'/download', $byId[$file['id']]['download_url']);
                $safe = in_array($key, ['pdf', 'png'], true);
                $mime = $safe ? ($key === 'pdf' ? 'application/pdf' : 'image/png') : 'application/octet-stream';
                foreach (['preview', 'download'] as $action) {
                    $response = $this->request($client, $origin, 'GET', '/uploads/'.$file['id'].'/'.$action);
                    $this->assertSame(200, $response->getStatusCode());
                    $this->assertSame($mime, $response->getHeaderLine('Content-Type'));
                    $this->assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
                    $this->assertSame([$policy], $response->getHeader('Content-Security-Policy'), 'Only the production CSP may apply to upload responses.');
                    $this->assertStringStartsWith($safe && $action === 'preview' ? 'inline;' : 'attachment;', $response->getHeaderLine('Content-Disposition'));
                    $this->assertSame($file['sha256'], hash('sha256', (string) $response->getBody()));
                }
            }

            $guest = $this->client($origin);
            $this->assertSame(403, $this->request($guest, $origin, 'GET', '/uploads/'.$fixture['files']['html']['id'].'/preview')->getStatusCode());
            $guestDownload = $this->request($guest, $origin, 'GET', '/uploads/'.$fixture['files']['pdf']['id'].'/download', ['headers' => ['Accept' => 'text/html']]);
            $this->assertContains($guestDownload->getStatusCode(), [302, 303]);
            $this->assertSame($origin.'/login', $guestDownload->getHeaderLine('Location'));
            $this->assertSame(401, $this->request($guest, $origin, 'GET', '/uploads/'.$fixture['files']['pdf']['id'].'/download', ['headers' => ['Accept' => 'application/json']])->getStatusCode());
            $control = $this->request($client, $origin, 'GET', '/__uploads_control', ['headers' => ['Accept' => 'text/html']]);
            $this->assertSame(200, $control->getStatusCode());
            $this->assertSame($pageResponse->getHeader('Content-Security-Policy'), $control->getHeader('Content-Security-Policy'));
            $this->assertStringContainsString("script-src 'self' 'unsafe-inline'", $control->getHeaderLine('Content-Security-Policy'));
            $this->assertStringContainsString('parent.__sec011ControlExecuted=1', (string) $control->getBody());
            foreach ([['POST', '/api/v1/uploads'], ['POST', '/api/v1/uploads/image'], ['POST', '/api/v1/uploads/zip-requests'], ['PUT', '/api/v1/uploads/'.$fixture['files']['pdf']['id']], ['GET', '/uploads/999999/preview'], ['POST', '/__uploads_control'], ['HEAD', '/__authority_health']] as [$method, $path]) {
                $this->assertSame(404, $this->request($client, $origin, $method, $path)->getStatusCode(), $method.' '.$path);
            }
            foreach ([['parentable_type' => 'client', 'parentable_id' => '999999'], ['parentable_type' => 'user', 'parentable_id' => '1']] as $query) {
                $this->assertSame(404, $this->request($client, $origin, 'GET', '/api/v1/uploads', ['query' => $query])->getStatusCode());
            }
            $this->assertSame($fixture, AuthorityHttpEnvironment::readManifest($ownedRoot.'/http.json')['uploads']);
            $input->write("status\n");
            $status = $this->message($process, $buffer, 'audit', 10);
            $this->assertSame($fixture['snapshot'], $status['uploads_snapshot']);
            $this->assertNotEmpty($status['audit']);
            foreach ($status['audit'] as $record) {
                $this->assertFalse($record['running_in_console']);
                $this->assertTrue($record['running_unit_tests']);
            }
        } finally {
            if ($process->isRunning()) {
                $input->write("stop\n");
                $input->close();
                $deadline = microtime(true) + 30;
                while ($process->isRunning() && microtime(true) < $deadline) {
                    $process->getIncrementalOutput();
                    usleep(20000);
                }
                if ($process->isRunning()) {
                    $process->stop(3);
                }
            }
            if ($started) {
                $this->assertFalse($process->isRunning());
                $this->assertSame(0, $process->getExitCode(), 'The owned Uploads launcher must finish cleanup.');
            }
            $this->assertCount(0, array_diff($this->directories(), $before), 'The owned Uploads sandbox must leave no temporary directories.');
        }
    }

    /** @param array<string, mixed> $page
     * @param  list<string>  $privateValues
     */
    private function assertSettings(array $page, string $name, array $privateValues = []): void
    {
        $this->assertSame('admin/dev/tools', $page['component']);
        $this->assertArrayNotHasKey('envVariables', $page['props']);
        $settings = $page['props']['operationalSettings'];
        $this->assertSame(['APP_NAME', 'APP_LOCALE', 'APP_FALLBACK_LOCALE', 'TELESCOPE_ENABLED'], array_keys($settings));
        $this->assertSame($name, $settings['APP_NAME']);
        $this->assertIsString($settings['APP_LOCALE']);
        $this->assertIsString($settings['APP_FALLBACK_LOCALE']);
        $this->assertIsBool($settings['TELESCOPE_ENABLED']);
        $serialized = json_encode($page, JSON_THROW_ON_ERROR);

        foreach (['synthetic-private-app-key', 'synthetic-private-provider-token', 'dev-bypass', ...$privateValues] as $private) {
            $this->assertFalse(str_contains($serialized, $private), 'Page props must not contain private environment or maintenance values.');
        }
    }

    /** @param list<string> $privateValues */
    private function assertRedirect(ResponseInterface $response, array $privateValues = []): void
    {
        $this->assertContains($response->getStatusCode(), [302, 303]);

        foreach ($privateValues as $private) {
            $this->assertFalse(str_contains($response->getHeaderLine('Location'), $private));
            $this->assertFalse(str_contains((string) $response->getBody(), $private));
        }
    }

    /**
     * @param  list<string>  $privateValues
     * @return array<string, mixed>
     */
    private function page(ResponseInterface $response, array $privateValues = []): array
    {
        $this->assertSame(200, $response->getStatusCode());
        $html = (string) $response->getBody();

        foreach ($privateValues as $private) {
            $this->assertFalse(str_contains($html, $private), 'The HTML response must not contain maintenance credentials.');
        }

        if (preg_match('/<script[^>]*data-page="app"[^>]*>(.*?)<\/script>/s', $html, $matches) !== 1) {
            throw new RuntimeException('The owned DevTools page did not return its Inertia state.');
        }

        return json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
    }

    /** @param list<string> $before */
    private function ownedRoot(array $before, string $mode = 'devtools'): string
    {
        $roots = array_values(array_filter(array_diff($this->directories(), $before), fn (string $path): bool => str_starts_with(basename($path), 'start-kit-mysql-')));
        $this->assertCount(1, $roots, 'The test must own exactly one new disposable MySQL directory.');
        $manifest = AuthorityHttpEnvironment::readManifest($roots[0].'/http.json');
        $this->assertSame($mode, $manifest['mode']);

        return $manifest['settings']['path'];
    }

    private function maintenanceSecret(string $ownedRoot): string
    {
        AuthorityHttpEnvironment::readManifest($ownedRoot.'/http.json');
        $path = $ownedRoot.'/http-devtools-storage/framework/down';

        if (! is_file($path) || is_link($path) || realpath($path) !== realpath($ownedRoot.'/http-devtools-storage/framework').DIRECTORY_SEPARATOR.'down') {
            throw new RuntimeException('The maintenance fixture must be a regular file inside the owned sandbox.');
        }

        $payload = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $secret = $payload['secret'] ?? null;

        if (! is_string($secret) || strlen($secret) < 32) {
            throw new RuntimeException('The owned maintenance fixture must contain a private random secret.');
        }

        return $secret;
    }

    private function client(string $origin, ?CookieJar $cookies = null): Client
    {
        return new Client(['base_uri' => $origin.'/', 'cookies' => $cookies ?? false, 'allow_redirects' => false, 'http_errors' => false, 'proxy' => '', 'timeout' => 10, 'connect_timeout' => 2,
            'headers' => ['Accept' => 'application/json', 'Origin' => $origin, 'Referer' => $origin.'/admin/dev/dev-tools']]);
    }

    private function assertOrigin(string $origin): void
    {
        $parts = parse_url($origin);

        if (! is_array($parts) || ($parts['scheme'] ?? null) !== 'http' || ($parts['host'] ?? null) !== AuthorityHttpEnvironment::requiredHost()
            || ! isset($parts['port']) || $parts['port'] < 1024 || $parts['port'] > 65535 || in_array($parts['port'], [3306, 33060], true)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment']) || ! in_array($parts['path'] ?? '', ['', '/'], true)) {
            throw new RuntimeException('Refusing a DevTools HTTP request outside its explicit loopback origin.');
        }
    }

    /** @param array<string, mixed> $options */
    private function request(Client $client, string $origin, string $method, string $path, array $options = []): ResponseInterface
    {
        $this->assertOrigin($origin);
        $this->assertSame($origin.'/', (string) $client->getConfig('base_uri'));

        if (! str_starts_with($path, '/') || str_starts_with($path, '//') || str_contains($path, '..') || str_contains($path, '?') || str_contains($path, '\\')) {
            throw new RuntimeException('Only explicit paths inside the DevTools sandbox are permitted.');
        }

        try {
            return $client->request($method, $path, $options);
        } catch (Throwable $exception) {
            $this->fail('Owned DevTools HTTP request failed: '.$exception::class);
        }
    }

    /** @param array<string, mixed> $fixture */
    private function login(Client $client, CookieJar $cookies, string $origin, array $fixture): void
    {
        $this->assertSame(200, $this->request($client, $origin, 'GET', '/login', ['headers' => ['Accept' => 'text/html']])->getStatusCode());
        $this->assertSame(200, $this->request($client, $origin, 'POST', '/login', ['headers' => $this->csrf($cookies), 'json' => ['email' => $fixture['email'], 'password' => $fixture['password']]])->getStatusCode());
    }

    /** @return array{X-XSRF-TOKEN: string} */
    private function csrf(CookieJar $cookies): array
    {
        $cookie = $cookies->getCookieByName('XSRF-TOKEN');

        if ($cookie === null || $cookie->getValue() === '') {
            throw new RuntimeException('The owned HTTP session did not issue its CSRF cookie.');
        }

        return ['X-XSRF-TOKEN' => rawurldecode($cookie->getValue())];
    }

    /** @return array<string, mixed> */
    private function message(Process $process, string &$buffer, string $key, int $timeout): array
    {
        $deadline = microtime(true) + $timeout;

        while (microtime(true) < $deadline) {
            $process->checkTimeout();
            $buffer .= $process->getIncrementalOutput();

            while (($newline = strpos($buffer, "\n")) !== false) {
                $message = json_decode(substr($buffer, 0, $newline), true);
                $buffer = substr($buffer, $newline + 1);

                if (is_array($message) && array_key_exists($key, $message)) {
                    return $message;
                }
            }

            if (! $process->isRunning()) {
                $this->fail('The owned DevTools launcher exited before its expected response.');
            }

            usleep(20000);
        }

        $this->fail('The owned DevTools launcher response timed out.');
    }

    /** @return list<string> */
    private function directories(): array
    {
        return [...glob(sys_get_temp_dir().'/start-kit-mysql-*', GLOB_ONLYDIR), ...glob(sys_get_temp_dir().'/start-kit-tests-*', GLOB_ONLYDIR)];
    }
}
