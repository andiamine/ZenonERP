<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

// Phase 3 acceptance (CLAUDE.md §12): §8 envelope shape asserted for 401/403/404/422
// (plus the statuses the renderer maps beyond the §8 core set).

beforeEach(function () {
    Route::middleware('api')->prefix('api')->group(function (): void {
        Route::get('/v1/_test/forbidden', fn () => abort(403));
        Route::get('/v1/_test/conflict', fn () => abort(409, 'Resource state conflict.'));
        Route::get('/v1/_test/boom', fn () => throw new RuntimeException('kaboom'));
        Route::get('/v1/_test/limited', fn () => response()->json(['data' => 'ok']))
            ->middleware('throttle:2,1');
    });
});

it('renders 401 unauthenticated', function () {
    $response = statefulJson('get', 'app.zenonerp.test', '/api/v1/auth/me');

    assertErrorEnvelope($response, 401, 'unauthenticated')
        ->assertJsonMissingPath('error.errors'); // errors key is 422-only
});

it('renders 403 forbidden', function () {
    assertErrorEnvelope($this->getJson('http://app.zenonerp.test/api/v1/_test/forbidden'), 403, 'forbidden');
});

it('renders 404 for unknown api routes', function () {
    assertErrorEnvelope($this->getJson('http://app.zenonerp.test/api/v1/nope'), 404, 'not_found');
});

it('renders 404 for unknown tenant subdomains', function () {
    assertErrorEnvelope($this->getJson('http://ghost.zenonerp.test/api/v1/ping'), 404, 'not_found');
});

it('renders 405 method_not_allowed', function () {
    assertErrorEnvelope($this->postJson('http://app.zenonerp.test/api/v1/_test/forbidden'), 405, 'method_not_allowed');
});

it('renders 409 conflict', function () {
    assertErrorEnvelope($this->getJson('http://app.zenonerp.test/api/v1/_test/conflict'), 409, 'conflict')
        ->assertJsonPath('error.message', 'Resource state conflict.');
});

it('renders 422 validation_error with the errors key', function () {
    $response = statefulJson('post', 'app.zenonerp.test', '/api/v1/auth/login', ['email' => 'not-an-email']);

    assertErrorEnvelope($response, 422, 'validation_error')
        ->assertJsonStructure(['error' => ['errors' => ['email', 'password']]]);
});

it('renders 429 too_many_requests preserving Retry-After', function () {
    $this->getJson('http://app.zenonerp.test/api/v1/_test/limited')->assertOk();
    $this->getJson('http://app.zenonerp.test/api/v1/_test/limited')->assertOk();

    $response = $this->getJson('http://app.zenonerp.test/api/v1/_test/limited');

    assertErrorEnvelope($response, 429, 'too_many_requests');
    expect($response->headers->get('Retry-After'))->not->toBeNull();
});

it('renders 500 server_error with the message hidden when debug is off', function () {
    config(['app.debug' => false]);

    $response = $this->getJson('http://app.zenonerp.test/api/v1/_test/boom');

    assertErrorEnvelope($response, 500, 'server_error')
        ->assertJsonPath('error.message', 'Server error.');

    expect(Str::isUuid($response->json('error.trace_id')))->toBeTrue();
});
