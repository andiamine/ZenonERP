# Demo

Phase 7 extension proof (CLAUDE.md §12 Phase 7): a real third-party addon, installed
into the `modules/thirdparty/` scan path exactly the way a marketplace addon would be,
that consumes `zenon/core`'s hooks without touching anything outside its own `Contracts\*`
surface:

- Filters `Modules\Core\Contracts\Hooks\CompanyApiResponse` to add computed insight
  fields to the companies API response (`AddCompanyInsights`).
- Filters `Modules\Core\Contracts\Hooks\CompanyDeleting` to veto deletion of any
  company whose code is `LOCKED` (`VetoProtectedCompanyDeletion`).
- Listens for `Modules\Core\Contracts\Events\CompanyDeleted` and records each deletion
  in-process, plus a log line (`RecordCompanyDeletion`).

Zero routes, zero migrations, zero permissions, no `DemoModule` lifecycle class — this
addon exists purely to prove the extension surface, not to ship a feature.

## Folder naming (deliberate deviation)

CLAUDE.md §"Module identity & distribution" says third-party addons get a vendor-derived
StudlyCase folder (e.g. `modules/thirdparty/AcmeCreditControl`) so folder collisions with
other vendors are impossible. This in-repo demo deliberately keeps the plain folder name
`Demo` (= the manifest `name`) instead — it is a first-party fixture living in the
third-party scan path to exercise that path end-to-end, not a real marketplace product
(decision D4 of the Phase 7 plan). A real third-party addon vendored for distribution
should follow the vendor-prefixed convention.

## Build / package / install

The PHP half here is the backend consumer; the frontend half is a `ZenonModule` built as
a prebuilt Module Federation remote via `@zenon/module-kit`, with its compiled `dist/`
output committed alongside the source — `module.json` points `zenon.frontend.remote` at
`dist/remoteEntry.js`.

To rebuild the frontend after changing `resources/js/`:

```
npm install       # first time only (or after a @zenon/module-kit / lockfile change)
npm run build     # zenon-module build — compiles resources/js → dist/remoteEntry.js (no CSS: styling comes from the host's MUI theme at mount)
```

To package and install the addon exactly as a marketplace zip-install would:

```
php artisan zenon:module:package Demo       # zips modules/thirdparty/Demo → storage/app/packages/acme-demo-1.0.0.zip
php artisan zenon:module:install-zip <zip>  # installs the zip into modules/thirdparty/
```

Then enable the module per tenant as usual (`zenon:module:enable demo --tenant=...` /
the admin UI) — no SPA rebuild, no Node needed on the server.
