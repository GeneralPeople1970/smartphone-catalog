import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    server: {
        // Bind IPv4 explicitly: on Node ≥17 `localhost` resolves to ::1 and the
        // hot file ends up with an IPv6 origin, which browsers refuse in a
        // Content-Security-Policy source list — killing every dev stylesheet.
        host: '127.0.0.1',
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
