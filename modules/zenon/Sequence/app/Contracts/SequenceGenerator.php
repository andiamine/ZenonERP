<?php

namespace Modules\Sequence\Contracts;

/**
 * Public numbering API (CLAUDE.md §9.2 — Odoo ir.sequence lineage). Bound in
 * SequenceServiceProvider::register() to Services\SequenceService. Consumers depend on
 * THIS interface (never the service class) — the only cross-module-visible entry point.
 */
interface SequenceGenerator
{
    /**
     * Allocate and format the next number for $code, optionally scoped to a company.
     * The allocation + row write happen inside one transaction (gapless): called inside
     * an outer transaction it becomes a savepoint and a rollback returns the number.
     */
    public function next(string $code, ?int $companyId = null): string;
}
