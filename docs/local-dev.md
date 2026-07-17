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
