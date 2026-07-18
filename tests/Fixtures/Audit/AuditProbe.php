<?php

namespace Tests\Fixtures\Audit;

use Illuminate\Database\Eloquent\Model;
use Modules\Audit\Contracts\Auditable;

/**
 * Fixture consumer of the Auditable trait (CLAUDE.md §9.2's Odoo mail.thread analogue).
 * No production consumer exists yet (chatter lands M2, the Demo addon in Phase 7) — this
 * fixture exists so the trait has a real using-class, exercised by AuditableTest and
 * analysed by Larastan (a trait used zero times can't be checked).
 *
 * @property int $id
 * @property string|null $name
 * @property string|null $password
 */
class AuditProbe extends Model
{
    use Auditable;

    protected $table = 'audit_probes';

    protected $fillable = ['name', 'password'];

    public $timestamps = false;
}
