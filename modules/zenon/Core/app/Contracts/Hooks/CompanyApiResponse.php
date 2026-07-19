<?php

namespace Modules\Core\Contracts\Hooks;

/**
 * Filter payload (§6): extension modules append computed fields to the companies API
 * response. Production analogue of the M2 SalesOrderApiResponse contract (fixture twin:
 * DummyItemsApiResponse).
 */
final class CompanyApiResponse
{
    /**
     * @param  list<array{id: int, name: string, code: string}>  $companies  read-only snapshot of the companies in the response
     * @param  array<int, mixed>  $extra  computed fields keyed by company id, mutated by filters
     */
    public function __construct(public readonly array $companies, public array $extra = []) {}
}
