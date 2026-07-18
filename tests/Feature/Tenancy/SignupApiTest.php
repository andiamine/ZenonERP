<?php

use App\Models\CentralUser;
use App\Models\Tenant;
use Illuminate\Support\Facades\Schema;

// Signup is operator-gated (decided Phase 3): every request below authenticates
// with the central guard; the unauthenticated case asserts the 401 envelope.
function actingAsOperator(): CentralUser
{
    $operator = CentralUser::factory()->create();
    test()->actingAs($operator, 'central');

    return $operator;
}

it('provisions a tenant via the central signup endpoint', function () {
    actingAsOperator();

    $response = $this->postJson('http://app.zenonerp.test/api/v1/signup', [
        'subdomain' => 'gamma',
        'name' => 'Gamma',
    ]);

    $response->assertCreated()
        ->assertJsonStructure(['data' => ['id', 'name', 'domain', 'created_at']])
        ->assertJsonPath('data.id', 'gamma')
        ->assertJsonPath('data.name', 'Gamma')
        ->assertJsonPath('data.domain', 'gamma');

    $tenant = Tenant::find('gamma');

    expect($tenant)->not->toBeNull()
        ->and(file_exists(database_path('zenon_tenant_gamma.sqlite')))->toBeTrue()
        ->and($tenant->run(fn () => Schema::hasTable('users')))->toBeTrue();
});

it('rejects unauthenticated signup with the 401 envelope', function () {
    $this->postJson('http://app.zenonerp.test/api/v1/signup', ['subdomain' => 'gamma'])
        ->assertUnauthorized()
        ->assertJsonPath('error.type', 'unauthenticated')
        ->assertJsonStructure(['error' => ['type', 'message', 'code', 'trace_id']]);

    expect(Tenant::count())->toBe(0);
});

it('rejects reserved subdomains', function (string $reserved) {
    actingAsOperator();

    $this->postJson('http://app.zenonerp.test/api/v1/signup', ['subdomain' => $reserved])
        ->assertUnprocessable()
        ->assertJsonPath('error.type', 'validation_error')
        ->assertJsonStructure(['error' => ['type', 'message', 'code', 'errors' => ['subdomain'], 'trace_id']]);

    expect(Tenant::count())->toBe(0);
})->with(['app', 'www']);

it('rejects malformed subdomains', function (string $subdomain) {
    actingAsOperator();

    $this->postJson('http://app.zenonerp.test/api/v1/signup', ['subdomain' => $subdomain])
        ->assertUnprocessable()
        ->assertJsonPath('error.type', 'validation_error')
        ->assertJsonStructure(['error' => ['type', 'message', 'code', 'errors' => ['subdomain'], 'trace_id']]);

    expect(Tenant::count())->toBe(0);
})->with(['Acme', '-x', 'a', 'bad_name']);

it('rejects duplicate subdomains', function () {
    createTenant('acme');
    actingAsOperator();

    $this->postJson('http://app.zenonerp.test/api/v1/signup', ['subdomain' => 'acme'])
        ->assertUnprocessable()
        ->assertJsonPath('error.type', 'validation_error')
        ->assertJsonStructure(['error' => ['type', 'message', 'code', 'errors' => ['subdomain'], 'trace_id']]);

    expect(Tenant::count())->toBe(1);
});

it('is not reachable on tenant subdomains', function () {
    createTenant('acme');

    $this->postJson('http://acme.zenonerp.test/api/v1/signup', ['subdomain' => 'delta'])
        ->assertNotFound();
});
