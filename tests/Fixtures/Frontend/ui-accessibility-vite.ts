import { realpathSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import { defineConfig } from 'vite';

const root = realpathSync(fileURLToPath(new URL('../../../', import.meta.url)));
const fontImport =
    "@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600;700&display=swap');";

export default defineConfig({
    root,
    envDir: false,
    publicDir: false,
    base: '/build/',
    cacheDir: 'storage/framework/testing/ui-20261019/ui009-vite-cache',
    resolve: { alias: [{ find: '@', replacement: `${root}/resources/js` }] },
    plugins: [
        {
            name: 'ui009-real-css-without-remote-fonts',
            enforce: 'pre',
            transform(source, id) {
                if (
                    id.split('?')[0].replaceAll('\\', '/') !==
                    `${root.replaceAll('\\', '/')}/resources/css/app.css`
                ) {
                    return;
                }

                if (source.split(fontImport).length !== 2) {
                    throw new Error(
                        'UI009 requires exactly the known single remote font import.',
                    );
                }

                return { code: source.replace(fontImport, ''), map: null };
            },
        },
        react({ babel: { plugins: ['babel-plugin-react-compiler'] } }),
        tailwindcss(),
    ],
    server: {
        host: '127.0.0.1',
        port: 5202,
        strictPort: true,
        allowedHosts: ['www2.start_kit.com.br'],
    },
    preview: {
        host: '127.0.0.1',
        port: 5201,
        strictPort: true,
        allowedHosts: ['www2.start_kit.com.br'],
    },
    build: {
        outDir: 'storage/framework/testing/ui-20261019/ui009-build',
        emptyOutDir: false,
        manifest: 'manifest.json',
        rolldownOptions: {
            input: `${root}/tests/Fixtures/Frontend/ui-accessibility-browser.html`,
        },
    },
});
