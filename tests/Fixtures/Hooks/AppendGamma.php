<?php

namespace Tests\Fixtures\Hooks;

final class AppendGamma
{
    public function __invoke(OrderProbePayload $payload): void
    {
        $payload->log[] = 'gamma';
    }
}
