<?php

namespace Modules\Dummy\Contracts\Hooks;

/**
 * Filter payload (§6): extension modules append computed fields to the dummy items
 * API response. Fixture twin of the M2 SalesOrderApiResponse contract.
 */
final class DummyItemsApiResponse
{
    /** @param array<string, mixed> $extra */
    public function __construct(public array $extra = []) {}
}
