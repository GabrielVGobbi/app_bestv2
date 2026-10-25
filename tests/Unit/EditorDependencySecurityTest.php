<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Process\Process;

class EditorDependencySecurityTest extends TestCase
{
    public function test_merge_attributes_preserves_own_data_without_inheriting_untrusted_attributes_and_recovers(): void
    {
        if (getenv('EDITOR_NODE_TESTS') !== '1') {
            $this->markTestSkipped('The public editor dependency contract requires EDITOR_NODE_TESTS=1.');
        }

        $binary = getenv('EDITOR_NODE_BINARY');

        if (! is_string($binary) || preg_match('~\A(?:[A-Za-z]:[\\\\/]|/)~', $binary) !== 1 || ! is_file($binary) || is_link($binary)) {
            throw new RuntimeException('Set EDITOR_NODE_BINARY to an explicit absolute installed Node executable.');
        }

        $script = <<<'JS'
import { mergeAttributes } from '@tiptap/core';

const before = Object.getOwnPropertyDescriptors(Object.prototype);
const input = JSON.parse('{"__proto__":{"data-dep-inherited":"inert"},"class":"incoming"}');
const positive = mergeAttributes({ class: 'first', title: 'Café & "quoted"' }, { class: 'second', 'data-dep-marker': 'positive' });
const merged = mergeAttributes({ class: 'base', title: 'Café & "quoted"' }, input);
const ownProto = Object.getOwnPropertyDescriptor(merged, '__proto__');
const recovery = mergeAttributes({ class: 'recovery', title: 'Recuperação ç' }, { class: 'complete' });
const after = Object.getOwnPropertyDescriptors(Object.prototype);
const beforeKeys = Reflect.ownKeys(before);
const globalUnchanged = beforeKeys.length === Reflect.ownKeys(after).length && beforeKeys.every((key) => {
    const original = before[key];
    const current = after[key];
    return current && ['value', 'get', 'set', 'enumerable', 'writable', 'configurable'].every((field) => original[field] === current[field]);
});
const allowedEnv = new Set(['SYSTEMROOT', 'WINDIR', 'COMSPEC', 'PATH', 'PATHEXT', 'TEMP', 'TMP', 'USERPROFILE', 'LOCALAPPDATA', 'APPDATA']);
const allowedEnvironmentKey = (key) => allowedEnv.has(key.toUpperCase()) || (process.platform === 'win32' && key.toUpperCase() === 'PROMPT' && process.env[key] === '$P$G');

process.stdout.write(JSON.stringify({
    onlyAllowedEnvironment: Object.keys(process.env).every(allowedEnvironmentKey),
    unexpectedEnvironmentKeys: Object.keys(process.env).filter((key) => !allowedEnvironmentKey(key)).sort(),
    promptControlled: process.platform !== 'win32' || process.env.PROMPT === '$P$G',
    inputOwnProto: Object.hasOwn(input, '__proto__'),
    positive: { class: positive.class, title: positive.title, marker: positive['data-dep-marker'] },
    recovery: { class: recovery.class, title: recovery.title },
    globalUnchanged,
    resultUsesObjectPrototype: Object.getPrototypeOf(merged) === Object.prototype,
    inheritedMarkerPresent: 'data-dep-inherited' in merged,
    resultOwnProtoData: ownProto !== undefined && ownProto.value === input.__proto__ && ownProto.get === undefined && ownProto.set === undefined,
    resultClass: merged.class,
    resultTitle: merged.title,
}) + '\n');
JS;

        $process = new Process([$binary, '--input-type=module'], dirname(__DIR__, 2), $this->nodeEnvironment());
        $process->setTimeout(10);
        $process->setInput($script);
        $process->run();
        $this->assertSame(0, $process->getExitCode(), 'The public helper must load and report observations before domain assertions.');
        $this->assertSame('', $process->getErrorOutput());
        $observed = json_decode($process->getOutput(), true, 32, JSON_THROW_ON_ERROR);
        $this->assertIsArray($observed);
        $this->assertTrue($observed['onlyAllowedEnvironment'], json_encode($observed['unexpectedEnvironmentKeys'], JSON_THROW_ON_ERROR));
        $this->assertTrue($observed['promptControlled']);
        $this->assertTrue($observed['inputOwnProto']);
        $this->assertSame(['class' => 'first second', 'title' => 'Café & "quoted"', 'marker' => 'positive'], $observed['positive']);
        $this->assertSame(['class' => 'recovery complete', 'title' => 'Recuperação ç'], $observed['recovery']);
        $this->assertTrue($observed['globalUnchanged']);
        $this->assertTrue($observed['resultUsesObjectPrototype'], 'Untrusted own data must not replace the merged object prototype.');
        $this->assertFalse($observed['inheritedMarkerPresent']);
        $this->assertTrue($observed['resultOwnProtoData'], 'The fixed helper may preserve __proto__ as an own data property.');
        $this->assertSame('base incoming', $observed['resultClass']);
        $this->assertSame('Café & "quoted"', $observed['resultTitle']);
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
