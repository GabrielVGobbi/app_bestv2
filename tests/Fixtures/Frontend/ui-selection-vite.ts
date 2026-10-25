import { realpathSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import react from '@vitejs/plugin-react';
import { defineConfig } from 'vite';

const root = realpathSync(fileURLToPath(new URL('../../../', import.meta.url)));

export default defineConfig({
    root,
    envDir: false,
    publicDir: false,
    base: '/build/',
    cacheDir: 'storage/framework/testing/ui-20261019/ui004-vite-cache',
    resolve: { alias: [{ find: '@', replacement: `${root}/resources/js` }] },
    plugins: [react({ babel: { plugins: ['babel-plugin-react-compiler'] } })],
    define: { 'import.meta.env.VITE_REVERB_ENABLED': '"false"' },
    preview: { allowedHosts: ['www2.start_kit.com.br'] },
    build: {
        outDir: 'storage/framework/testing/ui-20261019/ui004-build',
        emptyOutDir: false,
        manifest: 'manifest.json',
        rolldownOptions: {
            input: `${root}/tests/Fixtures/Frontend/ui-selection-browser.html`,
        },
    },
});
