import { realpathSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vite';

import application from '../../../vite.config';

const root = realpathSync(fileURLToPath(new URL('../../../', import.meta.url)));

// Compile the application's real inputs and installed Compiler without running
// Artisan against the workstation. Generated route helpers remain unchanged.
export default defineConfig({
    ...application,
    root,
    publicDir: false,
    envDir: `${root}/storage/framework/testing/ui-20261019/environment`,
    cacheDir: `${root}/storage/framework/testing/ui-20261019/vite-cache`,
    plugins: application.plugins
        ?.flat(Infinity)
        .filter((plugin) => plugin?.name !== '@laravel/vite-plugin-wayfinder'),
    build: {
        outDir: `${root}/storage/framework/testing/ui-20261019/application-build`,
        emptyOutDir: false,
        reportCompressedSize: false,
    },
});
