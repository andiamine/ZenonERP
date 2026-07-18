<?php

namespace Modules\Core\Models;

use App\Foundation\Company\BelongsToCompany;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Core\Database\Factories\TeamFactory;

/**
 * ERP organizational team (Frappe User Group / Odoo sales-team style, CLAUDE.md §9.1) —
 * UNRELATED to spatie/laravel-permission's disabled "teams" feature. The first real
 * consumer of the Foundation BelongsToCompany trait.
 *
 * @property int $id
 * @property int|null $company_id
 * @property string $name
 * @property string|null $description
 * @property bool $active
 */
class Team extends Model
{
    /** @use HasFactory<TeamFactory> */
    use BelongsToCompany, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = ['company_id', 'name', 'description', 'active'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_user');
    }

    protected static function newFactory(): TeamFactory
    {
        return TeamFactory::new();
    }
}
