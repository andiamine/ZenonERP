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

    'default_modules' => [],

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

];
