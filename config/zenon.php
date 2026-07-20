<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Deployment Mode
    |--------------------------------------------------------------------------
    |
    | "saas"       - multi-tenant: subdomain per tenant, DB auto-provisioning.
    | "standalone" - single tenant on commodity hosting, provisioned by the
    |                installer wizard using pre-created database credentials.
    |
    */

    'mode' => env('ZENON_MODE', 'saas'),

    /*
    |--------------------------------------------------------------------------
    | Platform Version
    |--------------------------------------------------------------------------
    |
    | Semver version of the platform contract consumed by addon manifests
    | ("platform": "^1.0"). Remote frontends built against an incompatible
    | major are refused by the loader before mounting. env-only (not a config
    | key elsewhere) so the runtime-mismatch drill can be exercised without a
    | code change — bump ZENON_PLATFORM_VERSION and reboot.
    |
    */

    'platform_version' => env('ZENON_PLATFORM_VERSION', '1.0.0'),

    /*
    |--------------------------------------------------------------------------
    | Default Modules
    |--------------------------------------------------------------------------
    |
    | Module aliases enabled for every newly provisioned tenant, in addition
    | to all modules flagged "core": true (which are always auto-enabled).
    |
    */

    'default_modules' => ['sequence', 'audit'],

    /*
    |--------------------------------------------------------------------------
    | Registry Cache TTL
    |--------------------------------------------------------------------------
    |
    | Seconds the per-tenant module enablement map is cached before being
    | re-read from the central tenant_modules table.
    |
    */

    'registry_cache_ttl' => (int) env('ZENON_REGISTRY_CACHE_TTL', 3600),

    /*
    |--------------------------------------------------------------------------
    | Company Model
    |--------------------------------------------------------------------------
    |
    | String indirection (class-string, resolved lazily) so app/Foundation stays
    | module-free — it must never `use Modules\...` directly. The Company model
    | ships with the zenon/core module; nothing dereferences this until a model
    | using BelongsToCompany calls the trait's company() relation.
    |
    */

    'company_model' => 'Modules\\Core\\Models\\Company',

    /*
    |--------------------------------------------------------------------------
    | Third-Party Module Root
    |--------------------------------------------------------------------------
    |
    | Resolution root for zip-installed addons (nwidart scan path, CLAUDE.md §1)
    | and the test seam ModuleAssetController resolves dist files under — a
    | config indirection rather than a hard-coded base_path() so tests can
    | point it at a temp fixture dir without a real addon on disk.
    |
    */

    'thirdparty_path' => base_path('modules/thirdparty'),

    /*
    |--------------------------------------------------------------------------
    | Kernel Module
    |--------------------------------------------------------------------------
    |
    | The module alias SetCurrentCompany gates company resolution on (must be
    | enabled for the tenant + bind Contracts\CompanyResolver before the header
    | is honored). Production always uses "core". Overridable purely for
    | testability: zenon/core doesn't exist until a later Phase 5 task, so tests
    | point this at a fixture module (DummyCore, alias "dummycore") to exercise
    | the positive path before Core is built.
    |
    */

    'kernel_module' => 'core',

    /*
    |--------------------------------------------------------------------------
    | Reserved Subdomains
    |--------------------------------------------------------------------------
    |
    | Subdomains that can never be claimed as tenant identifiers — they are
    | (or may become) infrastructure hostnames on the platform base domain.
    |
    */

    'reserved_subdomains' => [
        'app',
        'www',
        'api',
        'admin',
        'central',
        'mail',
        'smtp',
        'ftp',
        'status',
        'install',
        'assets',
        'cdn',
        'staging',
        'test',
    ],

    /*
    |--------------------------------------------------------------------------
    | Installer
    |--------------------------------------------------------------------------
    |
    | The standalone-mode /install wizard (CLAUDE.md §7/§12 Phase 8) self-disables once
    | provisioning completes by writing a lock file — its route group refuses to mount
    | again once lock_path exists. env_path indirects the .env file the wizard writes
    | credentials into; null means "the real environment file"
    | (Illuminate\Foundation\Application::environmentFilePath()) — a test seam so tests
    | can point the wizard at a throwaway path instead of the real .env.
    |
    */

    'installer' => [
        'lock_path' => storage_path('framework/installed.lock'),
        'env_path' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Composer
    |--------------------------------------------------------------------------
    |
    | Location of the bundled composer.phar (bin/composer.phar, gitignored — never
    | committed) that the addon-install pipeline shells out to for `dump-autoload` on
    | hosts with no system-wide Composer (the Plesk/cPanel unlock, CLAUDE.md §7).
    | php_binary overrides the PHP binary the phar is invoked with when the CLI running
    | artisan differs from the one on PATH; null falls back to PHP_BINARY.
    |
    */

    'composer' => [
        'phar_path' => env('ZENON_COMPOSER_PHAR', base_path('bin/composer.phar')),
        'php_binary' => env('ZENON_COMPOSER_PHP_BINARY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Addon Zip Limits
    |--------------------------------------------------------------------------
    |
    | Zip-bomb guards for the addon zip pipeline (CLAUDE.md §7 Phase 7, hardened here
    | for the admin-upload UI deferred to Phase 9/M2): a single entry's decompressed
    | size, the sum of all entries, and the entry count are all capped — env-tunable so
    | a pathological-but-legitimate addon isn't stuck without a code change.
    |
    */

    'addon_zip' => [
        'max_entry_bytes' => (int) env('ZENON_ADDON_ZIP_MAX_ENTRY_BYTES', 50 * 1024 * 1024),
        'max_total_bytes' => (int) env('ZENON_ADDON_ZIP_MAX_TOTAL_BYTES', 250 * 1024 * 1024),
        'max_entries' => (int) env('ZENON_ADDON_ZIP_MAX_ENTRIES', 10_000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Release Packaging
    |--------------------------------------------------------------------------
    |
    | zenon:release:package (Phase 8) stages a --no-dev vendor build plus the prebuilt
    | SPA assets into out_dir. source_root is a test seam — the packager must read this
    | instead of calling base_path() directly — so tests can point it at a fixture tree
    | rather than packaging the real repo.
    |
    */

    'release' => [
        'source_root' => base_path(),
        'out_dir' => storage_path('app/releases'),
    ],

];
