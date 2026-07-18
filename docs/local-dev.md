# Local development (Windows WAMP)

ZenonERP runs multi-tenant on subdomains: `app.zenonerp.test` is the central
(platform) domain, `{tenant}.zenonerp.test` is a tenant. Local setup needs an
Apache vhost with a wildcard alias plus explicit Windows hosts entries.

## Apache vhost

In `C:\wamp64\bin\apache\<version>\conf\extra\httpd-vhosts.conf`:

```apache
<VirtualHost *:80>
    ServerName app.zenonerp.test
    ServerAlias *.zenonerp.test
    DocumentRoot "c:/wamp64/www/zenonerp2/public"
    <Directory "c:/wamp64/www/zenonerp2/public/">
        Options +Indexes +Includes +FollowSymLinks +MultiViews
        AllowOverride All
        Require local
    </Directory>
</VirtualHost>
```

Restart Apache after changes.

> `php artisan serve` cannot serve tenant subdomains — use the Apache vhost.

## Windows hosts file

`C:\Windows\System32\drivers\etc\hosts` supports **no wildcards** — add one
entry per dev tenant (edit as Administrator):

```
127.0.0.1 zenonerp.test app.zenonerp.test acme.zenonerp.test beta.zenonerp.test gamma.zenonerp.test
```

Add a line (or extend the list) whenever you create a new dev tenant. If you
tire of this, a local DNS proxy like Acrylic DNS supports wildcard entries.

## Database

MariaDB (WAMP) — the central DB is `zenonerp_central` (create it once, empty;
`php artisan migrate` fills it). Tenant DBs (`zenon_tenant_{slug}`) are created
automatically on tenant provisioning, which requires the `CREATE DATABASE`
privilege — the default local `root` with empty password (see `.env`) has it.

## Creating tenants

```bash
php artisan zenon:tenant:create acme --name="Acme Inc."
# or via the central signup API:
curl -X POST http://app.zenonerp.test/api/v1/signup \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"subdomain":"acme","name":"Acme Inc."}'
```

## Frontend (SPA)

Node 22 + npm. One Vite build serves every tenant (`resources/js/main.tsx` entry;
per-module chunks come from the generated registry).

```bash
npm install
npm run dev        # Vite dev server + HMR — page stays on Apache (:80), assets come
                   # from the Vite origin; laravel-vite-plugin's default CORS already
                   # allows every *.test origin, so tenant subdomains just work
npm run build      # production build → public/build, served by Apache (no Node at runtime)
npm run typecheck  # tsc --noEmit
npm run lint       # eslint (includes the cross-module import ban)
php artisan zenon:frontend:generate          # regenerate resources/js/generated/module-registry.ts (committed)
php artisan zenon:frontend:generate --check  # CI staleness gate — fails if the committed file is stale
```

Notes:
- `app.blade.php` guards `@vite` behind a manifest/hot-file check so PHP tests and
  CI run build-less; `@viteReactRefresh` before `@vite` is required for dev mode
  (React fast-refresh preamble) — don't remove either.
- If a killed dev server leaves a stale `public/hot`, delete it — Laravel will
  otherwise point asset URLs at the dead Vite origin.
- Dev tenant login: `admin@acme.test` / `password` at `http://acme.zenonerp.test`
  (central operator `ops@zenonerp.test` — no central UI yet, placeholder screen).

## Verifying tenancy

```bash
curl http://acme.zenonerp.test/api/v1/ping
# → {"data":{"tenant":"acme","database":"zenon_tenant_acme"}}

curl http://beta.zenonerp.test/api/v1/ping
# → {"data":{"tenant":"beta","database":"zenon_tenant_beta"}}

curl -s -o /dev/null -w "%{http_code}" http://app.zenonerp.test/api/v1/ping
# → 404 (tenant routes do not exist on the central domain)

mysql -u root -e "SHOW DATABASES LIKE 'zenon_tenant_%'"
```
