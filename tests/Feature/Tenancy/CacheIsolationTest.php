<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

it('prefixes cache per tenant while storing rows centrally', function () {
    config(['cache.default' => 'database']);

    $acme = createTenant('acme');
    $beta = createTenant('beta');

    tenancy()->initialize($acme);
    Cache::put('shared-key', 'acme-value', 60);
    expect(Cache::get('shared-key'))->toBe('acme-value');
    tenancy()->end();

    tenancy()->initialize($beta);
    expect(Cache::get('shared-key'))->toBeNull();
    Cache::put('shared-key', 'beta-value', 60);
    expect(Cache::get('shared-key'))->toBe('beta-value');
    tenancy()->end();

    // Central context sees neither tenant's value…
    expect(Cache::get('shared-key'))->toBeNull();

    // …and both rows physically live in the CENTRAL cache table (tenant DBs
    // have no cache table at all — a tenant-connection write would have thrown).
    $centralConnection = (string) config('tenancy.database.central_connection');

    expect(DB::connection($centralConnection)->table('cache')->count())->toBeGreaterThanOrEqual(2);
});

it('re-reads the tenant cache prefix on re-initialization', function () {
    config(['cache.default' => 'database']);

    $acme = createTenant('acme');

    tenancy()->initialize($acme);
    Cache::put('key', 'value', 60);
    tenancy()->end();

    tenancy()->initialize($acme);
    expect(Cache::get('key'))->toBe('value');
    tenancy()->end();
});
