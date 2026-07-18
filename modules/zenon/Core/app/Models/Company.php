<?php

namespace Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Core\Database\Factories\CompanyFactory;

/**
 * Tenant company (CLAUDE.md §9.1/§9.3). Deliberately NOT company-scoped — this IS the
 * companies table that BelongsToCompany/CompanyScope resolve against; scoping it
 * against the currently active company would be circular.
 *
 * @property int $id
 * @property string $name
 * @property string $code
 * @property string|null $legal_name
 * @property string $currency_code
 * @property string|null $country_code
 * @property string|null $timezone
 * @property bool $is_default
 * @property bool $active
 */
class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name', 'code', 'legal_name', 'currency_code', 'country_code', 'timezone', 'is_default', 'active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'active' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'company_user');
    }

    protected static function newFactory(): CompanyFactory
    {
        return CompanyFactory::new();
    }
}
