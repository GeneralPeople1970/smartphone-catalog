import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    server: {
        proxy: process.env.VITE_API_PROXY_TARGET
            ? {
                '/api': {
                    target: process.env.VITE_API_PROXY_TARGET,
                    changeOrigin: true,
                    secure: false,
                },
            }
            : undefined,
    },
    plugins: [
        tailwindcss(),
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
});
