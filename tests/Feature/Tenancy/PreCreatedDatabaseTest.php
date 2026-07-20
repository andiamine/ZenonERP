<?php

use App\Foundation\Modules\Models\TenantModule;
use App\Models\Tenant;
use Illuminate\Support\Facades\Schema;

/**
 * Task 2 (Phase 8, pipeline wrapper jobs — the pre-created DB seam): standalone
 * tenants set `tenancy_create_database => false` because the installer's database
 * was already created by the user (Plesk/cPanel, no CREATE DATABASE privilege).
 * Stock `Stancl\Tenancy\Jobs\CreateDatabase::handle()` returns `false` to signal
 * "skip creation" in that case — but `JobPipeline` treats ANY `false` return from
 * ANY job as "abort the rest of the pipeline" (verified
 * vendor/stancl/jobpipeline/src/JobPipeline.php:79), which would also skip
 * MigrateDatabase + ProvisionTenantModules. `App\Foundation\Tenancy\Jobs\CreateTenantDatabase`
 * swallows that `false` so the pipeline continues for pre-created-DB tenants;
 * `DeleteTenantDatabase` mirrors the guard on deletion so tenancy never DROPs/unlinks
 * a database it does not own.
 */
it('continues the TenantCreated pipeline for a pre-created-database tenant (load-bearing)', function () {
    installModule('core');

    $dbFile = database_path('precreated_standalone.sqlite');
    file_put_contents($dbFile, '');

    $tenant = Tenant::create([
        'id' => 'standalone-precreated',
        'tenancy_create_database' => false,
        'tenancy_db_name' => 'precreated_standalone.sqlite',
    ]);

    try {
        // MigrateDatabase ran against the pre-existing file → platform base tenant schema present.
        expect($tenant->run(fn () => Schema::hasTable('users')))->toBeTrue();

        // ProvisionTenantModules ran → core (core:true) was enabled via the identical enable flow.
        expect(TenantModule::query()
            ->where('tenant_id', 'standalone-precreated')
            ->where('module', 'core')
            ->where('enabled', true)
            ->exists())->toBeTrue();
    } finally {
        @unlink($dbFile);
    }
});

it('leaves the pre-created database file on disk when a pre-created-database tenant is deleted', function () {
    $dbFile = database_path('precreated_delete_test.sqlite');
    file_put_contents($dbFile, '');

    $tenant = Tenant::create([
        'id' => 'standalone-delete-test',
        'tenancy_create_database' => false,
        'tenancy_db_name' => 'precreated_delete_test.sqlite',
    ]);

    try {
        $tenant->delete();

        // Tenancy does not own this database — DeleteTenantDatabase must early-return
        // instead of delegating to stancl's unconditional unlink()/DROP DATABASE.
        expect(file_exists($dbFile))->toBeTrue();
    } finally {
        @unlink($dbFile);
    }
});

it('still creates and deletes a normal (saas) tenant database exactly as before', function () {
    $tenant = createTenant('acme');

    expect(file_exists(database_path('zenon_tenant_acme.sqlite')))->toBeTrue()
        ->and($tenant->run(fn () => Schema::hasTable('users')))->toBeTrue();

    $tenant->delete();

    expect(file_exists(database_path('zenon_tenant_acme.sqlite')))->toBeFalse();
});
