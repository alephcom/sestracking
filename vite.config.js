import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.scss',
                'resources/css/signin.scss',
                'resources/js/activity.js',
                'resources/js/dashboard.js',
            ],
            refresh: true,
        }),
    ],
    css: {
        preprocessorOptions: {
            scss: {
                // Bootstrap 5 still uses Sass features that newer Dart Sass flags as deprecated
                quietDeps: true,
                silenceDeprecations: ['color-functions', 'global-builtin', 'import'],
            },
        },
    },
});
