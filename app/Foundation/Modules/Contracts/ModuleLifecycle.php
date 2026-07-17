<?php

namespace App\Foundation\Modules\Contracts;

use App\Models\Tenant;

/**
 * Optional per-module lifecycle hooks (CLAUDE.md §5) — a module ships a
 * {Name}Module class next to its provider namespace root implementing this
 * (or extending BaseModuleLifecycle to override only what it needs).
 */
interface ModuleLifecycle
{
    /** After platform-wide install (central context). */
    public function installed(): void;

    /** Before tenant migrations run (central context). */
    public function enabling(Tenant $tenant): void;

    /** After migrations + seeding succeed (tenant context). */
    public function enabled(Tenant $tenant): void;

    /** After the module is disabled for the tenant (tenant context). */
    public function disabled(Tenant $tenant): void;

    /** Before the module's tenant migrations are rolled back (tenant context). */
    public function purging(Tenant $tenant): void;
}
