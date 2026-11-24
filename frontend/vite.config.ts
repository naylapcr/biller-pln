import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { resolve } from 'node:path';
import { defineConfig } from 'vite';

// Backend Laravel ada di ../backend. Hasil build dan berkas `hot` ditulis ke
// backend/public supaya direktif @vite di Blade tetap bisa menemukannya.
const backend = resolve(__dirname, '../backend');

export default defineConfig({
    // APP_URL dan VITE_* dibaca dari backend/.env.
    envDir: backend,
    plugins: [
        laravel({
            input: ['src/app.tsx'],
            publicDirectory: resolve(backend, 'public'),
            hotFile: resolve(backend, 'public/hot'),
            refresh: [
                resolve(backend, 'resources/views/**'),
                resolve(backend, 'routes/**'),
                resolve(backend, 'app/Http/**'),
            ],
        }),
        react(),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            '@': resolve(__dirname, 'src'),
        },
    },
});
