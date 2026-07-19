<?php

namespace Modules\Demo\Hooks;

use Illuminate\Contracts\Config\Repository;
use Modules\Core\Contracts\Hooks\CompanyApiResponse;

/**
 * Cross-module response filter (§6): only Core's Contracts namespace is imported.
 * The constructor dependency is deliberate — proves HookBus resolves filters through
 * the container, not `new` (fixture twin: DummyDep's AddDummyComputedField). The
 * DI-proof extra key is `platform`, read from config('zenon.platform_version') —
 * kept deterministic (unlike e.g. app.env, which varies by process) so the test
 * suite can assert an exact value.
 */
final class AddCompanyInsights
{
    public function __construct(private readonly Repository $config) {}

    public function __invoke(CompanyApiResponse $payload): void
    {
        foreach ($payload->companies as $row) {
            $payload->extra[$row['id']] = [
                'insight' => "Company {$row['code']} looks healthy",
                'name_length' => strlen($row['name']),
                'computed_by' => 'acme/demo',
                'platform' => (string) $this->config->get('zenon.platform_version'),
            ];
        }
    }
}
