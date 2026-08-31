import tailwindcss from '@tailwindcss/vite';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';

// Celowo zwykłe Vite, nie nakładka `vite-plus`: jej runtime startuje własną
// pulę wątków tokio, a na tym hostingu limit wątków na konto jest tak niski,
// że build wywala się paniką Rusta jeszcze przed wczytaniem konfiguracji.
export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/passkeys.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        cors: true,
        watch: {
            ignored: [
                '**/.claude/**',
                '**/storage/framework/views/**',
                '**/vendor/**',
            ],
        },
    },
});
