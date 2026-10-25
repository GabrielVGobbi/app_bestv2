import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
        }),
        inertia(),
        react({
            babel: {
                plugins: ['babel-plugin-react-compiler'],
            },
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
        }),
    ],
    server: {
        watch: {
            // storage/framework/testing acumula dezenas de milhares de pastas dos testes;
            // vigiar isso no Windows abre um handle por pasta e leva o Vite a GBs de RAM.
            ignored: [
                '**/storage/**',
                '**/vendor/**',
                '**/bootstrap/cache/**',
                '**/bootstrap/ssr/**',
                '**/public/build/**',
                '**/docs/**',
                '**/.docs/**',
                '**/tests/**',
            ],
        },
    },
});
