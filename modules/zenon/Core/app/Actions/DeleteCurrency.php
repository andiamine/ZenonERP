<?php

namespace Modules\Core\Actions;

use Modules\Core\Models\Company;
use Modules\Core\Models\Currency;

final class DeleteCurrency
{
    public function handle(Currency $currency): void
    {
        abort_if(
            Company::query()->where('currency_code', $currency->code)->exists(),
            409,
            'This currency is referenced by a company.',
        );

        $currency->delete();
    }
}
