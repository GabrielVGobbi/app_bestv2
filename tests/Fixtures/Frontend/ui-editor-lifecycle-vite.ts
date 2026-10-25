import { readFileSync, realpathSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import react from '@vitejs/plugin-react';
import { defineConfig } from 'vite';

const root = realpathSync(fileURLToPath(new URL('../../../', import.meta.url)));
const source = readFileSync(`${root}/resources/css/app.css`, 'utf8');
const light = source.match(/(?:^|\n):root\s*\{[^}]*\}/)?.[0];
const dark = source.match(/(?:^|\n)\.dark\s*\{[^}]*\}/)?.[0];

if (!light || !dark || !light.includes('oklch(') || !dark.includes('oklch(')) {
    throw new Error('UI007 requires the real finite light/dark token blocks.');
}

const imageName = 'assets/ui007-owned-pixel.png';
const image = Buffer.from(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l9sAAAAASUVORK5CYII=',
    'base64',
);

export default defineConfig({
    root,
    envDir: false,
    publicDir: false,
    base: '/build/',
    cacheDir: 'storage/framework/testing/ui-20261019/ui007-vite-cache',
    resolve: { alias: [{ find: '@', replacement: `${root}/resources/js` }] },
    plugins: [
        react({ babel: { plugins: ['babel-plugin-react-compiler'] } }),
        {
            name: 'ui007-owned-image-and-theme',
            buildStart() {
                this.emitFile({
                    type: 'asset',
                    fileName: imageName,
                    source: image,
                });
            },
            transformIndexHtml: {
                order: 'pre',
                handler: () => [
                    {
                        tag: 'style',
                        children: `${light}\n${dark}`,
                        injectTo: 'head',
                    },
                    {
                        tag: 'meta',
                        attrs: {
                            name: 'ui007-owned-image',
                            content: `/build/${imageName}`,
                        },
                        injectTo: 'head',
                    },
                ],
            },
        },
    ],
    server: {
        host: '127.0.0.1',
        port: 5199,
        strictPort: true,
        allowedHosts: ['www2.start_kit.com.br'],
    },
    preview: {
        host: '127.0.0.1',
        port: 5200,
        strictPort: true,
        allowedHosts: ['www2.start_kit.com.br'],
    },
    build: {
        outDir: 'storage/framework/testing/ui-20261019/ui007-build',
        emptyOutDir: false,
        manifest: 'manifest.json',
        rolldownOptions: {
            input: `${root}/tests/Fixtures/Frontend/ui-editor-lifecycle-browser.html`,
        },
    },
});
