import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import tailwindcss from "@tailwindcss/vite";

export default defineConfig({
    plugins: [
        laravel({
            input: [
                "resources/css/app.css",
                "resources/js/app.js",
                "resources/css/filament/admin/theme.css",
            ],
            refresh: true,
        }),
        // Only add TailwindCSS plugin if not explicitly skipped
        ...(process.env.SKIP_TAILWIND ? [] : [tailwindcss()]),
    ],
    build: {
        // Increase chunk size warnings limit
        chunkSizeWarningLimit: 1000,
        // Better error reporting
        rollupOptions: {
            onwarn(warning, warn) {
                // Suppress specific warnings that are common but not critical
                if (warning.code === "UNUSED_EXTERNAL_IMPORT") return;
                if (warning.code === "CIRCULAR_DEPENDENCY") return;
                warn(warning);
            },
            output: {
                // Improve chunk splitting
                manualChunks: {
                    vendor: ["axios"],
                },
            },
        },
        // Improve source maps for debugging
        sourcemap: process.env.NODE_ENV !== "production",
        // Optimize dependencies
        target: "es2018",
        minify: "esbuild",
    },
    // Better CSS handling
    css: {
        devSourcemap: process.env.NODE_ENV !== "production",
    },
    // Improve dev server
    server: {
        hmr: {
            host: "localhost",
        },
        host: "0.0.0.0",
    },
    // Handle resolve issues
    resolve: {
        alias: {
            "@": "/resources/js",
        },
    },
});
