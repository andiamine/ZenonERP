# Standalone deployment (Plesk/cPanel)

Standalone mode runs ZenonERP as a single tenant on commodity shared hosting —
`zenon.mode=standalone`, one pre-created MySQL/MariaDB database pair instead of
per-tenant auto-provisioning, and a self-disabling `/install` wizard instead of
the central signup API. The release zip ships prebuilt SPA assets and an
already-installed `vendor/` tree, so routine operation needs no Node.js and no
system-wide Composer on the host.

## Requirements

Checked live by the installer's Requirements step (`/install`, step 1) before it
lets you continue — anything marked **blocking** below fails the check and must
be fixed first; anything marked **warning** is surfaced but doesn't stop you:

- **PHP >= 8.3** (blocking).
- **PHP extensions** (blocking, each checked individually): `pdo_mysql`,
  `mbstring`, `openssl`, `curl`, `dom`, `fileinfo`, `xml`, `zip`.
- **MySQL 8+ or MariaDB 10.3+** — two pre-created, empty databases (see
  [Install](#install-on-pleskcpanel) below). Not separately version-checked by
  the installer; the `pdo_mysql` extension check above is the closest live
  signal.
- **Apache with `mod_rewrite`** (the shipped `public/.htaccess` handles all
  rewriting — no manual Apache config needed beyond the document root), **or
  nginx** with a standard Laravel-style server block (`try_files` to
  `index.php`) that you configure yourself — ZenonERP doesn't ship an nginx
  config.
- **Writable, blocking**: the directory holding `.env`, `storage/`,
  `bootstrap/cache/`.
- **Writable, warning only**: `modules/thirdparty/` (only needed once the
  admin addon-upload UI ships — Phase 9/M2 roadmap; CLI install works
  regardless).
- **Existence, warning only**: `public/build/manifest.json` (a dev/test
  wizard walkthrough may legitimately run against an unbuilt SPA) — this
  checks the file is present, not that it's writable.
- **No Node.js, no system Composer** required to install or run: the full
  release zip carries prebuilt `public/build/` assets and a `--no-dev`
  `vendor/` tree, plus a bundled `bin/composer.phar` for the one addon-related
  case that still needs Composer (see [Installing addons](#installing-addons)).
  **SSH/terminal access is not required at all** — cron is configured through
  your panel's UI, and the wizard's Migrate step installs the release's
  first-party modules itself (see [Install](#install-on-pleskcpanel) below).

## Install on Plesk/cPanel

1. **Create two databases** in your panel (Plesk: Databases; cPanel: MySQL
   Databases) — one for the central platform tables, one for the tenant. Most
   shared-hosting accounts only allow one DB user, so **grant that single user
   on both databases**; the installer's Database step accepts blank
   host/port/username/password for the tenant and falls back to the matching
   central value, matching this common case.
2. **Upload and extract the full release zip *outside* the web root**, e.g.
   into your home directory as `zenonerp/` — not directly into `public_html/`.
   Every file under the app (including `.env` once written) must stay
   unreachable from the web; only `public/` is meant to be served.
3. **Point the domain's document root at `zenonerp/public`**:
   - **Plesk**: Websites & Domains → the domain → *Hosting Settings* →
     *Document root* → browse to `zenonerp/public`.
   - **cPanel — addon domain or subdomain**: the *Domains*/*Subdomains* page
     lets you set the document root directly to `zenonerp/public`.
   - **cPanel — primary domain caveat**: cPanel typically hard-codes the
     primary domain's docroot to `public_html` with no UI to repoint it. Two
     workarounds: install under an addon domain/subdomain instead, or place
     the app one level above `public_html` and replace `public_html` with a
     symlink to `zenonerp/public` (empty/rename the original `public_html`
     first — File Manager or one `ln -s` command via SSH if available).
4. **Browse to `https://your-domain/install`.** The shipped `.htaccess`
   already routes everything through `index.php`; no further Apache
   configuration is needed. Follow the wizard: Requirements → Database →
   Migrate → Tenant → Admin → Finish. The Database step writes `.env`
   (`APP_KEY`, DB credentials, `ZENON_MODE=standalone`) only after it
   successfully connects to *both* databases; every step is safe to re-POST if
   the browser is closed mid-wizard (the wizard resumes from `GET
   /install/api/status`). The Migrate step creates the platform's central
   schema (`tenants`/`domains`/`modules`/`tenant_modules`/`jobs`/`cache`/
   `sessions`) **and** installs every first-party module the release ships
   (central `modules` rows — everything under `modules/zenon/` in this
   build) in the same request, so the Tenant/Admin steps that follow always
   have something to enable. No terminal access, no separate CLI step —
   the wizard alone is enough on a genuinely fresh extract.

   **Complete the wizard immediately after upload.** Until Finish is clicked
   (writing `storage/framework/installed.lock`), `/install` is claimable by
   whoever reaches the URL first — same-origin checking blocks cross-site
   forgery, not a race to the URL from an ordinary browser.

   **Recovery**: if you need to re-open the wizard after Finish (or after an
   attempt was interrupted), delete `storage/framework/installed.lock` — via
   your panel's file manager, or `rm storage/framework/installed.lock` over
   SSH if available — and `/install` becomes reachable again immediately.

## Cron

One cron line drives both the scheduler and the queue — there is no separate
worker process to supervise on shared hosting:

```
* * * * * cd /path/to/zenonerp && php artisan schedule:run >> /dev/null 2>&1
```

- **Plesk**: Tools & Settings → Scheduled Tasks → add a task running the
  command above every minute, on the PHP version matching the Requirements
  check (Plesk's task editor has a PHP-version dropdown).
- **cPanel**: Cron Jobs → "Every Minute" → the same command line. If `php`
  isn't on `PATH`, use the version-suffixed binary your host provides, e.g.
  `/usr/local/bin/ea-php83 artisan schedule:run` (path varies by host —
  check cPanel's "Select PHP Version" page for the exact binary path).
- Every tick, `schedule:run` also runs `queue:work --stop-when-empty
  --tries=3 --max-time=50` in the foreground (registered by
  `App\Foundation\Standalone\StandaloneSchedule`, gated on standalone mode) —
  this is the same cron line draining the queue, no `queue:work` daemon or
  Supervisor needed.
- **Verify**: `curl -s -o /dev/null -w "%{http_code}\n"
  https://your-domain/up` should return `200`. To confirm the queue side,
  trigger anything that dispatches a job (e.g. `php artisan
  zenon:module:upgrade {alias}` after a code update — see
  [Updates](#updates) — dispatches a per-tenant job batch) and confirm it
  completes within about a minute of the next cron tick.

## Updates

1. **Extract the update zip over the existing install**, overwriting in
   place. Preserved *by construction* — none of these are in the update zip
   to begin with:
   - `.env` (update zips never contain one).
   - Everything real under `storage/`, including `storage/framework/
     installed.lock` (the update zip only ships `storage/**` as an empty
     skeleton — `.gitignore` placeholders, no data files).
   - `modules/thirdparty/` (no entry at all in an update zip — installed
     addons are untouched).
   - `public/build/` **is** replaced with the new build: `manifest.json`
     now points only at the new content-hashed filenames, so old-hash chunk
     files left on disk from the previous version are simply never
     referenced again (extraction doesn't delete files absent from the zip,
     but nothing requests them either).
   - `vendor/` overlays the same way `public/build/` does: the update zip's
     `composer install --no-dev` output is extracted on top of the existing
     tree. Extraction never deletes files absent from the new zip, so a
     package removed between versions leaves stale files on disk — inert,
     since nothing in the new autoloader references them.
2. **Finalize** (CLI/SSH; the web update-finalizer is Phase 9 roadmap):
   ```bash
   cd /path/to/zenonerp
   php artisan migrate --force
   php artisan zenon:module:doctor
   ```
   `zenon:module:doctor` reports which installed modules' on-disk version now
   differs from the central `modules` row (and any pending tenant
   migrations). For each one it flags, run:
   ```bash
   php artisan zenon:module:upgrade {alias}
   ```
   which bumps the central row and queues a per-tenant migrate+reseed job —
   drained automatically by the next cron tick (see [Cron](#cron)). Re-run
   `zenon:module:doctor` afterward to confirm it converges to "All modules
   healthy".

   **If the update zip adds a brand-new first-party module** (one that
   didn't exist in your previous install — check the release notes),
   `zenon:module:doctor` won't mention it: doctor only walks modules that
   already have a central `modules` row, and a module your install has
   never seen has none yet. Install it once, then enable it per tenant
   (`default` on standalone):
   ```bash
   php artisan zenon:module:install {alias}
   php artisan zenon:module:enable {alias} --tenant=default
   ```
   This is a genuinely different situation from first install — the
   `/install` wizard's Migrate step (see [Install](#install-on-pleskcpanel))
   installs every first-party module the release ships **at that point in
   time**; it never runs again on an already-installed site, so a module
   introduced by a later update still needs this one CLI step today. Giving
   updates the same "roll it in automatically" treatment as fresh install is
   Phase 9 roadmap.
3. **If any third-party addons are installed, this step is REQUIRED and the
   site is DOWN until you run it** (verified in the Phase 8 acceptance
   drill): the moment the update zip's `vendor/` lands, every route AND
   every artisan command fatals with
   `Class "Modules\{Addon}\Providers\...ServiceProvider" not found` — even
   `zenon:module:doctor` cannot boot. Recovery is one command (it does not
   boot Laravel, so it always works):
   ```bash
   php bin/composer.phar dump-autoload
   ```
   Why: the update zip's `vendor/` was built via `composer install` in a
   staging tree where `modules/thirdparty/` is deliberately empty (that's
   what keeps your addon *files* safe), so its autoloader has no knowledge
   of your addons' merged autoload entries (`composer.json`'s
   `merge-plugin.include` picks up `modules/thirdparty/*/composer.json`).
   The bundled `bin/composer.phar` (shipped in every zip) makes the fix work
   with no system-wide Composer. Addons installed via
   `zenon:module:install-zip` already had this run for them at install time —
   this only matters after an update replaces `vendor/`.

   > **No SSH / panel-only hosting?** Do not apply update zips while
   > third-party addons are installed unless your panel offers a way to run
   > the command above (Plesk "Run a command" scheduled task, cPanel cron
   > with a one-shot schedule, or a terminal feature). Plan the update for a
   > maintenance window either way — the outage lasts from extraction until
   > the command runs. The Phase 9 web update-finalizer will close this gap.
4. If you rely on `config:cache`/`route:cache` in production, re-run them —
   `bootstrap/cache/` ships skeleton-only in every zip, so cached artifacts
   from before the update are left untouched, not automatically refreshed.

## Installing addons

No web upload UI yet (Phase 9/M2 roadmap) — third-party addon zips install
via CLI:

```bash
cd /path/to/zenonerp
php artisan zenon:module:install-zip storage/app/incoming/vendor-addon.zip
php artisan zenon:module:enable {alias} --tenant=default
```

- `zenon:module:install-zip` extracts the zip into `modules/thirdparty/{Name}`
  (zip-slip guarded; size/entry-count limits are env-tunable via
  `ZENON_ADDON_ZIP_MAX_*`, defaulting to 50 MB/entry, 250 MB total, 10,000
  entries), runs `composer dump-autoload` through the bundled
  `bin/composer.phar` automatically (no system Composer needed), then the
  normal module-install flow (central migration + `modules` row).
- `zenon:module:enable {alias} --tenant=default` migrates and seeds the
  addon inside the tenant database and enables it — `default` is always the
  standalone tenant's id.
- To build your own distributable zip from a folder under
  `modules/thirdparty/`, use `php artisan zenon:module:package {FolderName}`
  (this is also how the in-repo Demo addon is packaged for manual transfer —
  Demo itself never ships inside a release zip).

## Building a release

Maintainer-facing — run from the development machine, not the target host:

```bash
npm run build
php artisan zenon:release:package            # full release zip
php artisan zenon:release:package --update   # update zip (see Updates above)
```

- `zenon:release:package` preflights before staging anything: the committed
  `resources/js/generated/module-registry.ts` must be fresh (it runs
  `zenon:frontend:generate --check` itself — regenerate and commit first if
  it's stale), `public/build/manifest.json` must exist (`npm run build`
  first), the bundled `bin/composer.phar` must exist, and the working tree
  must be git-clean (`--allow-dirty` downgrades that to a warning).
- If `bin/composer.phar` is missing, fetch it once:
  ```bash
  php -r "copy('https://getcomposer.org/download/latest-2.x/composer.phar','bin/composer.phar');"
  ```
- The command stages a filtered copy of the repo, runs `composer install
  --no-dev --prefer-dist --optimize-autoloader --no-scripts` against the
  staging copy only (never the real `vendor/`), copies the phar to
  `bin/composer.phar` inside the zip, and — full zips only — stages `.env`
  from the committed `.env.standalone` template (no secrets; the installer
  wizard fills in real credentials). Update zips never contain `.env` or a
  `modules/thirdparty/` entry; full zips ship `modules/thirdparty/` with only
  a `.gitkeep` placeholder (a clean product release — the Demo addon is
  excluded and distributed separately via `zenon:module:package Demo`).
- Output: `storage/app/releases/zenonerp-{version}.zip` (or
  `-{version}-update.zip`), or wherever `--out=` points.
