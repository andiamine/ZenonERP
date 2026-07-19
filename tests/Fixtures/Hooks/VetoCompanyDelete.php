<?php

namespace Tests\Fixtures\Hooks;

use Modules\Core\Contracts\Hooks\CompanyDeleting;

/** Veto filter probe: unconditionally aborts the company delete in flight. */
final class VetoCompanyDelete
{
    public function __invoke(CompanyDeleting $payload): void
    {
        $payload->veto('Company delete blocked for testing.', 'test.veto_code');
    }
}
