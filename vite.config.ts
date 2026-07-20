import { federation } from '@module-federation/vite';
// @module-federation/runtime is pinned to 2.8.0 in package.json. It MUST equal the version in
// @module-federation/vite's own dependencies (1.18.2 → runtime 2.8.0) so npm dedupes to a single
// runtime shared by the plugin and the host — upgrade both together.
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
            // The app shell is a Blade view — there is no index.html for the plugin to inject
            // its host-init into (default 'html'), so pin it to the JS entry explicitly. Makes
            // the otherwise-implicit fallback deterministic.
            hostInitInjectLocation: 'entry',
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
                '@tanstack/react-table': { singleton: true, requiredVersion: '8.21.3' },
                // @zenon/core platform surface (Phase 7). Explicit keys register eagerly at
                // configResolved (deterministic share scope regardless of the host import graph);
                // the trailing-slash key is a lazy catch-all for any other subpath.
                // version = platform version (config/zenon.php + resources/js/core/package.json).
                '@zenon/core': { singleton: true, version: '1.0.0', requiredVersion: '^1.0.0' },
                '@zenon/core/': { singleton: true, version: '1.0.0', requiredVersion: '^1.0.0' },
                '@zenon/core/ui': { singleton: true, version: '1.0.0', requiredVersion: '^1.0.0' },
                '@zenon/core/apiClient': { singleton: true, version: '1.0.0', requiredVersion: '^1.0.0' },
                '@zenon/core/permissions': { singleton: true, version: '1.0.0', requiredVersion: '^1.0.0' },
                '@zenon/core/bootstrap': { singleton: true, version: '1.0.0', requiredVersion: '^1.0.0' },
                '@zenon/core/store': { singleton: true, version: '1.0.0', requiredVersion: '^1.0.0' },
                // moduleTypes is type-only (compiles to an empty runtime module) but MUST be an
                // explicit share: @module-federation/vite's remote export scanner registers it as
                // a demanded key on any remote that `import type`s it (the root barrel's
                // `export type * from './moduleTypes'` is followed textually), and the
                // trailing-slash catch-all above only materializes from host-side VALUE imports —
                // the host only type-imports moduleTypes, so without this explicit key it never
                // lands in the share scope and the remote's import:false proxy throws at init.
                '@zenon/core/moduleTypes': { singleton: true, version: '1.0.0', requiredVersion: '^1.0.0' },
            },
        }),
    ],
    resolve: {
        alias: {
            // '@zenon/core' is NOT aliased: Vite resolves resolve.alias before user plugins,
            // so an aliased bare specifier would bypass the MF share proxy (resolveId) and could
            // never be shared with runtime remotes. It is a real package now (file: dep →
            // resources/js/core/package.json) and is declared in the federation shared config below.
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
