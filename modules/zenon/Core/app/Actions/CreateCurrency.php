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
        return Currency::query()->create($data);
    }
}
