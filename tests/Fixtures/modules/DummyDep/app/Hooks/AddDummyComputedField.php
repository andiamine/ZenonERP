<?php

namespace Modules\DummyDep\Hooks;

use Illuminate\Contracts\Config\Repository;
use Modules\Dummy\Contracts\Hooks\DummyItemsApiResponse;

/**
 * Cross-module response filter (§6): only Dummy's Contracts namespace is imported.
 * The constructor dependency is deliberate — proves HookBus resolves filters through
 * the container, not `new`.
 */
final class AddDummyComputedField
{
    public function __construct(private readonly Repository $config) {}

    public function __invoke(DummyItemsApiResponse $payload): void
    {
        $payload->extra['computed_by'] = 'dummydep';
        $payload->extra['app_name'] = $this->config->get('app.name');
    }
}
