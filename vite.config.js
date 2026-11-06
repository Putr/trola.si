import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig(({ command, mode }) => {
    const isProduction = mode === 'production';

    return {
        plugins: [
            laravel({
                input: [
                    'resources/css/app.css',
                    'resources/js/app.js'
                ],
                refresh: true,
            }),
        ],
        build: {
            // Keep Vite 6's browser floor: Vite 8's default (Safari 16.4 / Chrome 111) would drop
            // older phones and Android WebViews, and the site must load on any phone.
            target: ['es2020', 'chrome87', 'edge88', 'firefox78', 'safari14'],
            // Only generate sourcemaps in development
            sourcemap: !isProduction,
            rollupOptions: {
                output: {
                    sourcemapExcludeSources: true,
                    // Production-only optimizations
                    ...(isProduction && {
                        chunkFileNames: 'assets/js/[name]-[hash].js',
                        entryFileNames: 'assets/js/[name]-[hash].js',
                        assetFileNames: 'assets/[ext]/[name]-[hash].[ext]',
                    }),
                }
            },
            // Production-only minification settings
            ...(isProduction && {
                minify: 'terser',
                terserOptions: {
                    compress: {
                        drop_console: true,
                        drop_debugger: true
                    }
                },
            }),
            reportCompressedSize: isProduction,
            chunkSizeWarningLimit: 1000,
        },
        // Development-specific settings
        server: {
            hmr: {
                overlay: true,
            },
            // Increase dev server timeout if needed
            timeout: 120000,
        },
    }
});
