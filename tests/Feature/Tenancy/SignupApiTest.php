<?php

use App\Models\Tenant;
use Illuminate\Support\Facades\Schema;

it('provisions a tenant via the central signup endpoint', function () {
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

it('rejects reserved subdomains', function (string $reserved) {
    $this->postJson('http://app.zenonerp.test/api/v1/signup', ['subdomain' => $reserved])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('subdomain');

    expect(Tenant::count())->toBe(0);
})->with(['app', 'www']);

it('rejects malformed subdomains', function (string $subdomain) {
    $this->postJson('http://app.zenonerp.test/api/v1/signup', ['subdomain' => $subdomain])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('subdomain');

    expect(Tenant::count())->toBe(0);
})->with(['Acme', '-x', 'a', 'bad_name']);

it('rejects duplicate subdomains', function () {
    createTenant('acme');

    $this->postJson('http://app.zenonerp.test/api/v1/signup', ['subdomain' => 'acme'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('subdomain');

    expect(Tenant::count())->toBe(1);
});

it('is not reachable on tenant subdomains', function () {
    createTenant('acme');

    $this->postJson('http://acme.zenonerp.test/api/v1/signup', ['subdomain' => 'delta'])
        ->assertNotFound();
});
