<?php

namespace Tests\Fixtures\Hooks;

final class AppendBeta
{
    public function __invoke(OrderProbePayload $payload): void
    {
        $payload->log[] = 'beta';
    }
}
