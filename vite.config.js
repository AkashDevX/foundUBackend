import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/workforce.js',
                'resources/js/registration-admin-profile.js',
                'resources/js/employee-autocomplete.js',
                'resources/js/admin-time-clock-timesheet.js',
                'resources/js/admin-time-clock-punch-map.js',
                'resources/js/admin-time-clock-row-actions.js',
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
