<?php

namespace Modules\Core\Contracts\Settings;

interface SettingsReader
{
    /**
     * Resolution order: company row (if $companyId given) ?? tenant-level row
     * (company_id NULL) ?? the registered default ?? null.
     */
    public function get(string $key, ?int $companyId = null): mixed;

    /**
     * Effective merged map: registered defaults ← tenant-level rows ← company rows.
     *
     * @return array<string, mixed>
     */
    public function all(?int $companyId = null): array;
}
