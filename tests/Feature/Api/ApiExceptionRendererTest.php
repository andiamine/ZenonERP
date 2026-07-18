<?php

use App\Foundation\Api\ApiExceptionRenderer;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

// Direct renderer coverage for branches without a feature-route surface yet
// (409 has no producing endpoint until later phases; 419 is CSRF, which
// PreventRequestForgery bypasses entirely under unit tests).

function renderApiException(Throwable $e): mixed
{
    return ApiExceptionRenderer::render($e, Request::create('/api/v1/x'));
}

it('maps ConflictHttpException to the conflict envelope', function () {
    $response = renderApiException(new ConflictHttpException('Order already confirmed.'));

    expect($response->getStatusCode())->toBe(409)
        ->and($response->getData(true)['error']['type'])->toBe('conflict')
        ->and($response->getData(true)['error']['message'])->toBe('Order already confirmed.');
});

it('maps 419 to csrf_token_mismatch', function () {
    $response = renderApiException(new HttpException(419, 'CSRF token mismatch.'));

    expect($response->getStatusCode())->toBe(419)
        ->and($response->getData(true)['error']['type'])->toBe('csrf_token_mismatch');
});

it('maps unknown exceptions to server_error with a uuid trace id and hidden message', function () {
    config(['app.debug' => false]);

    $response = renderApiException(new RuntimeException('secret internals'));
    $error = $response->getData(true)['error'];

    expect($response->getStatusCode())->toBe(500)
        ->and($error['type'])->toBe('server_error')
        ->and($error['message'])->toBe('Server error.')
        ->and(Str::isUuid($error['trace_id']))->toBeTrue();
});

it('exposes the exception message on 500 when debug is on', function () {
    config(['app.debug' => true]);

    expect(renderApiException(new RuntimeException('kaboom'))->getData(true)['error']['message'])->toBe('kaboom');
});

it('maps ValidationException with the errors key', function () {
    $response = renderApiException(ValidationException::withMessages(['email' => ['Invalid.']]));
    $error = $response->getData(true)['error'];

    expect($response->getStatusCode())->toBe(422)
        ->and($error['type'])->toBe('validation_error')
        ->and($error['errors'])->toBe(['email' => ['Invalid.']]);
});

it('maps AuthenticationException to 401', function () {
    $response = renderApiException(new AuthenticationException);

    expect($response->getStatusCode())->toBe(401)
        ->and($response->getData(true)['error']['type'])->toBe('unauthenticated');
});

it('passes HttpResponseException through untouched', function () {
    expect(renderApiException(new HttpResponseException(response('prepared'))))->toBeNull();
});
