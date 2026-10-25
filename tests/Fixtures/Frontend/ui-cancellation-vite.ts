import { realpathSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import react from '@vitejs/plugin-react';
import { defineConfig } from 'vite';

const root = realpathSync(fileURLToPath(new URL('../../../', import.meta.url)));

export default defineConfig({
    root,
    envDir: false,
    cacheDir:
        process.env.UI005_VITE_CACHE_DIR || 'node_modules/.vite-ui005-fixture',
    resolve: { alias: [{ find: '@', replacement: `${root}/resources/js` }] },
    plugins: [react({ babel: { plugins: ['babel-plugin-react-compiler'] } })],
    server: {
        host: '127.0.0.1',
        strictPort: true,
        hmr: false,
        cors: false,
        allowedHosts: ['www2.start_kit.com.br'],
        fs: { strict: true, allow: [root] },
    },
    build: {
        outDir:
            process.env.UI005_VITE_BUILD_DIR ||
            'storage/framework/testing/ui005-fixture-build',
        emptyOutDir: false,
        manifest: true,
        rolldownOptions: {
            input: `${root}/tests/Fixtures/Frontend/ui-cancellation-browser.html`,
        },
    },
});
