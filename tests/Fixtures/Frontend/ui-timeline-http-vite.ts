import { realpathSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import { defineConfig } from 'vite';

const root = realpathSync(fileURLToPath(new URL('../../../', import.meta.url)));

export default defineConfig({
    root,
    envDir: false,
    publicDir: false,
    base: '/build/',
    cacheDir: 'storage/framework/testing/ui-20261019/ui003-http-vite-cache',
    resolve: { alias: [{ find: '@', replacement: `${root}/resources/js` }] },
    plugins: [
        react({ babel: { plugins: ['babel-plugin-react-compiler'] } }),
        tailwindcss(),
    ],
    define: { 'import.meta.env.VITE_REVERB_ENABLED': '"false"' },
    build: {
        outDir: 'storage/framework/testing/ui-20261019/ui003-http-build',
        emptyOutDir: false,
        manifest: 'manifest.json',
        rolldownOptions: {
            input: `${root}/tests/Fixtures/Frontend/ui-timeline-http-probe.tsx`,
        },
    },
});
