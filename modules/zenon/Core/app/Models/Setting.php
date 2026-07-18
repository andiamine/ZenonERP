<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Deliberately NOT scoped with BelongsToCompany/CompanyScope (CLAUDE.md §9.1): a global
 * company scope would silently return only rows for the CURRENTLY ACTIVE company,
 * breaking the tenant-level (company_id NULL) fallback that Services\SettingsRepository
 * relies on to resolve "company override, else tenant default, else registered default".
 * The repository performs that merge explicitly instead of leaning on a scope.
 *
 * @property int $id
 * @property int|null $company_id
 * @property string $key
 * @property mixed $value
 */
class Setting extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['company_id', 'key', 'value'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }
}
