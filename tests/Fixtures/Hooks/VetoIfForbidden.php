<?php

namespace Tests\Fixtures\Hooks;

final class VetoIfForbidden
{
    public function __invoke(ConfirmProbePayload $payload): void
    {
        if ($payload->name === 'forbidden') {
            $payload->veto('Name is forbidden.', 'probe.forbidden');
        }

        $payload->log[] = 'veto-check';
    }
}
