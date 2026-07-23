import { federation } from '@module-federation/vite';
import react from '@vitejs/plugin-react';
import { defineConfig } from 'vite';

// Host singleton pin table — the platform 1.x contract (mirrors the host's
// vite.config.ts shared block). A pin bump here is a new kit release.
//
// MUI (kit 2.0): addons import the @mui/material ROOT BARREL only — subpath share
// entries ('@mui/material/…') exist on the host only for paths the host itself
// value-imports, so they are not part of the addon contract (ESLint-enforced).
// '@mui/material/SvgIcon' is the one explicit subpath the host guarantees: addon-BUNDLED
// @mui/icons-material icons chain to `export { createSvgIcon } from '@mui/material/SvgIcon'`
// (spike-verified 2026-07-23 on 9.2.0 — re-verify on MUI upgrades).
// @mui/icons-material itself is never shared — addons bundle the few icons they use.
const HOST_SINGLETONS = {
    react: '19.2.7',
    'react/': '19.2.7',
    'react-dom': '19.2.7',
    'react-dom/': '19.2.7',
    '@mui/material': '9.2.0',
    '@mui/material/': '9.2.0',
    '@mui/material/SvgIcon': '9.2.0',
    '@emotion/react': '11.14.0',
    '@emotion/styled': '11.14.1',
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
    // Type-only (empty runtime module), but the remote export scanner demands it as an
    // explicit share key regardless — see the matching comment in the host's vite.config.ts.
    '@zenon/core/moduleTypes',
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
 * Drop the federation *host* + *SSR* artifacts that @module-federation/vite emits
 * unconditionally for every exposes build but that a ZenonERP addon — a browser-only,
 * pure remote (it exposes `./module`, consumes zero remotes) — never loads:
 *
 *  - `hostInit-*.js`  — the host auto-init entry. It initializes federation *as a host*
 *    (so an app can `loadRemote()` others). A pure remote is never a host; the emitted
 *    `remoteEntry.js` does not import it (verified: it is in nobody's load graph).
 *  - `remoteEntry.ssr.js` + `virtual_mf-exposes-ssr*.js` — the Node SSR remote entry and
 *    its exposes map. The SSR entry does a bare `import "@module-federation/runtime"`
 *    (Node-external) and is only fetched by an SSR host; ZenonERP's host is a browser SPA.
 *
 * In 1.18.2 these have no suppression option — hostInit is added at index.js ~L8187 and
 * emitted in buildStart ~L8723; the SSR entry is emitted at ~L7502-7514 — both gated only on
 * "has exposes", not on any user flag. So we prune them from the output bundle (Rollup/Vite
 * `generateBundle`), which is safe precisely because nothing in the browser `remoteEntry.js`
 * graph references them, and clear the now-dangling `ssrRemoteEntry` pointer from the runtime
 * manifest the host reads. The MF runtime-core glue (`dist-*.js`) is kept — the container
 * genuinely needs it to `init`/`get` against the host's share scope.
 *
 * @returns {import('vite').Plugin}
 */
function pruneRemoteOnlyArtifacts() {
    // A dist file that a browser-only remote never loads (see doc block above).
    const isPrunedFile = (fileName) =>
        /(^|\/)hostInit-[^/]*\.js$/.test(fileName) ||
        /(^|\/)remoteEntry\.ssr\.js$/.test(fileName) ||
        fileName.includes('virtual_mf-exposes-ssr');

    // Rewrite a runtime/stats manifest so it no longer points at pruned files.
    const scrubManifest = (asset) => {
        if (!asset || asset.type !== 'asset' || typeof asset.source !== 'string') return;
        try {
            const parsed = JSON.parse(asset.source);
            let changed = false;
            if (parsed?.metaData?.ssrRemoteEntry) {
                delete parsed.metaData.ssrRemoteEntry;
                changed = true;
            }
            if (Array.isArray(parsed?.buildOutput)) {
                const kept = parsed.buildOutput.filter((record) => !isPrunedFile(String(record?.fileName ?? '')));
                if (kept.length !== parsed.buildOutput.length) {
                    parsed.buildOutput = kept;
                    changed = true;
                }
            }
            if (changed) asset.source = JSON.stringify(parsed);
        } catch {
            // Leave the manifest untouched if it is not the shape we expect.
        }
    };

    return {
        name: 'zenon:prune-remote-only-artifacts',
        apply: 'build',
        enforce: 'post',
        generateBundle(_options, bundle) {
            for (const [fileName, output] of Object.entries(bundle)) {
                const chunkName = output.type === 'chunk' ? output.name : undefined;
                if (chunkName === 'hostInit' || chunkName === 'ssrRemoteEntry' || isPrunedFile(fileName)) {
                    delete bundle[fileName];
                }
            }
            scrubManifest(bundle['mf-manifest.json']);
            scrubManifest(bundle['mf-stats.json']);
        },
    };
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
            federation({
                name: remoteNameForAlias(alias),
                filename: 'remoteEntry.js',
                exposes: { './module': './' + entry },
                shared: buildShared(),
                // Addons normally ship ZERO CSS (kit 2.0 — styling arrives through the host's
                // MUI theme via the shared singletons). Kept on: it is a no-op with no CSS and
                // correctly bundles any bespoke stylesheet a future addon deliberately ships.
                bundleAllCSS: true,
                manifest: true,
                dts: false,
            }),
            pruneRemoteOnlyArtifacts(),
        ],
        build: {
            outDir: 'dist',
            emptyOutDir: true,
            target: 'es2022',
            // Headless remote: there is no app entry and no index.html. An empty `input`
            // gives Vite/Rolldown a defined (non-default) input so it does NOT fall back to
            // resolving `index.html` (UNRESOLVED_ENTRY) — @module-federation/vite reads this
            // via getBuildInput (index.js ~L4420) and, seeing a defined input with no HTML
            // file, leaves the exposed `./module` un-wrapped by any host bootstrap. The
            // container's own chunks (remoteEntry.js, virtualExposes, share proxies) are
            // emitted by the plugin regardless of this input.
            rollupOptions: { input: {} },
        },
    });
}
