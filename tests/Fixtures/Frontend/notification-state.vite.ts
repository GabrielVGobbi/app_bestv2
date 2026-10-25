import react from '@vitejs/plugin-react';
import { realpathSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vite';

const root = realpathSync(fileURLToPath(new URL('../../../', import.meta.url)));

export default defineConfig({
    root,
    envDir: false,
    cacheDir:
        process.env.UI001_VITE_CACHE_DIR || 'node_modules/.vite-ui001-fixture',
    resolve: {
        alias: [
            {
                find: /^@\/services\/notification-service$/,
                replacement: 'virtual:ui001-notification-service',
            },
            { find: /^@\/lib\/echo$/, replacement: 'virtual:ui001-echo' },
            { find: '@', replacement: `${root}/resources/js` },
        ],
    },
    plugins: [
        {
            name: 'ui001-owned-service-boundaries',
            enforce: 'pre',
            resolveId(source) {
                if (source === 'virtual:ui001-notification-service')
                    return '\0ui001-notification-service';
                if (source === 'virtual:ui001-echo') return '\0ui001-echo';
            },
            load(id) {
                if (id === '\0ui001-notification-service')
                    return 'export const notificationService = globalThis.__ui001NotificationFixture.service;';
                if (id === '\0ui001-echo')
                    return 'export default globalThis.__ui001NotificationFixture.echo;';
            },
        },
        react(),
    ],
    server: {
        host: '127.0.0.1',
        strictPort: true,
        hmr: false,
        cors: false,
        fs: { strict: true, allow: [root] },
    },
});
