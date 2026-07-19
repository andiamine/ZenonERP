<?php

namespace App\Foundation\Api;

use App\Foundation\Hooks\ActionVetoedException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * The single §8 error envelope: { "error": { type, message, code, errors?, trace_id } }.
 *
 * Runs as a render callback AFTER Handler::prepareException — ModelNotFound arrives as
 * NotFoundHttpException(404), AuthorizationException as AccessDeniedHttpException(403),
 * TokenMismatch as HttpException(419) — so the status map covers them. Validation and
 * authentication exceptions arrive raw and are matched explicitly — as is
 * ActionVetoedException (§6): 422 'action_vetoed', the veto reason as the message and
 * the veto code as the envelope `code` (the only branch that populates it).
 */
final class ApiExceptionRenderer
{
    /** @var array<int, string> */
    private const array STATUS_TYPES = [
        400 => 'bad_request',
        401 => 'unauthenticated',
        403 => 'forbidden',
        404 => 'not_found',
        405 => 'method_not_allowed',
        409 => 'conflict',
        419 => 'csrf_token_mismatch',
        422 => 'validation_error',
        429 => 'too_many_requests',
    ];

    public static function render(Throwable $e, Request $request): ?JsonResponse
    {
        if ($e instanceof HttpResponseException) {
            return null; // carries its own prepared response — let the framework use it
        }

        $traceId = (string) Str::uuid();
        $status = 500;
        $type = 'server_error';
        $code = null;
        $errors = null;
        $headers = [];
        $message = config('app.debug') ? $e->getMessage() : 'Server error.';

        if ($e instanceof ValidationException) {
            [$status, $type, $message, $errors] = [$e->status, 'validation_error', $e->getMessage(), $e->errors()];
        } elseif ($e instanceof AuthenticationException) {
            [$status, $type, $message] = [401, 'unauthenticated', 'Unauthenticated.'];
        } elseif ($e instanceof AuthorizationException) { // defensive: normally pre-converted to AccessDeniedHttpException
            [$status, $type, $message] = [403, 'forbidden', $e->getMessage() !== '' ? $e->getMessage() : 'Forbidden.'];
        } elseif ($e instanceof ActionVetoedException) {
            [$status, $type, $message, $code] = [422, 'action_vetoed', $e->getMessage(), $e->vetoCode];
        } elseif ($e instanceof HttpExceptionInterface) {
            $status = $e->getStatusCode();
            $type = self::STATUS_TYPES[$status] ?? ($status >= 500 ? 'server_error' : 'http_error');
            $headers = $e->getHeaders(); // preserves Retry-After on 429, Allow on 405
            $message = $e->getMessage() !== ''
                ? $e->getMessage()
                : (Response::$statusTexts[$status] ?? 'Error');
        }

        if ($status >= 500) {
            // Correlation line only; the framework's report() pipeline logs the full trace.
            Log::error('api.unhandled_exception', [
                'trace_id' => $traceId,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }

        $error = ['type' => $type, 'message' => $message, 'code' => $code];

        if ($errors !== null) {
            $error['errors'] = $errors;
        }

        $error['trace_id'] = $traceId;

        return new JsonResponse(['error' => $error], $status, $headers);
    }
}
