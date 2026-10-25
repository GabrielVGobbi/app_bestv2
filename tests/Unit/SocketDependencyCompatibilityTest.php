<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Process\Process;

class SocketDependencyCompatibilityTest extends TestCase
{
    public function test_disconnected_socket_dependencies_preserve_bounded_packets_and_recover(): void
    {
        if (getenv('SOCKET_NODE_TESTS') !== '1') {
            $this->markTestSkipped('The socket compatibility contract requires SOCKET_NODE_TESTS=1.');
        }
        $binary = getenv('SOCKET_NODE_BINARY');
        if (! is_string($binary) || preg_match('~\A(?:[A-Za-z]:[\\\\/]|/)~', $binary) !== 1 || ! is_file($binary) || is_link($binary)) {
            throw new RuntimeException('Set SOCKET_NODE_BINARY to an explicit absolute installed Node executable.');
        }
        $script = <<<'JS'
import { Encoder, Decoder, PacketType, protocol as parserProtocol } from 'socket.io-parser';
import { Manager } from 'socket.io-client';
import { protocol as engineProtocol, Transport, transports } from 'engine.io-client';
import { WebSocketServer } from 'ws';

const encoder = new Encoder();
const decoder = new Decoder({ maxAttachments: 1 });
const recoveredDecoder = new Decoder({ maxAttachments: 1 });
const decoded = [];
const recovered = [];
decoder.on('decoded', (packet) => decoded.push(packet));
recoveredDecoder.on('decoded', (packet) => recovered.push(packet));
const event = { type: PacketType.EVENT, nsp: '/fixture', id: 7, data: ['fixture', 'Café & "quoted"\num\ndois'] };
const acknowledgment = { type: PacketType.ACK, nsp: '/fixture', id: 7, data: ['Recebido ç'] };
const binary = Buffer.from('0123456789abcdef', 'utf8');
const binaryFrames = encoder.encode({ type: PacketType.EVENT, nsp: '/fixture', id: 8, data: ['binary-fixture', binary] });
const servers = [];
const addressResult = (server) => {
    try {
        return { value: server.address() };
    } catch (error) {
        return { name: error.name, message: error.message };
    }
};
let manager;
let result;

try {
    for (const packet of [event, acknowledgment]) {
        for (const frame of encoder.encode(packet)) decoder.add(frame);
    }
    for (const frame of binaryFrames) decoder.add(frame);
    let syntaxError = null;
    try {
        decoder.add('2["fixture",');
    } catch (error) {
        syntaxError = { name: error.name, message: error.message };
    } finally {
        decoder.destroy();
    }
    for (const frame of encoder.encode(acknowledgment)) recoveredDecoder.add(frame);

    manager = new Manager('http://example.test', { autoConnect: false, reconnection: false });
    const disconnectedManager = {
        autoConnect: manager.opts.autoConnect,
        reconnection: manager.reconnection(),
        engineAbsent: manager.engine === undefined,
    };
    let listeningEvents = 0;
    const options = { noServer: true, path: '/fixture', maxPayload: 1024, perMessageDeflate: false };
    const server = new WebSocketServer(options, () => { listeningEvents += 1; });
    servers.push(server);
    let conflict = null;
    try {
        new WebSocketServer({ ...options, port: 0 }, () => { listeningEvents += 1; });
    } catch (error) {
        conflict = { name: error.name, message: error.message };
    }
    const recoveryServer = new WebSocketServer(options, () => { listeningEvents += 1; });
    servers.push(recoveryServer);
    result = {
        parserProtocol,
        engineProtocol,
        event: decoded[0],
        acknowledgment: decoded[1],
        decodedCount: decoded.length,
        binary: {
            frameCount: binaryFrames.length,
            attachmentCount: binaryFrames.filter(Buffer.isBuffer).length,
            attachmentBytes: binaryFrames.filter(Buffer.isBuffer).map((frame) => frame.length),
            type: decoded[2].type,
            namespace: decoded[2].nsp,
            id: decoded[2].id,
            event: decoded[2].data[0],
            isBuffer: Buffer.isBuffer(decoded[2].data[1]),
            text: decoded[2].data[1].toString('utf8'),
        },
        syntaxError,
        recovered,
        manager: disconnectedManager,
        engineExports: {
            transport: typeof Transport === 'function',
            polling: typeof transports.polling === 'function',
            websocket: typeof transports.websocket === 'function',
            webtransport: typeof transports.webtransport === 'function',
        },
        websocket: {
            fixture: server.shouldHandle({ url: '/fixture' }),
            fixtureQuery: server.shouldHandle({ url: '/fixture?label=normal' }),
            other: server.shouldHandle({ url: '/other' }),
            address: addressResult(server),
            clientCount: server.clients.size,
            conflict,
            recovery: recoveryServer.shouldHandle({ url: '/fixture' }),
            recoveryOther: recoveryServer.shouldHandle({ url: '/other' }),
            recoveryAddress: addressResult(recoveryServer),
        },
    };
    manager.disconnect();
    result.manager.engineAbsentAfterDisconnect = manager.engine === undefined;
    result.websocket.listeningEvents = listeningEvents;
} finally {
    decoder.destroy();
    recoveredDecoder.destroy();
    if (manager) manager.disconnect();
    await Promise.all(servers.map((server) => new Promise((resolve, reject) => {
        server.close((error) => error ? reject(error) : resolve());
    })));
}

const allowedEnv = new Set(['SYSTEMROOT', 'WINDIR', 'COMSPEC', 'PATH', 'PATHEXT', 'TEMP', 'TMP', 'USERPROFILE', 'LOCALAPPDATA', 'APPDATA']);
const allowedKey = (key) => allowedEnv.has(key.toUpperCase()) || (process.platform === 'win32' && key.toUpperCase() === 'PROMPT' && process.env[key] === '$P$G');
process.stdout.write(JSON.stringify({
    ...result,
    closedServers: servers.length,
    onlyAllowedEnvironment: Object.keys(process.env).every(allowedKey),
    unexpectedEnvironmentKeys: Object.keys(process.env).filter((key) => !allowedKey(key)).sort(),
    promptControlled: process.platform !== 'win32' || process.env.PROMPT === '$P$G',
}) + '\n');
JS;
        $process = new Process([$binary, '--input-type=module'], dirname(__DIR__, 2), $this->nodeEnvironment());
        $process->setTimeout(10);
        $process->setInput($script);
        $process->run();
        $this->assertSame(0, $process->getExitCode(), 'The disconnected socket APIs must report observations: '.$process->getErrorOutput().' '.$process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
        $observed = json_decode($process->getOutput(), true, 32, JSON_THROW_ON_ERROR);
        $this->assertIsArray($observed);
        $this->assertTrue($observed['onlyAllowedEnvironment'], json_encode($observed['unexpectedEnvironmentKeys'], JSON_THROW_ON_ERROR));
        $this->assertTrue($observed['promptControlled']);
        $this->assertSame(5, $observed['parserProtocol']);
        $this->assertSame(4, $observed['engineProtocol']);
        $this->assertSame(['type' => 2, 'nsp' => '/fixture', 'id' => 7, 'data' => ['fixture', "Café & \"quoted\"\num\ndois"]], $observed['event']);
        $acknowledgment = ['type' => 3, 'nsp' => '/fixture', 'id' => 7, 'data' => ['Recebido ç']];
        $this->assertSame($acknowledgment, $observed['acknowledgment']);
        $this->assertSame(3, $observed['decodedCount']);
        $this->assertSame([
            'frameCount' => 2, 'attachmentCount' => 1, 'attachmentBytes' => [16],
            'type' => 2, 'namespace' => '/fixture', 'id' => 8, 'event' => 'binary-fixture',
            'isBuffer' => true, 'text' => '0123456789abcdef',
        ], $observed['binary']);
        $this->assertSame(['name' => 'Error', 'message' => 'invalid payload'], $observed['syntaxError']);
        $this->assertSame([$acknowledgment], $observed['recovered']);
        $this->assertSame(['autoConnect' => false, 'reconnection' => false, 'engineAbsent' => true, 'engineAbsentAfterDisconnect' => true], $observed['manager']);
        $this->assertSame(['transport' => true, 'polling' => true, 'websocket' => true, 'webtransport' => true], $observed['engineExports']);
        $this->assertTrue($observed['websocket']['fixture']);
        $this->assertTrue($observed['websocket']['fixtureQuery']);
        $this->assertFalse($observed['websocket']['other']);
        $addressError = ['name' => 'Error', 'message' => 'The server is operating in "noServer" mode'];
        $this->assertSame($addressError, $observed['websocket']['address']);
        $this->assertSame(0, $observed['websocket']['clientCount']);
        $this->assertSame(['name' => 'TypeError', 'message' => 'One and only one of the "port", "server", or "noServer" options must be specified'], $observed['websocket']['conflict']);
        $this->assertTrue($observed['websocket']['recovery']);
        $this->assertFalse($observed['websocket']['recoveryOther']);
        $this->assertSame($addressError, $observed['websocket']['recoveryAddress']);
        $this->assertSame(0, $observed['websocket']['listeningEvents']);
        $this->assertSame(2, $observed['closedServers']);
    }

    /** @return array<string, string|false> */
    private function nodeEnvironment(): array
    {
        $environment = [];
        $allowed = ['SYSTEMROOT', 'WINDIR', 'COMSPEC', 'PATH', 'PATHEXT', 'TEMP', 'TMP', 'USERPROFILE', 'LOCALAPPDATA', 'APPDATA'];
        foreach (getenv() + $_ENV + $_SERVER as $key => $value) {
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
