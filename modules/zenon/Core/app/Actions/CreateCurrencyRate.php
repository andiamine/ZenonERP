<?php

namespace Modules\Core\Actions;

use Modules\Core\Models\Currency;
use Modules\Core\Models\CurrencyRate;

final class CreateCurrencyRate
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Currency $currency, array $data): CurrencyRate
    {
        return CurrencyRate::query()->create([
            'currency_id' => $currency->getKey(),
            'company_id' => $data['company_id'] ?? null,
            'rate' => $data['rate'],
            'valid_from' => $data['valid_from'],
        ]);
    }
}
