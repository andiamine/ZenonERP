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
        $companyId = $data['company_id'] ?? null;

        // Guard the (currency_id, company_id, valid_from) uniqueness invariant here so a
        // duplicate returns the §8 409 envelope instead of letting the DB unique index throw
        // a raw QueryException → 500. Also covers the tenant-level (company_id NULL) case,
        // which the DB index can't dedupe (MySQL/MariaDB allow duplicate NULLs — see the
        // currency_rates migration), keeping the two rate layers consistent.
        abort_if(
            CurrencyRate::query()
                ->where('currency_id', $currency->getKey())
                ->where('company_id', $companyId)
                ->whereDate('valid_from', $data['valid_from'])
                ->exists(),
            409,
            'A rate for this currency, company and date already exists.',
        );

        return CurrencyRate::query()->create([
            'currency_id' => $currency->getKey(),
            'company_id' => $companyId,
            'rate' => $data['rate'],
            'valid_from' => $data['valid_from'],
        ]);
    }
}
