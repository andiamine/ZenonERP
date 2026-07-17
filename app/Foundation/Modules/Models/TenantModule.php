<?php

namespace App\Foundation\Modules\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Central `tenant_modules` table — single source of truth for per-tenant enablement
 * (CLAUDE.md §4). CentralConnection keeps reads/writes correct even while tenancy
 * is initialized.
 *
 * @property int $id
 * @property string $tenant_id
 * @property string $module
 * @property bool $enabled
 * @property string|null $migrated_version
 */
class TenantModule extends Model
{
    use CentralConnection;

    protected $table = 'tenant_modules';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }
}
