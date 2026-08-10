import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                // resources/css/app.css was dropped: it was always empty, and
                // nothing renders a @vite(['resources/css/app.css']) blade
                // directive anywhere in the app, so it only ever produced a
                // dead, empty build entry.
                'resources/js/app.js',
                'resources/css/filament/admin/theme.css',
            ],
            refresh: true,
        }),
    ],
});
