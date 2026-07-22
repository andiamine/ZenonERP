<?php

use App\Foundation\Installer\InstallerState;
use App\Foundation\Modules\Models\TenantModule;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Modules\Core\Models\Company;

/**
 * Phase 8 Task 6: the full standalone installer wizard walk on sqlite — requirements
 * -> database -> migrate -> tenant -> admin -> finalize, then resume safety.
 *
 * Central-DB config-seaming (load-bearing, read before touching this file): every
 * installer step is designed to run WITHOUT reloading config in-process (Actions\
 * WriteEnvironment never re-reads its own .env write — production picks the new .env up
 * on the NEXT request's fresh boot). A single Pest test method has no such "next
 * request" — everything runs inside one already-booted $app. So this test manually
 * seeds the config a fresh boot would have derived from the just-written .env, exactly
 * where the brief calls this out: `database.default` / `tenancy.database.central_connection`
 * are pointed at a dedicated 'installer_test_central' connection (a physical temp sqlite
 * file) in beforeEach — NOT the phpunit.xml-provided 'sqlite' connection, because that
 * name is the one Pest's global RefreshDatabase binding (tests/Pest.php `.in('Feature', ...)`)
 * already migrates and wraps in a transaction; reusing it here would fight that wrapper.
 * `database.connections.standalone` (the template CreateStandaloneTenant's controller
 * call always passes 'standalone' for, matching production) is seeded the same way,
 * pointed at a second physical temp sqlite file, right before the tenant step.
 *
 * dbName source-of-truth choice (brief offered two options): the tenant step reads
 * TENANT_DB_DATABASE back out of the just-written .env (via EnvWriter::read()) rather
 * than re-collecting it in the tenant-step POST body. Justification: TENANT_DB_DATABASE
 * is already the single value the database step successfully PROBED — asking for it
 * again risks the admin typing something different the second time (a silent mismatch
 * between "what was proven reachable" and "what the tenant actually uses"), and the
 * tenant step's own payload shrinks to just the company's display name.
 */
function installerFlowPost(string $uri, array $data = []): TestResponse
{
    return test()->withHeaders(['Origin' => 'http://erp.example.test'])
        ->postJson('http://erp.example.test'.$uri, $data);
}

function installerFlowGet(string $uri): TestResponse
{
    return test()->getJson('http://erp.example.test'.$uri);
}

beforeEach(function () {
    $this->lockPath = storage_path('framework/testing/installer-flow-'.uniqid('', true).'.lock');
    $this->envPath = storage_path('framework/testing/installer-flow-'.uniqid('', true).'.env');
    $this->centralDbFile = database_path('phase8_installer_central_test.sqlite');
    $this->tenantDbFile = database_path('phase8_installer_tenant_test.sqlite');

    file_put_contents($this->centralDbFile, '');
    file_put_contents($this->tenantDbFile, '');

    config([
        'zenon.mode' => 'standalone',
        'zenon.installer.lock_path' => $this->lockPath,
        'zenon.installer.env_path' => $this->envPath,
        'app.url' => 'http://erp.example.test',

        // The "fresh boot picked up the new .env" simulation — see the file docblock.
        'database.connections.installer_test_central' => [
            'driver' => 'sqlite',
            'database' => $this->centralDbFile,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ],
        'database.default' => 'installer_test_central',
        'tenancy.database.central_connection' => 'installer_test_central',

        // Template connection CreateStandaloneTenant's controller call names literally
        // ('standalone', matching production) — 'database' is a placeholder,
        // SQLiteDatabaseManager::makeConnectionConfig() overwrites it with
        // database_path($dbName) from the tenant's own tenancy_db_name internal.
        'database.connections.standalone' => [
            'driver' => 'sqlite',
            'database' => '',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ],
    ]);
});

afterEach(function () {
    // Load-bearing, not cosmetic: Illuminate\Foundation\Testing\RefreshDatabase schedules
    // its rollback/disconnect via a `beforeApplicationDestroyed` callback that re-evaluates
    // `connectionsToTransact()` (= [config('database.default')]) fresh AT TEARDOWN TIME,
    // not from whatever it captured when the transaction was opened at test start. Leaving
    // `database.default` pointed at 'installer_test_central' past this point means that
    // callback rolls back/disconnects the WRONG connection — the real shared 'sqlite'
    // :memory: connection (which Pest's global RefreshDatabase binding, tests/Pest.php,
    // opened a transaction on for THIS test too) never gets rolled back, corrupting it for
    // every subsequent test in the process. Restoring the defaults here — before Laravel's
    // own tearDown() runs — was root-caused live: without it, this file alone cascades into
    // ~175 unrelated failures elsewhere in the suite.
    config([
        'database.default' => 'sqlite',
        'tenancy.database.central_connection' => 'sqlite',
    ]);

    File::delete($this->lockPath);
    File::delete($this->envPath);
    @unlink($this->centralDbFile);
    @unlink($this->tenantDbFile);
});

it('walks the full install wizard end-to-end on sqlite, then resumes safely', function () {
    try {
        installerFlowWalk();
    } finally {
        // Belt-and-suspenders alongside the afterEach() restore above: this runs
        // synchronously at the end of the test's OWN call stack (success or failure),
        // strictly before PHPUnit's tearDown() phase even begins — the surest possible
        // point to undo the config.default/central_connection swap before RefreshDatabase's
        // beforeApplicationDestroyed callback re-evaluates connectionsToTransact().
        config([
            'database.default' => 'sqlite',
            'tenancy.database.central_connection' => 'sqlite',
        ]);
    }
});

function installerFlowWalk(): void
{
    // --- requirements -------------------------------------------------------------
    $requirements = installerFlowGet('/install/api/requirements')->assertOk()->json('data');

    expect($requirements['items'])->toBeArray()->not->toBeEmpty();
    expect(array_column($requirements['items'], 'key'))->toContain('php_version', 'ext_pdo_mysql');

    // --- status: nothing done yet ---------------------------------------------------
    installerFlowGet('/install/api/status')->assertOk()->assertJson(['data' => ['steps' => [
        'database' => false, 'migrate' => false, 'tenant' => false, 'admin' => false, 'finalize' => false,
    ]]]);

    // --- database --------------------------------------------------------------------
    $databasePayload = [
        'app_name' => 'Acme Co',
        'app_url' => 'http://erp.example.test',
        'driver' => 'sqlite',
        'central' => ['database' => 'phase8_installer_central_test.sqlite'],
        'tenant' => ['database' => 'phase8_installer_tenant_test.sqlite'],
    ];

    installerFlowPost('/install/api/database', $databasePayload)
        ->assertOk()
        ->assertJson(['data' => ['written' => true]]);

    expect(file_exists(test()->envPath))->toBeTrue();
    $envContent = (string) file_get_contents(test()->envPath);

    expect($envContent)->toMatch('/^APP_KEY=base64:.+$/m')
        ->toMatch('/^TENANCY_CENTRAL_DOMAINS=$/m') // literal blank line — CentralDomains::parse() contract
        ->toContain('APP_NAME="Acme Co"') // posted app_name contains a space -> EnvWriter quotes it
        ->toContain('APP_URL=http://erp.example.test')
        ->toContain('APP_ENV=production')
        ->toContain('APP_DEBUG=false')
        ->toContain('ZENON_MODE=standalone')
        ->toContain('SANCTUM_STATEFUL_DOMAINS=erp.example.test')
        ->toContain('SESSION_DOMAIN=null')
        ->toContain('DB_CONNECTION=sqlite')
        ->toContain('DB_DATABASE=phase8_installer_central_test.sqlite')
        ->toContain('TENANT_DB_DATABASE=phase8_installer_tenant_test.sqlite')
        ->toContain('SESSION_DRIVER=database')
        ->toContain('CACHE_STORE=database')
        ->toContain('QUEUE_CONNECTION=database')
        ->toContain('DB_CACHE_CONNECTION=mysql')
        ->toContain('DB_QUEUE_CONNECTION=mysql');

    installerFlowGet('/install/api/status')->assertJson(['data' => ['steps' => [
        'database' => true, 'migrate' => false, 'tenant' => false, 'admin' => false, 'finalize' => false,
    ]]]);

    // --- migrate -----------------------------------------------------------------
    expect(Schema::hasTable('tenants'))->toBeFalse();

    installerFlowPost('/install/api/migrate')->assertOk()->assertJson(['data' => ['migrated' => true]]);

    expect(Schema::hasTable('tenants'))->toBeTrue()
        ->and(Schema::hasTable('tenant_modules'))->toBeTrue();

    // A production release zip ships with first-party modules already installed by the
    // deploy pipeline (zenon:module:install) — the wizard never installs modules itself,
    // only enables already-installed ones for the tenant it provisions. Replicate that
    // pre-existing state now that the central schema exists to hold it.
    installModule('core');
    installModule('sequence');
    installModule('audit');

    installerFlowGet('/install/api/status')->assertJson(['data' => ['steps' => [
        'database' => true, 'migrate' => true, 'tenant' => false, 'admin' => false, 'finalize' => false,
    ]]]);

    // --- tenant ------------------------------------------------------------------
    installerFlowPost('/install/api/tenant', ['name' => 'Acme Co'])
        ->assertOk()
        ->assertJson(['data' => ['tenant_id' => 'default']]);

    $tenant = Tenant::find('default');
    expect($tenant)->not->toBeNull()
        ->and($tenant->name)->toBe('Acme Co')
        ->and($tenant->domains()->where('domain', 'erp.example.test')->exists())->toBeTrue()
        ->and($tenant->run(fn () => Schema::hasTable('users')))->toBeTrue();

    expect(TenantModule::query()->where('tenant_id', 'default')->where('enabled', true)->pluck('module')->sort()->values()->all())
        ->toBe(['audit', 'core', 'sequence']);

    installerFlowGet('/install/api/status')->assertJson(['data' => ['steps' => [
        'database' => true, 'migrate' => true, 'tenant' => true, 'admin' => false, 'finalize' => false,
    ]]]);

    // --- admin -------------------------------------------------------------------
    $adminPayload = ['name' => 'Admin User', 'email' => 'admin@erp.example.test', 'password' => 'password123'];

    installerFlowPost('/install/api/admin', $adminPayload)
        ->assertOk()
        ->assertJson(['data' => ['created' => true]]);

    $tenant->run(function () {
        $admin = User::query()->where('email', 'admin@erp.example.test')->first();

        expect($admin)->not->toBeNull()
            ->and($admin->hasRole('admin'))->toBeTrue();

        $mainCompany = Company::query()->where('code', 'MAIN')->first();

        expect($mainCompany)->not->toBeNull()
            ->and($mainCompany->users()->where('users.id', $admin->id)->exists())->toBeTrue();
    });

    // --- status: everything except finalize -----------------------------------------
    installerFlowGet('/install/api/status')->assertJson(['data' => ['steps' => [
        'database' => true, 'migrate' => true, 'tenant' => true, 'admin' => true, 'finalize' => false,
    ]]]);

    // --- resume safety: re-POST database/tenant/admin, no dupes, no errors ----------
    installerFlowPost('/install/api/database', $databasePayload)->assertOk();
    installerFlowPost('/install/api/tenant', ['name' => 'Acme Co'])->assertOk();
    installerFlowPost('/install/api/admin', $adminPayload)->assertOk();

    expect(Tenant::query()->count())->toBe(1)
        ->and($tenant->domains()->count())->toBe(1);

    expect(TenantModule::query()->where('tenant_id', 'default')->where('enabled', true)->count())->toBe(3);

    $tenant->run(function () {
        expect(User::query()->where('email', 'admin@erp.example.test')->count())->toBe(1);
    });

    // --- finalize ------------------------------------------------------------------
    installerFlowPost('/install/api/finalize')->assertOk()->assertJson(['data' => ['redirect' => '/']]);

    expect(app(InstallerState::class)->isInstalled())->toBeTrue();

    // Once locked, EnsureInstallerAvailable 404s the whole /install surface — including
    // the GET stub Task 5 shipped and this very status endpoint.
    test()->get('http://erp.example.test/install')->assertNotFound();
    installerFlowGet('/install/api/status')->assertNotFound();
}
