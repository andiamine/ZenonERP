<?php

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('provisions tenant, domain and database', function () {
    $this->artisan('zenon:tenant:create', ['subdomain' => 'acme', '--name' => 'Acme Inc.'])
        ->assertSuccessful();

    $tenant = Tenant::find('acme');

    expect($tenant)->not->toBeNull()
        ->and($tenant->name)->toBe('Acme Inc.')
        ->and(DB::table('domains')->where('domain', 'acme')->exists())->toBeTrue()
        ->and(file_exists(database_path('zenon_tenant_acme.sqlite')))->toBeTrue()
        ->and($tenant->run(fn () => Schema::hasTable('users')))->toBeTrue();
});

it('defaults the display name to a headline-cased subdomain', function () {
    $this->artisan('zenon:tenant:create', ['subdomain' => 'acme-corp'])
        ->assertSuccessful();

    expect(Tenant::find('acme-corp')->name)->toBe('Acme Corp');
});

it('fails on a reserved subdomain without side effects', function () {
    $this->artisan('zenon:tenant:create', ['subdomain' => 'app'])
        ->assertFailed();

    expect(Tenant::count())->toBe(0)
        ->and(file_exists(database_path('zenon_tenant_app.sqlite')))->toBeFalse();
});

it('fails on a duplicate subdomain', function () {
    createTenant('acme');

    $this->artisan('zenon:tenant:create', ['subdomain' => 'acme'])
        ->assertFailed();

    expect(Tenant::count())->toBe(1);
});
