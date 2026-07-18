<?php

namespace Modules\Core\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\Models\CurrencyRate;

/** @mixin CurrencyRate */
class CurrencyRateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'currency_id' => $this->currency_id,
            'company_id' => $this->company_id,
            'rate' => $this->rate,
            'valid_from' => $this->valid_from,
            'created_at' => $this->created_at,
        ];
    }
}
