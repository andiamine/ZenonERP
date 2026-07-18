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
    | major are refused by the loader before mounting.
    |
    */

    'platform_version' => '1.0.0',

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

];
