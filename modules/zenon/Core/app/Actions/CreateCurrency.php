<?php

namespace Modules\Core\Actions;

use Modules\Core\Models\Currency;

final class CreateCurrency
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): Currency
    {
        $currency = Currency::query()->create($data);

        // See CreateCompany's docblock: `decimal_places`/`active` are optional on the
        // request, so an omitted value only gets the schema DEFAULT at the DB layer —
        // refresh so the returned model (and CurrencyResource) reflects it, not null.
        return $currency->refresh();
    }
}
