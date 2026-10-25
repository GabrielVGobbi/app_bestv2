import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import httpNative from 'node:http';
import httpsNative from 'node:https';
import { syncBuiltinESMExports } from 'node:module';
import net from 'node:net';
import { resolve } from 'node:path';
import process from 'node:process';
import tls from 'node:tls';
import { pathToFileURL } from 'node:url';
import { AxiosError, AxiosHeaders } from 'axios';
import ts from 'typescript';

let realNetworkRequests = 0;
const denyNetwork = () => {
    realNetworkRequests++;
    throw new Error('ARC002 contract fixture forbids real transport.');
};
httpNative.request =
    httpNative.get =
    httpsNative.request =
    httpsNative.get =
        denyNetwork;
net.connect = net.createConnection = tls.connect = denyNetwork;
globalThis.fetch = denyNetwork;
syncBuiltinESMExports();

const root = process.cwd();
const sourcePath = (path) => resolve(root, 'resources/js', path);
const { http, httpPut, httpDelete, toApiError } = await import(
    pathToFileURL(sourcePath('lib/http.ts')).href
);
const originalAdapter = http.defaults.adapter;
const checks = [];
async function check(name, run) {
    try {
        await run();
        checks.push({ name, status: 'passed' });
    } catch (error) {
        checks.push({ name, status: 'failed', message: error.message });
    }
}

// Execute the production callback body, preserving its real route and HTTP
// imports. React state and navigation are the controlled boundaries here.
async function handler(path, name, boundary) {
    const source = ts.createSourceFile(
        path,
        readFileSync(sourcePath(path), 'utf8'),
        ts.ScriptTarget.Latest,
        true,
        ts.ScriptKind.TSX,
    );
    const dependencies = { httpPut, httpDelete, toApiError, ...boundary };
    for (const statement of source.statements) {
        if (
            !ts.isImportDeclaration(statement) ||
            !ts.isStringLiteral(statement.moduleSpecifier) ||
            !statement.moduleSpecifier.text.startsWith('@/routes/')
        ) {
            continue;
        }
        const module = await import(
            pathToFileURL(
                sourcePath(
                    `${statement.moduleSpecifier.text.slice(2)}/index.ts`,
                ),
            ).href
        );
        const bindings = statement.importClause?.namedBindings;
        if (bindings && ts.isNamedImports(bindings)) {
            for (const binding of bindings.elements) {
                dependencies[binding.name.text] =
                    module[binding.propertyName?.text ?? binding.name.text];
            }
        }
    }
    let expression;
    function visit(node) {
        if (
            ts.isVariableDeclaration(node) &&
            ts.isIdentifier(node.name) &&
            node.name.text === name
        ) {
            expression = node.initializer?.getText(source);
        }
        ts.forEachChild(node, visit);
    }
    visit(source);
    assert(expression, `Production callback ${name} is missing.`);
    const compiled = ts.transpileModule(`const callback = ${expression};`, {
        compilerOptions: {
            target: ts.ScriptTarget.ES2022,
            module: ts.ModuleKind.ESNext,
        },
        reportDiagnostics: true,
    });
    assert.equal(compiled.diagnostics?.length ?? 0, 0);
    return new Function(
        ...Object.keys(dependencies),
        `${compiled.outputText}\nreturn callback;`,
    )(...Object.values(dependencies));
}

await check('pagination-public-aliases-have-the-canonical-contract', () => {
    const path = resolve(
        root,
        'tests/Fixtures/Frontend/arc002-virtual-contract.ts',
    );
    const text = `
import type { PaginationMeta as HttpMeta, PaginationLinks as HttpLinks, PaginatedResponse } from '@/lib/http';
import type { PaginationMeta, PaginationLinks, Paginated } from '@/types/pagination';
type Equal<A, B> = (<T>() => T extends A ? 1 : 2) extends (<T>() => T extends B ? 1 : 2) ? true : false;
const meta: Equal<HttpMeta, PaginationMeta> = true;
const links: Equal<HttpLinks, PaginationLinks> = true;
const envelope: Equal<PaginatedResponse<{ id: number }>, Paginated<{ id: number }>> = true;
void [meta, links, envelope];`;
    const config = ts.readConfigFile(
        resolve(root, 'tsconfig.json'),
        ts.sys.readFile,
    );
    assert.equal(config.error, undefined);
    const parsed = ts.parseJsonConfigFileContent(config.config, ts.sys, root);
    const options = { ...parsed.options, noEmit: true, incremental: false };
    const host = ts.createCompilerHost(options);
    const getSourceFile = host.getSourceFile.bind(host);
    host.getSourceFile = (file, language, ...args) =>
        resolve(file) === path
            ? ts.createSourceFile(file, text, language, true)
            : getSourceFile(file, language, ...args);
    const program = ts.createProgram([path], options, host);
    const diagnostics = ts.getPreEmitDiagnostics(program);
    assert.equal(
        diagnostics.length,
        0,
        ts.formatDiagnosticsWithColorAndContext(diagnostics, {
            getCanonicalFileName: (file) => file,
            getCurrentDirectory: () => root,
            getNewLine: () => '\n',
        }),
    );
});

for (const resource of ['roles', 'permissions']) {
    const api = await import(
        pathToFileURL(sourcePath(`routes/api/${resource}/index.ts`)).href
    );
    const identifier = resource === 'roles' ? 'role' : 'permission';
    for (const scenario of [
        'success',
        'validation',
        'inactive',
        'identity-ended-during-request',
    ]) {
        await check(`${resource}-edit:${scenario}`, async () => {
            const requests = [];
            const visits = [];
            const successes = [];
            const failures = [];
            const errors = [];
            const processing = [];
            let current = scenario !== 'inactive';
            http.defaults.adapter = async (config) => {
                requests.push({
                    url: config.url,
                    method: config.method,
                    data: config.data,
                });
                if (scenario === 'validation') {
                    throw new AxiosError(
                        'Fixture validation',
                        'ERR_BAD_REQUEST',
                        config,
                        undefined,
                        {
                            status: 422,
                            statusText: 'Unprocessable Content',
                            data: {
                                message: 'Validation failed.',
                                errors: { name: ['Name is required.'] },
                            },
                            headers: new AxiosHeaders(),
                            config,
                        },
                    );
                }
                if (scenario === 'identity-ended-during-request')
                    current = false;
                return {
                    status: 200,
                    statusText: 'OK',
                    data: { data: { id: 31 } },
                    headers: new AxiosHeaders(),
                    config,
                };
            };
            const submit = await handler(
                `pages/app/${resource}/edit.tsx`,
                'handleSubmit',
                {
                    scope: { isCurrent: () => current },
                    [identifier]: { id: 31 },
                    form: {
                        name: 'Updated fixture name',
                        slug: 'existing-slug',
                        description: '',
                    },
                    setProcessing: (value) => processing.push(value),
                    setErrors: (value) => errors.push(value),
                    toast: {
                        success: (value) => successes.push(value),
                        error: (value) => failures.push(value),
                    },
                    router: { visit: (url) => visits.push(url) },
                },
            );
            let prevented = false;
            await submit({
                preventDefault: () => {
                    prevented = true;
                },
            });
            assert(prevented);
            assert.equal(processing.at(-1), false);
            if (scenario === 'inactive') {
                assert.equal(requests.length, 0);
            } else {
                assert.equal(requests.length, 1);
                assert.equal(
                    requests[0].url,
                    api.update.url(31),
                    'Standalone HTTP mutations must use the JSON API route, not an Inertia redirect endpoint.',
                );
                assert.equal(requests[0].method, 'put');
            }
            if (scenario === 'success') {
                assert.equal(successes.length, 1);
                assert.equal(visits.length, 1);
                assert.equal(failures.length, 0);
            } else {
                assert.equal(successes.length, 0);
                assert.equal(visits.length, 0);
                assert.equal(failures.length, 0);
            }
            if (scenario === 'validation')
                assert.deepEqual(errors.at(-1), { name: 'Name is required.' });
        });
    }
}

for (const scenario of [
    'success-redirect',
    'success-callback',
    'validation',
    'cancel',
]) {
    await check(`web-delete:${scenario}`, async () => {
        const inertia = [];
        const xhr = [];
        const visits = [];
        const successes = [];
        const failures = [];
        const processing = [];
        const opened = [];
        let callbacks = 0;
        http.defaults.adapter = async (config) => {
            xhr.push(config.url);
            return {
                status: 200,
                statusText: 'OK',
                data: '<html>Redirect destination</html>',
                headers: new AxiosHeaders(),
                config,
            };
        };
        const remove = await handler(
            'components/delete-button.tsx',
            'handleDelete',
            {
                url: '/clients/fixture-id',
                resourceType: 'cliente',
                redirectTo: '/clients',
                onSuccess:
                    scenario === 'success-callback'
                        ? () => {
                              callbacks++;
                          }
                        : undefined,
                setDeleting: (value) => processing.push(value),
                setOpen: (value) => opened.push(value),
                capitalize: (value) => value[0].toUpperCase() + value.slice(1),
                toast: {
                    success: (value) => successes.push(value),
                    error: (value) => failures.push(value),
                },
                router: {
                    visit: (url) => visits.push(url),
                    delete: (url, options) => {
                        inertia.push(url);
                        if (scenario === 'validation')
                            options.onError?.({
                                message: 'Cannot remove fixture.',
                            });
                        else if (scenario === 'cancel') options.onCancel?.();
                        else options.onSuccess?.();
                        options.onFinish?.();
                    },
                },
            },
        );
        await remove();
        assert.deepEqual(
            xhr,
            [],
            'A web redirect endpoint must not be consumed as Axios JSON.',
        );
        assert.deepEqual(inertia, ['/clients/fixture-id']);
        assert.equal(
            processing.at(-1),
            false,
            'Finish and cancellation must release the busy state.',
        );
        if (scenario.startsWith('success')) {
            assert.equal(successes.length, 1);
            assert.equal(opened.at(-1), false);
            assert.deepEqual(
                visits,
                scenario === 'success-redirect' ? ['/clients'] : [],
            );
            assert.equal(callbacks, scenario === 'success-callback' ? 1 : 0);
        } else {
            assert.equal(successes.length, 0);
            assert.deepEqual(visits, []);
            assert.equal(callbacks, 0);
            assert.equal(failures.length, scenario === 'validation' ? 1 : 0);
        }
    });
}

http.defaults.adapter = originalAdapter;
assert.equal(realNetworkRequests, 0);
const failed = checks.filter((entry) => entry.status !== 'passed');
console.log(
    JSON.stringify({
        status: failed.length ? 'failed' : 'passed',
        checks,
        realNetworkRequests,
    }),
);
process.exitCode = failed.length ? 1 : 0;
