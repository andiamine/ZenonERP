<?php

use App\Foundation\Modules\ModuleManager;
use App\Foundation\Tenancy\Actions\CreateTenant;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', '../modules/zenon/*/tests/Feature');

/**
 * Creates tenant + domain and synchronously provisions/migrates its (file-based
 * sqlite) database — dogfoods the production CreateTenant path.
 */
function createTenant(string $subdomain, ?string $name = null): Tenant
{
    return app(CreateTenant::class)->handle($subdomain, $name);
}

function installModule(string $alias): void
{
    app(ModuleManager::class)->install($alias);
}

function enableModule(string $alias, Tenant $tenant): void
{
    app(ModuleManager::class)->enableForTenant($alias, $tenant);
}

/**
 * Standing invisibility assertion (CLAUDE.md §11): a module that is not enabled for
 * the tenant must be behaviorally invisible — its routes 404. Every future module's
 * suite reuses this.
 */
function assertModuleInvisibleFor(Tenant $tenant, string $probeUri): void
{
    test()->getJson('http://'.$tenant->getTenantKey().'.zenonerp.test'.$probeUri)->assertNotFound();
}

/**
 * Creates a user inside the tenant's DB (factory password: "password").
 *
 * @param  array<string, mixed>  $attributes
 */
function tenantUser(Tenant $tenant, array $attributes = []): User
{
    return $tenant->run(fn () => User::factory()->create($attributes));
}

/**
 * Same-origin (stateful) JSON request against a host — the Referer header is what makes
 * Sanctum's EnsureFrontendRequestsAreStateful treat it as a frontend request. Cookie
 * state is flushed first so every call is self-contained; pass the RAW ENCRYPTED session
 * cookie (from loginOn) to authenticate. CSRF needs no ceremony: PreventRequestForgery
 * bypasses entirely under unit tests.
 *
 * @param  array<string, mixed>  $data
 * @param  array<string, string>  $headers  extra request headers (e.g. X-Company-Id)
 */
function statefulJson(string $method, string $host, string $uri, array $data = [], ?string $rawSessionCookie = null, array $headers = []): TestResponse
{
    $test = test();
    $test->flushCookieState();
    $test->withHeader('Referer', "http://{$host}");
    $test->withHeaders($headers);

    if ($rawSessionCookie !== null) {
        // getJson/postJson only forward cookies when withCredentials() is set.
        $test->withCredentials()
            ->withUnencryptedCookie((string) config('session.cookie'), $rawSessionCookie);
    }

    return $test->{$method.'Json'}("http://{$host}{$uri}", $data);
}

/**
 * Logs in on a host and returns [response, raw encrypted session cookie|null].
 *
 * @return array{0: TestResponse, 1: string|null}
 */
function loginOn(string $host, string $email, string $password = 'password'): array
{
    $response = statefulJson('post', $host, '/api/v1/auth/login', ['email' => $email, 'password' => $password]);
    $cookie = $response->getCookie((string) config('session.cookie'), decrypt: false);

    return [$response, $cookie?->getValue()];
}

/** Asserts the §8 error envelope shape: { error: { type, message, code, trace_id } }. */
function assertErrorEnvelope(TestResponse $response, int $status, string $type): TestResponse
{
    return $response->assertStatus($status)
        ->assertJsonPath('error.type', $type)
        ->assertJsonStructure(['error' => ['type', 'message', 'code', 'trace_id']]);
}
