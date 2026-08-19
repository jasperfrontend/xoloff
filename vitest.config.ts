import vue from '@vitejs/plugin-vue';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vitest/config';

/**
 * Deliberately separate from vite.config.ts. The build config loads the
 * Laravel and Wayfinder plugins, which shell out to artisan and expect a
 * running application. Tests need neither.
 */
export default defineConfig({
    plugins: [vue()],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    test: {
        environment: 'jsdom',
        // Tests sit next to the code they cover, so the existing Prettier,
        // ESLint and vue-tsc globs over resources/ pick them up unchanged.
        include: ['resources/js/**/*.test.ts'],
    },
});
