<?php

namespace Tests\Feature;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\AuthorityHttpEnvironment;
use Throwable;

class EditorHttpIntegrationTest extends TestCase
{
    public function test_editor_http_scope_serves_real_read_consumers_with_session_authority_and_preserves_its_neutral_fixture(): void
    {
        if (getenv('AUTHORITY_HTTP_TESTS') !== '1' || getenv('AUTHORITY_MYSQL_TESTS') !== '1' || getenv('AUTHORITY_HTTP_EDITOR_TESTS') !== '1') {
            $this->markTestSkipped('Editor HTTP validation requires all three explicit sandbox opt-ins.');
        }

        foreach (['AUTHORITY_HTTP_DEVTOOLS_TESTS', 'AUTHORITY_HTTP_TIMELINE_TESTS', 'AUTHORITY_HTTP_UPLOADS_TESTS'] as $flag) {
            if (getenv($flag) === '1') {
                throw new RuntimeException('The Editor HTTP profile must not be combined with another extended scope.');
            }
        }

        $host = AuthorityHttpEnvironment::requiredHost();
        $before = $this->directories();
        $input = new InputStream;
        $process = new Process([PHP_BINARY, dirname(__DIR__).'/authority-http-server.php'], dirname(__DIR__, 2), [
            'AUTHORITY_HTTP_TESTS' => '1', 'AUTHORITY_MYSQL_TESTS' => '1', 'AUTHORITY_HTTP_EDITOR_TESTS' => '1',
            'AUTHORITY_HTTP_DEVTOOLS_TESTS' => '0', 'AUTHORITY_HTTP_TIMELINE_TESTS' => '0', 'AUTHORITY_HTTP_UPLOADS_TESTS' => '0',
            'AUTHORITY_HTTP_HOST' => $host, 'APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => '',
        ]);
        $process->setInput($input);
        $process->setTimeout(240);
        $buffer = '';
        $started = false;

        try {
            $process->start();
            $started = true;
            $ready = $this->message($process, $buffer, 'url', 180);
            $this->assertSame('editor', $ready['mode'] ?? null);
            $this->assertNull($ready['timeline']);
            $this->assertNull($ready['uploads']);
            $origin = $ready['url'];
            $this->assertIsString($origin);
            $this->assertOrigin($origin);
            $ownedRoot = $this->ownedRoot($before);
            $fixture = $ready['editor'];
            $this->assertSame(['client_id', 'client_uuid', 'snapshot'], array_keys($fixture));
            $this->assertSame(hash('sha256', '[]'), $fixture['snapshot']);
            $this->assertSame($fixture, AuthorityHttpEnvironment::readManifest($ownedRoot.'/http.json')['editor']);
            $api = '/api/v1/timeline/client/'.$fixture['client_uuid'];
            $pages = ['/admin/dev/components/rich-text-editor' => 'admin/dev/components/rich-text-editor', '/admin/dev/components/activity-timeline' => 'admin/dev/components/activity-timeline'];
            $guest = $this->client($origin);

            foreach ($pages as $path => $component) {
                $response = $this->request($guest, $origin, 'GET', $path, ['headers' => ['Accept' => 'text/html']]);
                $this->assertContains($response->getStatusCode(), [302, 303]);
                $this->assertSame($origin.'/login', $response->getHeaderLine('Location'));
            }
            $this->assertSame(401, $this->request($guest, $origin, 'GET', $api)->getStatusCode());

            $ordinaryJar = new CookieJar;
            $ordinary = $this->client($origin, $ordinaryJar);
            $this->login($ordinary, $ordinaryJar, $origin, $ready['fixtures']['ordinary']);

            foreach (array_keys($pages) as $path) {
                $this->assertSame(403, $this->request($ordinary, $origin, 'GET', $path)->getStatusCode());
            }

            $jar = new CookieJar;
            $developer = $this->client($origin, $jar);
            $this->assertSame(200, $this->request($developer, $origin, 'GET', '/login', ['headers' => ['Accept' => 'text/html']])->getStatusCode());
            $this->assertSame(419, $this->request($developer, $origin, 'POST', '/login', ['json' => [
                'email' => $ready['fixtures']['developer']['email'], 'password' => $ready['fixtures']['developer']['password'],
            ]])->getStatusCode());
            $this->login($developer, $jar, $origin, $ready['fixtures']['developer']);

            foreach ($pages as $path => $component) {
                $page = $this->page($this->request($developer, $origin, 'GET', $path, ['headers' => ['Accept' => 'text/html']]));
                $this->assertSame($component, $page['component']);

                if ($path === '/admin/dev/components/activity-timeline') {
                    $this->assertSame('client', $page['props']['defaultSubject']['type']);
                    $this->assertSame($fixture['client_uuid'], $page['props']['defaultSubject']['id']);
                    $this->assertSame('DEP001 Editor Fixture', $page['props']['defaultSubject']['label']);
                }
            }

            $response = $this->request($developer, $origin, 'GET', $api, ['query' => ['limit' => '50']]);
            $this->assertSame(200, $response->getStatusCode());
            $timeline = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame('client', $timeline['subject']['type']);
            $this->assertSame($fixture['client_uuid'], $timeline['subject']['uuid']);
            $this->assertSame('DEP001 Editor Fixture', $timeline['subject']['label']);
            $this->assertIsArray($timeline['data']);

            foreach ($timeline['data'] as $item) {
                $this->assertNotSame('comment', $item['type']);
            }

            $build = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/public/build/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
            $asset = '/build/'.$build['resources/js/app.tsx']['file'];
            $this->assertNotNull(AuthorityHttpEnvironment::assetPath($asset, dirname(__DIR__, 2).'/public'));
            $this->assertSame(200, $this->request($developer, $origin, 'GET', $asset)->getStatusCode());

            foreach ([['POST', $api.'/comments'], ['PUT', '/api/v1/timeline/comments/1'], ['DELETE', '/api/v1/timeline/comments/1'], ['POST', '/api/v1/uploads/image'], ['POST', '/admin/dev/dev-tools/env/update'], ['GET', '/roles'], ['GET', '/__timeline_control'], ['GET', '/__uploads_control'], ['POST', $asset], ['HEAD', '/__authority_health']] as [$method, $path]) {
                $this->assertSame(404, $this->request($developer, $origin, $method, $path, ['headers' => $this->csrf($jar)])->getStatusCode(), $method.' '.$path);
            }

            $otherUuid = $fixture['client_uuid'] === '30000000-0000-4000-8000-000000000002' ? '30000000-0000-4000-8000-000000000003' : '30000000-0000-4000-8000-000000000002';
            $this->assertSame(404, $this->request($developer, $origin, 'GET', '/api/v1/timeline/client/'.$otherUuid)->getStatusCode());
            $this->assertSame(404, $this->request($developer, $origin, 'GET', $api, ['query' => ['limit' => '101']])->getStatusCode());
            $this->assertSame(404, $this->request($developer, $origin, 'GET', '/admin/dev/components/rich-text-editor', ['query' => ['unexpected' => '1']])->getStatusCode());
            $this->assertSame(421, $this->request($developer, $origin, 'GET', '/admin/dev/components/rich-text-editor', ['headers' => ['Host' => 'other.authority.test']])->getStatusCode());
            $logout = $this->request($developer, $origin, 'POST', '/logout', ['headers' => $this->csrf($jar)]);
            $this->assertSame(204, $logout->getStatusCode());
            $this->assertSame('', (string) $logout->getBody());
            $this->assertSame(401, $this->request($developer, $origin, 'GET', $api)->getStatusCode());
            $this->login($developer, $jar, $origin, $ready['fixtures']['developer']);
            $this->assertSame('admin/dev/components/rich-text-editor', $this->page($this->request($developer, $origin, 'GET', '/admin/dev/components/rich-text-editor', ['headers' => ['Accept' => 'text/html']]))['component']);
            $this->assertSame($fixture, AuthorityHttpEnvironment::readManifest($ownedRoot.'/http.json')['editor']);

            $input->write("status\n");
            $status = $this->message($process, $buffer, 'audit', 10);
            $this->assertSame($fixture['snapshot'], $status['editor_snapshot']);
            $this->assertNotEmpty($status['audit']);

            foreach ($status['audit'] as $record) {
                $this->assertFalse($record['running_in_console']);
                $this->assertTrue($record['running_unit_tests']);

                if ($record['method'] !== 'GET') {
                    $this->assertSame('POST', $record['method']);
                    $this->assertContains($record['path'], ['/login', '/logout']);
                }
            }
        } finally {
            if ($this->processIsRunning($process)) {
                $input->write("stop\n");
                $input->close();
                $deadline = microtime(true) + 30;

                while ($this->processIsRunning($process) && microtime(true) < $deadline) {
                    $process->getIncrementalOutput();
                    usleep(20000);
                }

                if ($this->processIsRunning($process)) {
                    $process->stop(3);
                }
            }

            if ($started) {
                $this->assertFalse($this->processIsRunning($process));
                $this->assertSame(0, $process->getExitCode(), 'The owned Editor launcher must finish cleanup.');
            }

            $this->assertCount(0, array_diff($this->directories(), $before), 'The owned Editor sandbox must leave no temporary directories.');
        }
    }

    /** @return array<string, mixed> */
    private function page(ResponseInterface $response): array
    {
        $this->assertSame(200, $response->getStatusCode());

        if (preg_match('/<script[^>]*data-page="app"[^>]*>(.*?)<\/script>/s', (string) $response->getBody(), $matches) !== 1) {
            throw new RuntimeException('The owned Editor page did not return its Inertia state.');
        }

        return json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
    }

    /** @param list<string> $before */
    private function ownedRoot(array $before): string
    {
        $roots = array_values(array_filter(array_diff($this->directories(), $before), fn (string $path): bool => str_starts_with(basename($path), 'start-kit-mysql-')));
        $this->assertCount(1, $roots);
        $manifest = AuthorityHttpEnvironment::readManifest($roots[0].'/http.json');
        $this->assertSame('editor', $manifest['mode']);

        return $manifest['settings']['path'];
    }

    private function client(string $origin, ?CookieJar $cookies = null): Client
    {
        return new Client(['base_uri' => $origin.'/', 'cookies' => $cookies ?? false, 'allow_redirects' => false, 'http_errors' => false, 'proxy' => '', 'timeout' => 10, 'connect_timeout' => 2,
            'headers' => ['Accept' => 'application/json', 'Origin' => $origin, 'Referer' => $origin.'/admin/dev/components/rich-text-editor']]);
    }

    private function assertOrigin(string $origin): void
    {
        $parts = parse_url($origin);

        if (! is_array($parts) || ($parts['scheme'] ?? null) !== 'http' || ($parts['host'] ?? null) !== AuthorityHttpEnvironment::requiredHost()
            || ! isset($parts['port']) || filter_var($parts['port'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1024, 'max_range' => 65535]]) === false || in_array($parts['port'], [3306, 33060], true)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment']) || ! in_array($parts['path'] ?? '', ['', '/'], true)) {
            throw new RuntimeException('Refusing an Editor HTTP request outside its explicit loopback origin.');
        }
    }

    /** @param array<string, mixed> $options */
    private function request(Client $client, string $origin, string $method, string $path, array $options = []): ResponseInterface
    {
        $this->assertOrigin($origin);
        $this->assertSame($origin.'/', (string) $client->getConfig('base_uri'));

        if (! str_starts_with($path, '/') || str_starts_with($path, '//') || str_contains($path, '..') || str_contains($path, '?') || str_contains($path, '\\')) {
            throw new RuntimeException('Only explicit paths inside the Editor sandbox are permitted.');
        }

        try {
            return $client->request($method, $path, $options);
        } catch (Throwable $exception) {
            $this->fail('Owned Editor HTTP request failed: '.$exception::class);
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

            if (! $this->processIsRunning($process)) {
                $this->fail('The owned Editor launcher exited before its expected response.');
            }

            usleep(20000);
        }

        $this->fail('The owned Editor launcher response timed out.');
    }

    /**
     * The independently running child can change state between observations.
     *
     * @phpstan-impure
     */
    private function processIsRunning(Process $process): bool
    {
        return $process->isRunning();
    }

    /** @return list<string> */
    private function directories(): array
    {
        return [...glob(sys_get_temp_dir().'/start-kit-mysql-*', GLOB_ONLYDIR), ...glob(sys_get_temp_dir().'/start-kit-tests-*', GLOB_ONLYDIR)];
    }
}
