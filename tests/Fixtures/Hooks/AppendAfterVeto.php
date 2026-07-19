<?php

namespace Tests\Fixtures\Hooks;

final class AppendAfterVeto
{
    public function __invoke(ConfirmProbePayload $payload): void
    {
        $payload->log[] = 'after';
    }
}
