<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Process\Process;

class DevBuildCompatibilityTest extends TestCase
{
    public function test_bounded_public_build_apis_preserve_normal_inputs_and_recover_from_syntax_errors(): void
    {
        if (getenv('DEV_BUILD_NODE_TESTS') !== '1') {
            $this->markTestSkipped('The build compatibility contract requires DEV_BUILD_NODE_TESTS=1.');
        }
        $binary = getenv('DEV_BUILD_NODE_BINARY');
        if (! is_string($binary) || preg_match('~\A(?:[A-Za-z]:[\\\\/]|/)~', $binary) !== 1 || ! is_file($binary) || is_link($binary)) {
            throw new RuntimeException('Set DEV_BUILD_NODE_BINARY to an explicit absolute installed Node executable.');
        }
        $script = <<<'JS'
import * as babel from '@babel/core';
import { getCompatibleVersions } from 'baseline-browser-mapping';
import browserslist from 'browserslist';
import concurrently from 'concurrently';
import shellQuote from 'shell-quote';
import yaml from 'js-yaml';
import { nanoid } from 'nanoid';
import postcss from 'postcss';
import sourceMap from 'source-map-js';
import { mergeConfig, normalizePath, version as viteVersion } from 'vite';
import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
const oldConsumer = createRequire(require.resolve('@eslint/eslintrc'));
const oldGlob = createRequire(oldConsumer.resolve('minimatch'));
const typedConsumer = createRequire(require.resolve('@typescript-eslint/typescript-estree'));
const typedGlob = createRequire(typedConsumer.resolve('minimatch'));
const expandOld = oldGlob('brace-expansion');
const { expand: expandTyped } = typedGlob('brace-expansion');
const failureName = (operation) => {
    try {
        operation();
        return null;
    } catch (error) {
        return error.name;
    }
};
const babelOptions = { filename: 'fixture.js', sourceFileName: 'fixture.js', configFile: false, babelrc: false, browserslistConfigFile: false, ast: true, sourceMaps: true, plugins: [], presets: [] };
const transform = () => babel.transformSync('const label = "Café & quoted";', babelOptions);
const transformed = transform();
const babelError = failureName(() => babel.transformSync('const =;', babelOptions));
const babelRecovery = transform();
const baseline = getCompatibleVersions({ widelyAvailableOnDate: '2024-06-01' });
const browserTargets = browserslist(['chrome 120', 'firefox 121']);
const browserError = failureName(() => browserslist(['fixture-invalid-browser-query']));
const browserRecovery = browserslist(['chrome 120', 'firefox 121']);
const argumentsOnly = ['npm', 'run', 'dev', 'Café & "quoted"', 'two words'];
const yamlInput = 'label: "Café & quoted"\nitems:\n  - one\n  - two\n';
const yamlPositive = yaml.load(yamlInput);
const yamlError = failureName(() => yaml.load('label: [one\n'));
const yamlRecovery = yaml.load(yaml.dump(yamlPositive));
const cssInput = '/* Café */\n.fixture { color: blue; }\n';
const cssResult = postcss([]).process(cssInput, { from: 'fixture.css', to: 'result.css', map: { inline: false, annotation: false, sourcesContent: true } });
const cssError = failureName(() => postcss.parse('.fixture {', { from: 'fixture.css' }));
const cssRecovery = postcss.parse(cssInput, { from: 'fixture.css' });
const map = new sourceMap.SourceMapGenerator({ file: 'result.css' });
map.addMapping({ generated: { line: 1, column: 0 }, original: { line: 3, column: 2 }, source: 'fixture.css' });
map.setSourceContent('fixture.css', '/* Café */\n\n  .fixture { color: blue; }\n');
const mapReader = new sourceMap.SourceMapConsumer(map.toString());
const original = mapReader.originalPositionFor({ line: 1, column: 0 });
const identifier = nanoid(12);
const config = mergeConfig({ base: '/base/', define: { LABEL: '"Café"' }, css: { devSourcemap: true } }, { base: '/assets/', define: { MODE: '"fixture"' } });
const allowedEnv = new Set(['SYSTEMROOT', 'WINDIR', 'COMSPEC', 'PATH', 'PATHEXT', 'TEMP', 'TMP', 'USERPROFILE', 'LOCALAPPDATA', 'APPDATA']);
const allowedKey = (key) => allowedEnv.has(key.toUpperCase()) || (process.platform === 'win32' && key.toUpperCase() === 'PROMPT' && process.env[key] === '$P$G');

process.stdout.write(JSON.stringify({
    versions: { babel: babel.version, vite: viteVersion, oldBrace: oldGlob('brace-expansion/package.json').version, typedBrace: typedGlob('brace-expansion/package.json').version },
    onlyAllowedEnvironment: Object.keys(process.env).every(allowedKey),
    unexpectedEnvironmentKeys: Object.keys(process.env).filter((key) => !allowedKey(key)).sort(),
    promptControlled: process.platform !== 'win32' || process.env.PROMPT === '$P$G',
    babel: {
        literal: transformed.ast.program.body[0].declarations[0].init.value,
        codePresent: typeof transformed.code === 'string' && transformed.code.includes('const label'),
        mapContent: transformed.map.sourcesContent,
        error: babelError,
        recovery: babelRecovery.ast.program.body[0].declarations[0].init.value,
    },
    baseline: {
        populated: baseline.length > 0,
        chrome: baseline.some((entry) => entry.browser === 'chrome' && typeof entry.version === 'string' && entry.version.length > 0),
        firefox: baseline.some((entry) => entry.browser === 'firefox' && typeof entry.version === 'string' && entry.version.length > 0),
    },
    browserTargets, browserError, browserRecovery,
    braces: { old: expandOld('file-{a,b}-{1..2}.txt'), typed: expandTyped('file-{a,b}-{1..2}.txt') },
    shellArguments: shellQuote.parse(shellQuote.quote(argumentsOnly), {}),
    concurrentlyImportable: typeof concurrently === 'function',
    yaml: { positive: yamlPositive, error: yamlError, recovery: yamlRecovery },
    css: { text: cssResult.css, mapSources: cssResult.map.toJSON().sources, mapContent: cssResult.map.toJSON().sourcesContent, error: cssError, recovery: cssRecovery.toString() },
    sourceMap: { original, content: mapReader.sourceContentFor('fixture.css') },
    nanoid: { length: identifier.length, alphabet: /^[A-Za-z0-9_-]+$/.test(identifier) },
    vite: { config, normalized: normalizePath('resources/js/../js/app.tsx') },
}) + '\n');
JS;
        $process = new Process([$binary, '--input-type=module'], dirname(__DIR__, 2), $this->nodeEnvironment());
        $process->setTimeout(10);
        $process->setInput($script);
        $process->run();
        $this->assertSame(0, $process->getExitCode(), 'The public build APIs must load and report observations before compatibility assertions: '.$process->getErrorOutput());
        $this->assertSame('', $process->getErrorOutput());
        $observed = json_decode($process->getOutput(), true, 32, JSON_THROW_ON_ERROR);
        $this->assertIsArray($observed);
        foreach ($observed['versions'] as $version) {
            $this->assertIsString($version);
            $this->assertNotSame('', $version);
        }
        $this->assertTrue($observed['onlyAllowedEnvironment'], json_encode($observed['unexpectedEnvironmentKeys'], JSON_THROW_ON_ERROR));
        $this->assertTrue($observed['promptControlled']);
        $this->assertSame('Café & quoted', $observed['babel']['literal']);
        $this->assertTrue($observed['babel']['codePresent']);
        $this->assertSame(['const label = "Café & quoted";'], $observed['babel']['mapContent']);
        $this->assertSame('SyntaxError', $observed['babel']['error']);
        $this->assertSame('Café & quoted', $observed['babel']['recovery']);
        foreach (['populated', 'chrome', 'firefox'] as $contract) {
            $this->assertTrue($observed['baseline'][$contract], 'Historical Baseline mapping contract failed: '.$contract);
        }
        $this->assertSame(['chrome 120', 'firefox 121'], $observed['browserTargets']);
        $this->assertSame('BrowserslistError', $observed['browserError']);
        $this->assertSame($observed['browserTargets'], $observed['browserRecovery']);
        $expanded = ['file-a-1.txt', 'file-a-2.txt', 'file-b-1.txt', 'file-b-2.txt'];
        $this->assertSame($expanded, $observed['braces']['old']);
        $this->assertSame($expanded, $observed['braces']['typed']);
        $this->assertSame(['npm', 'run', 'dev', 'Café & "quoted"', 'two words'], $observed['shellArguments']);
        $this->assertTrue($observed['concurrentlyImportable']);
        $this->assertSame(['label' => 'Café & quoted', 'items' => ['one', 'two']], $observed['yaml']['positive']);
        $this->assertSame('YAMLException', $observed['yaml']['error']);
        $this->assertSame($observed['yaml']['positive'], $observed['yaml']['recovery']);
        $css = "/* Café */\n.fixture { color: blue; }\n";
        $this->assertSame($css, $observed['css']['text']);
        $this->assertSame(['fixture.css'], $observed['css']['mapSources']);
        $this->assertSame([$css], $observed['css']['mapContent']);
        $this->assertSame('CssSyntaxError', $observed['css']['error']);
        $this->assertSame($css, $observed['css']['recovery']);
        $this->assertSame(['source' => 'fixture.css', 'line' => 3, 'column' => 2, 'name' => null], $observed['sourceMap']['original']);
        $this->assertSame("/* Café */\n\n  .fixture { color: blue; }\n", $observed['sourceMap']['content']);
        $this->assertSame(12, $observed['nanoid']['length']);
        $this->assertTrue($observed['nanoid']['alphabet']);
        $this->assertSame(['base' => '/assets/', 'define' => ['LABEL' => '"Café"', 'MODE' => '"fixture"'], 'css' => ['devSourcemap' => true]], $observed['vite']['config']);
        $this->assertSame('resources/js/app.tsx', $observed['vite']['normalized']);
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
