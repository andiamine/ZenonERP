<?php

namespace Modules\Core\Actions;

use Modules\Core\Models\Currency;

final class UpdateCurrency
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Currency $currency, array $data): Currency
    {
        $currency->update($data);

        return $currency;
    }
}
