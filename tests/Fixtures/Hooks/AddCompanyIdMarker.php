<?php

namespace Tests\Fixtures\Hooks;

use Modules\Core\Contracts\Hooks\CompanyApiResponse;

/** Response filter probe: marks every company in the snapshot, keyed by its id. */
final class AddCompanyIdMarker
{
    public function __invoke(CompanyApiResponse $payload): void
    {
        foreach ($payload->companies as $company) {
            $payload->extra[$company['id']] = ['marked' => true];
        }
    }
}
