import { federation } from '@module-federation/vite';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import path from 'node:path';
import { defineConfig } from 'vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/main.tsx'],
            refresh: true,
        }),
        react(),
        tailwindcss(),
        // Module Federation HOST (CLAUDE.md §7): present from Phase 4 so the host build
        // proves Router + MF compile together (§13). Remotes are registered at RUNTIME
        // in Phase 7 from bootstrap.remote_modules — none are configured here, ever.
        federation({
            name: 'zenon_host',
            // dts generation is a remote-author concern (@zenon/module-kit); disabling it
            // also keeps the vulnerable adm-zip dev path (@module-federation/dts-plugin)
            // out of the build.
            dts: false,
            shared: {
                react: { singleton: true, requiredVersion: '19.2.7' },
                // Trailing-slash entries cover React 19 subpath imports (react-dom/client,
                // react/jsx-runtime) which bypass exact-name sharing (§13).
                'react/': { singleton: true, requiredVersion: '19.2.7' },
                'react-dom': { singleton: true, requiredVersion: '19.2.7' },
                'react-dom/': { singleton: true, requiredVersion: '19.2.7' },
                '@base-ui/react': { singleton: true, requiredVersion: '1.6.0' },
                '@tanstack/react-router': { singleton: true, requiredVersion: '1.170.18' },
                '@tanstack/react-query': { singleton: true, requiredVersion: '5.101.2' },
                zustand: { singleton: true, requiredVersion: '5.0.14' },
                i18next: { singleton: true, requiredVersion: '26.3.6' },
                'react-i18next': { singleton: true, requiredVersion: '17.0.10' },
                // TODO(Phase 5): add '@tanstack/react-table' when it is installed.
                // TODO(Phase 7 spike): share '@zenon/core' — it is a path alias, not a
                // resolvable package; needs a package.json in resources/js/core first.
            },
        }),
    ],
    resolve: {
        alias: {
            '@zenon/core': path.resolve(import.meta.dirname, 'resources/js/core'),
            '@modules': path.resolve(import.meta.dirname, 'modules/zenon'),
            '@generated': path.resolve(import.meta.dirname, 'resources/js/generated'),
        },
    },
    build: {
        rolldownOptions: {
            output: {
                advancedChunks: {
                    groups: [{ name: 'vendor', test: /[\\/]node_modules[\\/]/ }],
                },
            },
        },
    },
    server: {
        // No `cors` override: laravel-vite-plugin's default already allows APP_URL plus
        // every http(s)://*.test origin, which covers app.zenonerp.test and all tenant
        // subdomains for HMR. Setting our own value would REPLACE that default.
        watch: { ignored: ['**/storage/**', '**/vendor/**'] },
    },
});
