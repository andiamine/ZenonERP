# @zenon/module-kit

Build toolkit for ZenonERP third-party addons. `@zenon/module-kit` turns an addon's
`resources/js/index.ts` (a default-exported `ZenonModule`) into a prebuilt Module
Federation remote — `dist/remoteEntry.js` — that the ZenonERP host loads at runtime.
No server-side Node is needed to install an addon; the whole `dist/` directory ships
inside the addon zip.

**2.0.0** — the MUI migration release: the Tailwind pipeline (css/preset.css,
css/tokens.css, the per-addon compiled stylesheet) is gone. Addons ship **no CSS**;
all styling arrives at mount time through the host's MUI theme via the shared
`@mui/material`/`@emotion` singletons. Addons built against kit 1.x must be rebuilt.

## Authoring a `vite.config.ts`

```ts
import { defineAddonConfig } from '@zenon/module-kit';

export default defineAddonConfig({ alias: 'demo' });
```

That's it. `alias` must match the addon's `module.json` alias (`^[a-z][a-z0-9_]*$`)
and is used to derive the MF container name (`zenon_addon_{alias}`). `entry` defaults
to `resources/js/index.ts` and rarely needs overriding.

## Building

```
zenon-module build
zenon-module build --watch
```

Output (in `dist/`):
- `remoteEntry.js` — the MF container entry, loaded by the host. Its `init(shareScope)`
  consumes the **host's** share scope (it does not spin up an isolated federation
  instance), so react/`@mui/material`/`@zenon/core`/the TanStack libs/etc. resolve to
  the host's copies.
- `assets/*` — the addon's compiled JS chunks plus the MF runtime-core glue
  (`dist-*.js`, ~60 KB) the container needs to `init`/`get`.
- `mf-manifest.json` — the MF manifest (`manifest: true`)

The output is a **browser-only** remote. `@module-federation/vite` also emits a federation
*host* init entry (`hostInit-*.js`) and a Node *SSR* remote entry (`remoteEntry.ssr.js` +
`virtual_mf-exposes-ssr*.js`) for every exposes build; a ZenonERP addon is a pure remote
mounted in a browser SPA host, so the kit prunes those artifacts from `dist/` (they are in
nobody's browser load graph) — do not expect them.

Ship the whole `dist/` directory in the addon zip.

## Allowed imports

- **`@mui/material` — ROOT BARREL ONLY** (`import { Button, Card } from '@mui/material';`).
  The root barrel is the addon platform contract: it is a shared singleton served by the
  host, and the host's theme styles everything. **Never import `@mui/material/<subpath>`**
  — subpath share entries only exist for paths the host itself imports, so a subpath
  import either bundles a private MUI copy into your addon or throws at mount.
  Everything MUI-adjacent you need (`styled`, `useTheme`, `alpha`, …) is re-exported
  from the root barrel.
- **`@mui/icons-material/<IconName>`** subpath default imports (e.g.
  `import AddOutlined from '@mui/icons-material/AddOutlined';`). Icons are **bundled
  into your dist** (~1–2 KB each) — the icons package is deliberately not shared; the
  `SvgIcon` runtime underneath resolves from the host (`@mui/material/SvgIcon` is an
  explicit host share key). Never import the `@mui/icons-material` root barrel.
- `@zenon/core` and its subpaths (`@zenon/core/ui`, `@zenon/core/apiClient`,
  `@zenon/core/permissions`, `@zenon/core/bootstrap`, `@zenon/core/store`,
  `@zenon/core/moduleTypes`) — shared singletons, consumed from the host.
  `@zenon/core/ui` now holds Zenon composites only (DataTable, Field, ConfirmDialog,
  ApiErrorAlert, NavIcon) — primitives come from `@mui/material`.
- The host's framework singleton list: `react`, `react-dom` (and their subpath
  entries), `@tanstack/react-router`, `@tanstack/react-query`, `@tanstack/react-table`,
  `zustand`, `i18next`, `react-i18next`.
- Do **not** import `@emotion/react`, `@emotion/styled`, `@mui/system`, or
  `@mui/styled-engine` — the Emotion engine is host-internal; style via `sx`/`styled`
  from the `@mui/material` root barrel.
- Everything else is allowed but gets bundled into the addon's own `dist/` output
  (discouraged — it bloats the addon and can't benefit from host caching/version
  pinning).

react, `@mui/material`, `@zenon/core`, and the rest of the shared list are declared
with `import: false` in the federation config — the kit never lets the addon bundle
its own copy. This is deliberate: **the remote cannot run standalone**, only mounted
inside a host that provides these singletons.

## Styling

There is no addon stylesheet. Style with MUI's `sx` prop / `styled` (from
`@mui/material`) against the host theme's tokens (`color: 'text.secondary'`,
`bgcolor: 'background.paper'`, `theme.palette.*`) — the host theme (light AND dark
color schemes) applies to your components automatically at mount. Never create your
own `ThemeProvider`. If an addon truly must ship a bespoke stylesheet, a plain CSS
import still works (`bundleAllCSS: true` bundles it into the remote), but this is an
escape hatch, not the pattern.

## Naming

- Manifest alias → MF container name: `zenon_addon_{alias}` (`remoteNameForAlias`).
- The exposed module is always `./module` (`exposes: { './module': './' + entry }`).
- The addon entry (`resources/js/index.ts` by default) must default-export a
  `ZenonModule` object (see `@zenon/core`'s `ZenonModule` type).

## `platform` grammar

Install-time (`zenon:module:install-zip`) accepts the full composer/semver grammar for
`zenon.platform` — but for an addon with a remote frontend, the SPA host loader is the
thing that actually evaluates it at mount time, and the loader only understands a
narrower grammar:

- `*` — always compatible.
- `^MAJOR[.MINOR[.PATCH]]` (e.g. `^1`, `^1.0`, `^1.2.3`) — caret range.
- a bare `MAJOR[.MINOR[.PATCH]]` (e.g. `1`, `1.0`, `1.2.3`) — exact-prefix match.

Anything else (`~1.2`, `>=1.0 <2.0`, `1.0 - 2.0`, …) is refused at mount, with an admin
notice, never a crash — and because a backend-installed addon's `install-zip` preflight
only validates against this narrower grammar when `zenon.frontend.remote` is set, an
addon with a remote frontend and an out-of-grammar `platform` string will install
cleanly and then be permanently unmountable. Always author a remote addon's `platform`
field in the grammar above. (Backend-only addons — `zenon.frontend.remote: null` — are
never mounted by the SPA loader and keep full composer/semver freedom.)

## Dev workflow

Addon frontends are prebuilt artifacts, not dev-server-served modules — there is no
addon HMR. Run `zenon-module build --watch` in the addon directory and reload the
host page to pick up changes.

## Types

In-repo (first-party-adjacent) addons point their `tsconfig.json` `paths` at
`resources/js/core` directly for `@zenon/core` types. A standalone published types
package for external, out-of-repo addon authors is planned but not yet available.

## Build-time resolution of shared packages

Even though `@mui/material` and `@zenon/core` (and its `/ui`, `/apiClient`, … subpaths)
are shared with `import: false` and never bundled, the addon build must still be able
to **resolve** them: the federation plugin statically enumerates each shared module's
named exports to generate the `import: false` re-export proxy. So both must be present
in the addon's `node_modules` at build time — add them as `devDependencies`:

- `@mui/material` (+ `@emotion/react`, `@emotion/styled` peers, and
  `@mui/icons-material` if you use icons) at the kit's pinned versions.
- in-repo: `"@zenon/core": "file:../../../resources/js/core"`
- external authors: the future published `@zenon/core` package.

Without them the build fails with `MISSING_EXPORT` / unresolved-import errors.
