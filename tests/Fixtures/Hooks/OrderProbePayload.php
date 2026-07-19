<?php

namespace Tests\Fixtures\Hooks;

/** Hook payload probe: filters append markers so tests can assert execution order. */
final class OrderProbePayload
{
    /** @var list<string> */
    public array $log = [];
}
