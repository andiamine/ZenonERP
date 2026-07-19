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

The PHP half here is the backend consumer; the frontend half (a `ZenonModule` built as a
prebuilt Module Federation remote via `@zenon/module-kit`) and its `dist/` output land in
a later task — `module.json` already points `zenon.frontend.remote` at
`dist/remoteEntry.js`, which does not exist on disk yet.

Once the frontend lands, the intended workflow is:

```
npx zenon-module build           # compiles resources/js → dist/remoteEntry.js + CSS
php artisan zenon:module:package Demo       # zips modules/thirdparty/Demo for distribution
php artisan zenon:module:install-zip <zip>  # installs an addon zip into modules/thirdparty/
```

`zenon:module:package` / `zenon:module:install-zip` land in a parallel task; this addon
is already shaped to work with them once they exist.
