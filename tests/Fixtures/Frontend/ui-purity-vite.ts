import { readFileSync, realpathSync } from 'node:fs';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import react from '@vitejs/plugin-react';
import { defineConfig } from 'vite';

const root = realpathSync(fileURLToPath(new URL('../../../', import.meta.url)));
const baselineDirectory = process.env.UI008_SOURCE_SNAPSHOT;
const baselineSources = [
    'resources/js/components/data-table/hooks/use-data-table-selection.ts',
    'resources/js/components/data-table/hooks/use-data-table-state.ts',
    'resources/js/contexts/nav-module-context.tsx',
    'resources/js/modules/uploads/components/file-attachments.tsx',
    'resources/js/modules/uploads/components/upload-queue-item.tsx',
    'resources/js/modules/uploads/hooks/use-upload-queue.ts',
];
const boundaries: Record<string, string> = {
    '@/lib/http':
        'export const http = { post: (...args) => globalThis.__uiPurity.post(...args) }; export const toApiError = error => error; export const getErrorMessage = error => error.message ?? "Synthetic failure";',
    '@inertiajs/react':
        'export function usePage() { return globalThis.__uiPurity.page; }',
};

export default defineConfig({
    root,
    envDir: false,
    publicDir: false,
    cacheDir:
        process.env.UI008_VITE_CACHE_DIR || 'node_modules/.vite-ui008-fixture',
    resolve: {
        alias: [
            {
                find: '@/contexts/private-state-context',
                replacement: `${root}/tests/Fixtures/Frontend/ui-purity-scope.tsx`,
            },
            ...Object.keys(boundaries).map((find) => ({
                find,
                replacement: `virtual:ui008:${find}`,
            })),
            {
                find: /^\.\.\/api\/mutations$/,
                replacement: 'virtual:ui008-upload-mutations',
            },
            {
                find: /^\.\.\/hooks\/use-uploads$/,
                replacement: 'virtual:ui008-upload-list',
            },
            {
                find: /^\.\.\/hooks\/use-zip-request$/,
                replacement: 'virtual:ui008-zip',
            },
            {
                find: /.*\/resources\/js\/modules\/uploads\/api\/mutations(?:\.ts)?$/,
                replacement: 'virtual:ui008-upload-mutations',
            },
            {
                find: /.*\/resources\/js\/modules\/uploads\/hooks\/use-uploads(?:\.ts)?$/,
                replacement: 'virtual:ui008-upload-list',
            },
            {
                find: /.*\/resources\/js\/modules\/uploads\/hooks\/use-zip-request(?:\.ts)?$/,
                replacement: 'virtual:ui008-zip',
            },
            { find: '@', replacement: `${root}/resources/js` },
        ],
    },
    plugins: [
        {
            name: 'ui008-owned-boundaries',
            enforce: 'pre',
            resolveId(source) {
                if (source.startsWith('virtual:ui008:')) {
                    return `\0${source}`;
                }

                if (source.startsWith('virtual:ui008-')) {
                    return `\0${source.slice('virtual:'.length)}`;
                }

                if (source === '../hooks/use-uploads') {
                    return '\0ui008-upload-list';
                }

                if (source === '../api/mutations') {
                    return '\0ui008-upload-mutations';
                }

                if (source === '../hooks/use-zip-request') {
                    return '\0ui008-zip';
                }
            },
            load(id) {
                const normalizedId = id.replaceAll('\\', '/').split('?')[0];
                const relativePath = baselineSources.find(
                    (source) =>
                        normalizedId ===
                        `${root.replaceAll('\\', '/')}/${source}`,
                );

                if (baselineDirectory && relativePath) {
                    return readFileSync(
                        resolve(baselineDirectory, relativePath),
                        'utf8',
                    );
                }

                if (id.startsWith('\0virtual:ui008:')) {
                    return boundaries[id.slice('\0virtual:ui008:'.length)];
                }

                if (id === '\0ui008-upload-list') {
                    return 'export function useUploads() { return globalThis.__uiPurity.uploads; }';
                }

                if (id === '\0ui008-upload-mutations') {
                    return 'export function useBulkDeleteUploads() { return { isPending: false, mutate() { throw new Error("Real delete forbidden"); } }; }';
                }

                if (id === '\0ui008-zip') {
                    return 'export function useZipRequest() { return { isPending: false, createZip: value => globalThis.__uiPurity.zips.push(value) }; }';
                }
            },
        },
        react({ babel: { plugins: ['babel-plugin-react-compiler'] } }),
    ],
    server: {
        host: '127.0.0.1',
        strictPort: true,
        hmr: false,
        cors: false,
        allowedHosts: ['www2.start_kit.com.br'],
        fs: { strict: true, allow: [root] },
    },
    preview: { allowedHosts: ['www2.start_kit.com.br'] },
    build: {
        outDir:
            process.env.UI008_VITE_BUILD_DIR ||
            'storage/framework/testing/ui008-fixture-build',
        emptyOutDir: false,
        manifest: true,
        rolldownOptions: {
            input: `${root}/tests/Fixtures/Frontend/ui-purity-browser.html`,
        },
    },
});
