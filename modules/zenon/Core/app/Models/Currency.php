<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Database\Factories\CurrencyFactory;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $symbol
 * @property int $decimal_places
 * @property bool $active
 */
class Currency extends Model
{
    /** @use HasFactory<CurrencyFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = ['code', 'name', 'symbol', 'decimal_places', 'active'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'decimal_places' => 'integer',
            'active' => 'boolean',
        ];
    }

    protected static function newFactory(): CurrencyFactory
    {
        return CurrencyFactory::new();
    }
}
