<?php

namespace App\Foundation\Installer\Actions;

use App\Foundation\Installer\ProbeResult;

/**
 * Outcome of {@see WriteEnvironment::handle()}. $success is false when either probe
 * failed — in that case the .env file is left untouched (never partially written) and
 * the controller surfaces both probe messages so the wizard can show which database
 * rejected the given credentials.
 */
final readonly class WriteEnvironmentResult
{
    public function __construct(
        public bool $success,
        public ProbeResult $central,
        public ProbeResult $tenant,
    ) {}
}
