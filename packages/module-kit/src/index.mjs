import { federation } from '@module-federation/vite';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import { defineConfig } from 'vite';

// Host singleton pin table — the platform 1.x contract (mirrors the host's
// vite.config.ts shared block). A pin bump here is a new kit release.
const HOST_SINGLETONS = {
    react: '19.2.7',
    'react/': '19.2.7',
    'react-dom': '19.2.7',
    'react-dom/': '19.2.7',
    '@base-ui/react': '1.6.0',
    '@tanstack/react-router': '1.170.18',
    '@tanstack/react-query': '5.101.2',
    '@tanstack/react-table': '8.21.3',
    zustand: '5.0.14',
    i18next: '26.3.6',
    'react-i18next': '17.0.10',
};

// @zenon/core shared surface — must mirror the host's '@zenon/core*' shared keys.
const CORE_SURFACE = [
    '@zenon/core',
    '@zenon/core/',
    '@zenon/core/ui',
    '@zenon/core/apiClient',
    '@zenon/core/permissions',
    '@zenon/core/bootstrap',
    '@zenon/core/store',
];

/**
 * Manifest alias → MF container name. Same contract as the host's remoteModules.ts.
 *
 * @param {string} alias
 * @returns {string}
 */
export function remoteNameForAlias(alias) {
    if (!/^[a-z][a-z0-9_]*$/.test(alias)) throw new Error(`invalid module alias "${alias}"`);
    return `zenon_addon_${alias}`;
}

/**
 * Build the shared-singleton map for an addon remote: every host framework singleton
 * plus the full `@zenon/core` surface, all with `import: false` so the addon dist
 * never bundles react/core/etc — it can only run mounted inside the host.
 *
 * @returns {Record<string, { singleton: true, import: false, requiredVersion: string }>}
 */
function buildShared() {
    /** @type {Record<string, { singleton: true, import: false, requiredVersion: string }>} */
    const shared = {};
    for (const [name, pin] of Object.entries(HOST_SINGLETONS)) {
        shared[name] = { singleton: true, import: false, requiredVersion: '^' + pin };
    }
    for (const name of CORE_SURFACE) {
        shared[name] = { singleton: true, import: false, requiredVersion: '^1.0.0' };
    }
    return shared;
}

/**
 * Define a ZenonERP addon's Vite config: a Module Federation remote exposing
 * `./module` (the addon's `ZenonModule` default export) and consuming the host's
 * shared singletons — react, the TanStack libs, zustand, i18next, and the full
 * `@zenon/core` surface — as externals (`import: false`), never bundled.
 *
 * @param {{ alias: string, entry?: string }} options
 * @returns {import('vite').UserConfig}
 */
export function defineAddonConfig({ alias, entry = 'resources/js/index.ts' }) {
    return defineConfig({
        // MF publicPath auto: assets resolve relative to remoteEntry's own URL, any mount path works.
        base: '',
        plugins: [
            react(),
            tailwindcss(),
            federation({
                name: remoteNameForAlias(alias),
                filename: 'remoteEntry.js',
                exposes: { './module': './' + entry },
                shared: buildShared(),
                bundleAllCSS: true,
                manifest: true,
                dts: false,
            }),
        ],
        build: {
            outDir: 'dist',
            emptyOutDir: true,
            target: 'es2022',
        },
    });
}
