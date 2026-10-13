import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
export default defineConfig({
    publicDir: '../public_html',
    plugins: [laravel({publicDirectory: '../public_html', input: ['resources/css/app.css', 'resources/css/admin.css', 'resources/js/app.js'], refresh: true}), tailwindcss()],
    build: {sourcemap: false, copyPublicDir: false, emptyOutDir: true, target: ['chrome111', 'firefox128', 'safari16.4']}
});
