<?php

namespace App\Foundation\Installer;

/**
 * Outcome of a {@see DatabaseConnectionProbe::probe()} call. $message carries the raw
 * PDO/driver exception message on failure — safe to surface to the wizard UI verbatim
 * (it's the admin installing their own app, not an untrusted end user).
 */
final readonly class ProbeResult
{
    private function __construct(
        public bool $success,
        public ?string $message,
    ) {}

    public static function success(): self
    {
        return new self(true, null);
    }

    public static function failure(string $message): self
    {
        return new self(false, $message);
    }
}
