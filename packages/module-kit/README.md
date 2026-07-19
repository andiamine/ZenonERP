# @zenon/module-kit

Build toolkit for ZenonERP third-party addons. `@zenon/module-kit` turns an addon's
`resources/js/index.ts` (a default-exported `ZenonModule`) into a prebuilt Module
Federation remote — `dist/remoteEntry.js` — that the ZenonERP host loads at runtime.
No server-side Node is needed to install an addon; the whole `dist/` directory ships
inside the addon zip.

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
- `remoteEntry.js` — the MF container entry, loaded by the host
- `assets/*` — the addon's compiled JS/CSS chunks
- `mf-manifest.json` — the MF manifest (`manifest: true`)

Ship the whole `dist/` directory in the addon zip.

## Allowed imports

- `@zenon/core` and its subpaths (`@zenon/core/ui`, `@zenon/core/apiClient`,
  `@zenon/core/permissions`, `@zenon/core/bootstrap`, `@zenon/core/store`) — shared
  singletons, consumed from the host.
- The host's framework singleton list: `react`, `react-dom` (and their subpath
  entries), `@base-ui/react`, `@tanstack/react-router`, `@tanstack/react-query`,
  `@tanstack/react-table`, `zustand`, `i18next`, `react-i18next`.
- Everything else is allowed but gets bundled into the addon's own `dist/` output
  (discouraged — it bloats the addon and can't benefit from host caching/version
  pinning).

react, `@zenon/core`, and the rest of the shared list are declared with
`import: false` in the federation config — the kit never lets the addon bundle its
own copy. This is deliberate: **the remote cannot run standalone**, only mounted
inside a host that provides these singletons.

## CSS

An addon's stylesheet must consume the kit's preset rather than plain
`@import 'tailwindcss'`:

```css
@import '@zenon/module-kit/css/preset.css';
@source '../js';
```

(`@source` is relative to the CSS file — point it at the addon's `resources/js`
directory.) This compiles the addon's utility classes against the host's design
tokens (`css/tokens.css`, mirroring the host's `@theme inline` block) with preflight
OFF — the host owns preflight/base styles, and re-emitting them from an addon would
fight the host's cascade.

## Naming

- Manifest alias → MF container name: `zenon_addon_{alias}` (`remoteNameForAlias`).
- The exposed module is always `./module` (`exposes: { './module': './' + entry }`).
- The addon entry (`resources/js/index.ts` by default) must default-export a
  `ZenonModule` object (see `@zenon/core`'s `ZenonModule` type).

## `platform` grammar

The addon's `module.json` → `zenon.platform` field must be `^MAJOR[.MINOR]`
(e.g. `^1.0`, `^1`). The host loader refuses to mount a remote whose `platform`
string it cannot parse, or whose major doesn't match — with an admin notice, never
a crash.

## Dev workflow

Addon frontends are prebuilt artifacts, not dev-server-served modules — there is no
addon HMR. Run `zenon-module build --watch` in the addon directory and reload the
host page to pick up changes.

## Types

In-repo (first-party-adjacent) addons point their `tsconfig.json` `paths` at
`resources/js/core` directly for `@zenon/core` types. A standalone published types
package for external, out-of-repo addon authors is planned but not yet available.
