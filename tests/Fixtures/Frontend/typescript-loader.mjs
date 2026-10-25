import { existsSync, lstatSync, readFileSync } from 'node:fs';
import { register } from 'node:module';
import { dirname, extname, resolve as resolvePath } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { isMainThread } from 'node:worker_threads';
import ts from 'typescript';

const root = resolvePath(dirname(fileURLToPath(import.meta.url)), '../../..');
const sourceRoot = resolvePath(root, 'resources/js');
const lucideNodeBundle = resolvePath(
    root,
    'node_modules/lucide-react/dist/cjs/lucide-react.js',
);

if (isMainThread) {
    register(import.meta.url, { parentURL: import.meta.url });
}

function sourceFile(path) {
    const candidates = [
        path,
        `${path}.ts`,
        `${path}.tsx`,
        `${path}.js`,
        resolvePath(path, 'index.ts'),
        resolvePath(path, 'index.tsx'),
    ];

    return candidates.find(
        (candidate) => existsSync(candidate) && lstatSync(candidate).isFile(),
    );
}

export async function resolve(specifier, context, nextResolve) {
    if (specifier === 'lucide-react') {
        // Use the package's real Node bundle instead of loading 1,761 ESM icons.
        const resolved = await nextResolve(specifier, {
            ...context,
            conditions: context.conditions.filter(
                (condition) => condition !== 'import',
            ),
        });

        if (fileURLToPath(resolved.url) !== lucideNodeBundle) {
            throw new Error('Unexpected Lucide Node bundle refused.');
        }

        return { ...resolved, format: 'commonjs' };
    }

    let requested;

    if (specifier.startsWith('@/')) {
        requested = resolvePath(sourceRoot, specifier.slice(2));
    } else if (
        specifier.startsWith('.') &&
        context.parentURL?.startsWith('file:')
    ) {
        const parent = fileURLToPath(context.parentURL);

        if (parent.startsWith(sourceRoot)) {
            requested = resolvePath(dirname(parent), specifier);
        }
    }

    if (requested) {
        if (
            !requested.startsWith(sourceRoot + '/') &&
            !requested.startsWith(sourceRoot + '\\')
        ) {
            throw new Error('Frontend fixture source escape refused.');
        }

        const file = sourceFile(requested);

        if (file) {
            const info = lstatSync(file);

            if (info.isSymbolicLink() || info.nlink !== 1) {
                throw new Error('Frontend fixture source link refused.');
            }

            return { url: pathToFileURL(file).href, shortCircuit: true };
        }
    }

    return nextResolve(specifier, context);
}

export async function load(url, context, nextLoad) {
    if (!url.startsWith('file:')) {
        return nextLoad(url, context);
    }

    const file = fileURLToPath(url);

    if (file === lucideNodeBundle) {
        return {
            format: 'commonjs',
            source: readFileSync(file, 'utf8'),
            shortCircuit: true,
        };
    }

    if (
        !file.startsWith(sourceRoot) ||
        !['.ts', '.tsx'].includes(extname(file))
    ) {
        return nextLoad(url, context);
    }

    const environment = (transformContext) => {
        const visit = (node) => {
            if (
                ts.isPropertyAccessExpression(node) &&
                node.name.text === 'env' &&
                ts.isMetaProperty(node.expression) &&
                node.expression.keywordToken === ts.SyntaxKind.ImportKeyword
            ) {
                return ts.factory.createObjectLiteralExpression([
                    ts.factory.createPropertyAssignment(
                        'SSR',
                        ts.factory.createTrue(),
                    ),
                    ts.factory.createPropertyAssignment(
                        'DEV',
                        ts.factory.createFalse(),
                    ),
                    ts.factory.createPropertyAssignment(
                        'PROD',
                        ts.factory.createFalse(),
                    ),
                ]);
            }

            return ts.visitEachChild(node, visit, transformContext);
        };

        return (source) => ts.visitNode(source, visit);
    };
    const output = ts.transpileModule(readFileSync(file, 'utf8'), {
        fileName: file,
        compilerOptions: {
            target: ts.ScriptTarget.ES2022,
            module: ts.ModuleKind.ESNext,
            jsx: ts.JsxEmit.ReactJSX,
        },
        transformers: { before: [environment] },
        reportDiagnostics: true,
    });

    if (
        output.diagnostics?.some(
            (diagnostic) => diagnostic.category === ts.DiagnosticCategory.Error,
        )
    ) {
        throw new Error('Frontend fixture TypeScript transpilation failed.');
    }

    return { format: 'module', source: output.outputText, shortCircuit: true };
}
