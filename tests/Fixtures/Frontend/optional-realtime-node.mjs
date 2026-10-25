import assert from 'node:assert/strict';
import { Buffer } from 'node:buffer';
import { readFileSync } from 'node:fs';
import http from 'node:http';
import https from 'node:https';
import { syncBuiltinESMExports } from 'node:module';
import net from 'node:net';
import process from 'node:process';
import tls from 'node:tls';
import ts from 'typescript';

let networkCalls = 0;
const denyNetwork = () => {
    networkCalls++;

    throw new Error('Network forbidden in the SSR import regression.');
};
http.request = https.request = http.get = https.get = denyNetwork;
net.connect = net.createConnection = tls.connect = denyNetwork;
globalThis.fetch = denyNetwork;
syncBuiltinESMExports();

try {
    assert.equal(typeof window, 'undefined');
    assert.equal(typeof document, 'undefined');
    const source = readFileSync('resources/js/lib/echo.ts', 'utf8');
    const observed = [];

    for (const enabled of [false, true]) {
        const environment = {
            SSR: true,
            VITE_REVERB_ENABLED: String(enabled),
            VITE_REVERB_APP_KEY: 'ops004-synthetic-key',
            VITE_REVERB_HOST: '127.0.0.1',
            VITE_REVERB_PORT: '19999',
            VITE_REVERB_SCHEME: 'http',
        };
        const before = (context) => {
            const visit = (node) => {
                if (
                    ts.isPropertyAccessExpression(node) &&
                    node.name.text === 'env' &&
                    ts.isMetaProperty(node.expression)
                ) {
                    return ts.factory.createObjectLiteralExpression(
                        Object.entries(environment).map(([name, value]) =>
                            ts.factory.createPropertyAssignment(
                                name,
                                typeof value === 'boolean'
                                    ? ts.factory.createTrue()
                                    : ts.factory.createStringLiteral(value),
                            ),
                        ),
                    );
                }

                if (
                    ts.isImportDeclaration(node) &&
                    ts.isStringLiteral(node.moduleSpecifier)
                ) {
                    return ts.factory.updateImportDeclaration(
                        node,
                        node.modifiers,
                        node.importClause,
                        ts.factory.createStringLiteral(
                            import.meta.resolve(node.moduleSpecifier.text),
                        ),
                        node.attributes,
                    );
                }

                return ts.visitEachChild(node, visit, context);
            };

            return (file) => ts.visitNode(file, visit);
        };
        const output = ts.transpileModule(source, {
            fileName: 'echo.ts',
            compilerOptions: {
                target: ts.ScriptTarget.ES2022,
                module: ts.ModuleKind.ESNext,
            },
            transformers: { before: [before] },
            reportDiagnostics: true,
        });
        assert.equal(output.diagnostics?.length ?? 0, 0);
        const module = await import(
            `data:text/javascript;base64,${Buffer.from(output.outputText).toString('base64')}`
        );
        assert.equal(module.default, null);
        observed.push({ enabled, echo: module.default });
    }

    assert.equal(networkCalls, 0);
    console.log(JSON.stringify({ status: 'passed', observed, networkCalls }));
} catch (error) {
    console.log(
        JSON.stringify({
            status: 'failed',
            name: error.name,
            message: error.message,
            networkCalls,
        }),
    );
    process.exitCode = 1;
}
