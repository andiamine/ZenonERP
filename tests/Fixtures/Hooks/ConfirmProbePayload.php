<?php

namespace Tests\Fixtures\Hooks;

use App\Foundation\Hooks\Concerns\Vetoable;

/** Vetoable payload probe: filters log execution; VetoIfForbidden aborts on demand. */
final class ConfirmProbePayload
{
    use Vetoable;

    /** @var list<string> */
    public array $log = [];

    public function __construct(public readonly string $name) {}
}
