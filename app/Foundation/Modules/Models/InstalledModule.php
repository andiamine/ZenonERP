<?php

namespace App\Foundation\Modules\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Central `modules` table — the source of truth for which modules are installed
 * platform-wide (the nwidart statuses file is a derived boot artifact).
 *
 * @property int $id
 * @property string $alias
 * @property string $name
 * @property string $version
 * @property bool $core
 * @property Carbon $installed_at
 */
class InstalledModule extends Model
{
    use CentralConnection;

    protected $table = 'modules';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'core' => 'boolean',
            'installed_at' => 'datetime',
        ];
    }
}
