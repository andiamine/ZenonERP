<?php

use App\Foundation\Modules\Models\TenantModule;
use App\Foundation\Tenancy\Actions\CreateStandaloneTenant;
use App\Models\Tenant;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 8 Task 4: the installer wizard's provisioning action for the single standalone
 * tenant. Mirrors the pre-created-database pattern proven in PreCreatedDatabaseTest —
 * a real sqlite file stands in for a Plesk/cPanel pre-created DB, and
 * `tenancy_db_connection` is passed as null so DatabaseConfig falls back to the
 * central sqlite template (matches how PreCreatedDatabaseTest exercises the same seam).
 */
function standaloneDbFile(): string
{
    return database_path('phase8_standalone_test.sqlite');
}

beforeEach(function () {
    installModule('core');
    installModule('sequence');
    installModule('audit');

    config(['app.url' => 'http://erp.example.test']);

    file_put_contents(standaloneDbFile(), '');
});

afterEach(function () {
    @unlink(standaloneDbFile());
});

it('provisions the default tenant fresh: migrates the DB, enables core+sequence+audit, and writes the APP_URL-host domain row', function () {
    $tenant = app(CreateStandaloneTenant::class)->handle('Acme Co', 'phase8_standalone_test.sqlite', null);

    expect($tenant->id)->toBe('default')
        ->and($tenant->name)->toBe('Acme Co');

    expect($tenant->domains()->where('domain', 'erp.example.test')->exists())->toBeTrue();

    // MigrateDatabase ran against the pre-created file → platform base tenant schema present.
    expect($tenant->run(fn () => Schema::hasTable('users')))->toBeTrue();

    // ProvisionTenantModules ran → core + default_modules (sequence, audit) enabled.
    expect(TenantModule::query()
        ->where('tenant_id', 'default')
        ->where('enabled', true)
        ->pluck('module')->sort()->values()->all())
        ->toBe(['audit', 'core', 'sequence']);
});

it('resumes and completes provisioning when the tenant row exists but the pipeline never ran (mid-failure simulation)', function () {
    Tenant::withoutEvents(function () {
        Tenant::create([
            'id' => 'default',
            'name' => 'Acme Co',
            'tenancy_db_name' => 'phase8_standalone_test.sqlite',
            'tenancy_create_database' => false,
        ]);
    });

    // Sanity: withoutEvents really suppressed the TenantCreated pipeline.
    expect(TenantModule::query()->where('tenant_id', 'default')->exists())->toBeFalse();

    $tenant = app(CreateStandaloneTenant::class)->handle('Acme Co', 'phase8_standalone_test.sqlite', null);

    expect($tenant->id)->toBe('default');

    expect($tenant->run(fn () => Schema::hasTable('users')))->toBeTrue();

    expect(TenantModule::query()
        ->where('tenant_id', 'default')
        ->where('enabled', true)
        ->pluck('module')->sort()->values()->all())
        ->toBe(['audit', 'core', 'sequence']);

    expect($tenant->domains()->where('domain', 'erp.example.test')->count())->toBe(1);
});

it('is a no-op on a second call after full success: same tenant, no duplicate domain rows, no errors', function () {
    $action = app(CreateStandaloneTenant::class);

    $first = $action->handle('Acme Co', 'phase8_standalone_test.sqlite', null);
    $second = $action->handle('Acme Co', 'phase8_standalone_test.sqlite', null);

    expect($second->id)->toBe($first->id)
        ->and($second->id)->toBe('default');

    expect($second->domains()->count())->toBe(1);

    expect(TenantModule::query()
        ->where('tenant_id', 'default')
        ->where('enabled', true)
        ->pluck('module')->sort()->values()->all())
        ->toBe(['audit', 'core', 'sequence']);
});
