import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                // Espace enseignant (resources/views/layouts/enseignant.blade.php):
                // its own bundle, deliberately not merged into app.css/app.js —
                // the mockup it's built from (files/tableau-bord-enseignant_1.html)
                // reuses admin class names like .shell/.btn/.field with different
                // styling, which would silently corrupt the admin UI if the two
                // stylesheets shared one bundle.
                'resources/css/enseignant.css',
                'resources/js/enseignant.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
