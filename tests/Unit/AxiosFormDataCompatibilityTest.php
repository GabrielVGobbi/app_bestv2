<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Process\Process;

class AxiosFormDataCompatibilityTest extends TestCase
{
    public function test_public_axios_and_node_multipart_contracts_preserve_data_cancel_and_recover(): void
    {
        if (getenv('AXIOS_NODE_TESTS') !== '1') {
            $this->markTestSkipped('The dependency compatibility contract requires AXIOS_NODE_TESTS=1.');
        }
        $binary = getenv('AXIOS_NODE_BINARY');
        if (! is_string($binary) || preg_match('~\A(?:[A-Za-z]:[\\\\/]|/)~', $binary) !== 1 || ! is_file($binary) || is_link($binary)) {
            throw new RuntimeException('Set AXIOS_NODE_BINARY to an explicit absolute installed Node executable.');
        }
        $script = <<<'JS'
import axios, { AxiosHeaders, CanceledError } from 'axios';
import FormData from 'form-data';
import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
const observations = [];
const client = axios.create({
    baseURL: 'https://example.test/base/',
    headers: { Accept: 'application/json', 'X-Fixture': 'compatibility' },
    adapter: async (config) => {
        observations.push({ method: config.method, data: config.data, accept: config.headers.get('Accept'), marker: config.headers.get('X-Fixture') });
        return { data: config.data ?? '{"label":"Recuperação ç"}', status: 200, statusText: 'OK', headers: new AxiosHeaders({ 'Content-Type': 'application/json' }), config };
    },
});
const input = { label: 'Café & "quoted"', lines: 'um\ndois' };
const positive = await client.post('/records', input);
const uri = new URL(client.getUri({ url: '/records', params: { search: 'Café & "quoted"', page: 1 } }));
const header = AxiosHeaders.from({ 'x-fixture': 'one' });
header.set('X-Fixture', 'two');
const cancellation = new CanceledError('Synthetic cancellation');
const controller = new AbortController();
controller.abort();
let canceled = null;
const adapterCallsBeforeCancel = observations.length;
try {
    await client.get('/canceled', { signal: controller.signal });
} catch (error) {
    canceled = { code: error.code, isCancel: axios.isCancel(error) };
}
const adapterCallsAfterCancel = observations.length;
const recovery = await client.get('/recovery');

const form = new FormData();
const content = Buffer.from('Café\n& "quoted"', 'utf8');
form.append('note', 'Olá ç');
form.append('file', content, { filename: 'Café"transport.txt', contentType: 'text/plain' });
const boundary = form.getBoundary();
const body = form.getBuffer();
const bodyText = body.toString('utf8');
const nextForm = new FormData();
nextForm.append('note', 'Recuperação ç');
const nextBoundary = nextForm.getBoundary();
const nextBody = nextForm.getBuffer().toString('utf8');
const allowedEnv = new Set(['SYSTEMROOT', 'WINDIR', 'COMSPEC', 'PATH', 'PATHEXT', 'TEMP', 'TMP', 'USERPROFILE', 'LOCALAPPDATA', 'APPDATA']);
const allowedKey = (key) => allowedEnv.has(key.toUpperCase()) || (process.platform === 'win32' && key.toUpperCase() === 'PROMPT' && process.env[key] === '$P$G');

process.stdout.write(JSON.stringify({
    versions: { axios: axios.VERSION, formData: require('form-data/package.json').version },
    onlyAllowedEnvironment: Object.keys(process.env).every(allowedKey),
    unexpectedEnvironmentKeys: Object.keys(process.env).filter((key) => !allowedKey(key)).sort(),
    promptControlled: process.platform !== 'win32' || process.env.PROMPT === '$P$G',
    positiveData: positive.data,
    firstRequest: observations[0],
    uri: { origin: uri.origin, pathname: uri.pathname, search: uri.searchParams.get('search'), page: uri.searchParams.get('page') },
    normalizedHeader: header.get('x-fixture'),
    explicitCancellation: { code: cancellation.code, isCancel: axios.isCancel(cancellation) },
    canceled,
    adapterCallsBeforeCancel,
    adapterCallsAfterCancel,
    adapterCallsAfterRecovery: observations.length,
    recoveryData: recovery.data,
    multipart: {
        headerMatchesBoundary: form.getHeaders()['content-type'] === `multipart/form-data; boundary=${boundary}`,
        lengthMatches: form.getLengthSync() === body.length,
        opensBoundary: bodyText.startsWith(`--${boundary}\r\n`),
        closesBoundary: bodyText.endsWith(`--${boundary}--\r\n`),
        contentPreserved: body.includes(content),
        unicodeFieldPreserved: bodyText.includes('name="note"\r\n\r\nOlá ç\r\n'),
        quoteEscaped: bodyText.includes('filename="Café%22transport.txt"\r\n'),
        rawQuoteAbsent: !bodyText.includes('filename="Café"transport.txt"'),
    },
    multipartRecovery: nextBody.includes('name="note"\r\n\r\nRecuperação ç\r\n') && nextBody.endsWith(`--${nextBoundary}--\r\n`) && nextForm.getLengthSync() === Buffer.byteLength(nextBody),
}) + '\n');
JS;
        $process = new Process([$binary, '--input-type=module'], dirname(__DIR__, 2), $this->nodeEnvironment());
        $process->setTimeout(10);
        $process->setInput($script);
        $process->run();
        $this->assertSame(0, $process->getExitCode(), 'The dependencies must load and report observations before domain assertions.');
        $this->assertSame('', $process->getErrorOutput());
        $observed = json_decode($process->getOutput(), true, 32, JSON_THROW_ON_ERROR);
        $this->assertIsArray($observed);
        $this->assertIsString($observed['versions']['axios']);
        $this->assertNotSame('', $observed['versions']['axios']);
        $this->assertIsString($observed['versions']['formData']);
        $this->assertNotSame('', $observed['versions']['formData']);
        $this->assertTrue($observed['onlyAllowedEnvironment'], json_encode($observed['unexpectedEnvironmentKeys'], JSON_THROW_ON_ERROR));
        $this->assertTrue($observed['promptControlled']);
        $this->assertSame(['label' => 'Café & "quoted"', 'lines' => "um\ndois"], $observed['positiveData']);
        $this->assertSame('post', $observed['firstRequest']['method']);
        $this->assertSame($observed['positiveData'], json_decode($observed['firstRequest']['data'], true, 32, JSON_THROW_ON_ERROR));
        $this->assertSame('application/json', $observed['firstRequest']['accept']);
        $this->assertSame('compatibility', $observed['firstRequest']['marker']);
        $this->assertSame(['origin' => 'https://example.test', 'pathname' => '/base/records', 'search' => 'Café & "quoted"', 'page' => '1'], $observed['uri']);
        $this->assertSame('two', $observed['normalizedHeader']);
        $this->assertSame(['code' => 'ERR_CANCELED', 'isCancel' => true], $observed['explicitCancellation']);
        $this->assertSame(['code' => 'ERR_CANCELED', 'isCancel' => true], $observed['canceled']);
        $this->assertSame(1, $observed['adapterCallsBeforeCancel']);
        $this->assertSame(1, $observed['adapterCallsAfterCancel']);
        $this->assertSame(2, $observed['adapterCallsAfterRecovery']);
        $this->assertSame(['label' => 'Recuperação ç'], $observed['recoveryData']);
        foreach (['headerMatchesBoundary', 'lengthMatches', 'opensBoundary', 'closesBoundary', 'contentPreserved', 'unicodeFieldPreserved', 'quoteEscaped', 'rawQuoteAbsent'] as $contract) {
            $this->assertTrue($observed['multipart'][$contract], 'Multipart contract failed: '.$contract);
        }
        $this->assertTrue($observed['multipartRecovery']);
    }

    /** @return array<string, string|false> */
    private function nodeEnvironment(): array
    {
        $inherited = getenv();
        $environment = [];
        $allowed = ['SYSTEMROOT', 'WINDIR', 'COMSPEC', 'PATH', 'PATHEXT', 'TEMP', 'TMP', 'USERPROFILE', 'LOCALAPPDATA', 'APPDATA'];
        foreach ($inherited + $_ENV + $_SERVER as $key => $value) {
            if (is_string($key)) {
                $environment[$key] = in_array(strtoupper($key), $allowed, true) && is_string($value) ? $value : false;
            }
        }
        if (DIRECTORY_SEPARATOR === '\\') {
            $environment['PROMPT'] = '$P$G';
        }

        return $environment;
    }
}
