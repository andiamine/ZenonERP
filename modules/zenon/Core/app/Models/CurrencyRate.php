<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $currency_id
 * @property int|null $company_id
 * @property string $rate
 * @property Carbon $valid_from
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class CurrencyRate extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['currency_id', 'company_id', 'rate', 'valid_from'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rate' => 'decimal:10',
            'valid_from' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Currency, $this>
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }
}
