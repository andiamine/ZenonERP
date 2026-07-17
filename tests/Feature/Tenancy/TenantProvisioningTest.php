<?php

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('creates a tenant database and runs tenant migrations', function () {
    $tenant = createTenant('acme', 'Acme Inc.');

    expect(file_exists(database_path('zenon_tenant_acme.sqlite')))->toBeTrue();

    expect($tenant->run(fn () => Schema::hasTable('users')))->toBeTrue();
    expect($tenant->run(fn () => Schema::hasTable('sessions')))->toBeTrue();
    expect($tenant->run(fn () => Schema::hasTable('password_reset_tokens')))->toBeTrue();

    expect(Tenant::find('acme'))->not->toBeNull()
        ->and(Tenant::find('acme')->name)->toBe('Acme Inc.')
        ->and(DB::table('domains')->where('domain', 'acme')->exists())->toBeTrue();
});

it('provisions a separate database per tenant', function () {
    $acme = createTenant('acme');
    $beta = createTenant('beta');

    expect(file_exists(database_path('zenon_tenant_acme.sqlite')))->toBeTrue()
        ->and(file_exists(database_path('zenon_tenant_beta.sqlite')))->toBeTrue();

    $acme->run(fn () => DB::table('users')->insert([
        'name' => 'Acme User',
        'email' => 'user@acme.test',
        'password' => 'secret',
    ]));

    expect($acme->run(fn () => DB::table('users')->count()))->toBe(1)
        ->and($beta->run(fn () => DB::table('users')->count()))->toBe(0);
});

it('deletes the tenant database when the tenant is deleted', function () {
    $tenant = createTenant('acme');

    expect(file_exists(database_path('zenon_tenant_acme.sqlite')))->toBeTrue();

    $tenant->delete();

    expect(file_exists(database_path('zenon_tenant_acme.sqlite')))->toBeFalse();
});
