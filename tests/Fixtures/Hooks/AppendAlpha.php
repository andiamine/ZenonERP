<?php

namespace Tests\Fixtures\Hooks;

final class AppendAlpha
{
    public function __invoke(OrderProbePayload $payload): void
    {
        $payload->log[] = 'alpha';
    }
}
