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
            // Only generate sourcemaps in development
            sourcemap: !isProduction,
            rollupOptions: {
                output: {
                    sourcemapExcludeSources: true,
                    // Production-only optimizations
                    ...(isProduction && {
                        manualChunks: {
                            vendor: ['axios'], // Add your major dependencies here
                        },
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
