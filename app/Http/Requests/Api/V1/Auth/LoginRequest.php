<?php

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Shared by the tenant (`web`) and central (`central`) login endpoints — the guard
 * decides which users table (and which DB) the credentials are checked against.
 */
class LoginRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @throws ValidationException
     */
    public function authenticate(string $guardName): void
    {
        $this->ensureIsNotRateLimited($guardName);

        if (! Auth::guard($guardName)->attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey($guardName));

            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        RateLimiter::clear($this->throttleKey($guardName));
    }

    /**
     * @throws ValidationException
     */
    private function ensureIsNotRateLimited(string $guardName): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey($guardName), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey($guardName));

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    private function throttleKey(string $guardName): string
    {
        return Str::transliterate($guardName.'|'.Str::lower($this->string('email')->value()).'|'.$this->ip());
    }
}
