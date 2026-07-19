<?php

namespace Modules\Demo\Hooks;

use Modules\Core\Contracts\Hooks\CompanyDeleting;

/**
 * Cross-module veto filter (§6): protects companies with code LOCKED from deletion —
 * the extension-proof twin of DummyDep's VetoWhenNameForbidden. Registered with
 * priority 10 to run early.
 */
final class VetoProtectedCompanyDeletion
{
    public function __invoke(CompanyDeleting $payload): void
    {
        if ($payload->code === 'LOCKED') {
            $payload->veto('Companies with code LOCKED are protected by the Demo addon.', 'demo.company_locked');
        }
    }
}
