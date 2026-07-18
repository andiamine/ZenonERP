<?php

namespace Modules\Core\Contracts\Companies;

/**
 * Snapshot of a company for cross-module/API consumption (CLAUDE.md §7) — this exact
 * shape feeds /api/v1/bootstrap and the SPA's Company type. Not the Eloquent model:
 * Contracts stay free of persistence concerns (CLAUDE.md §2).
 */
final readonly class CompanyData
{
    public function __construct(
        public int $id,
        public string $name,
        public string $code,
        public string $currencyCode,
        public bool $isDefault,
    ) {}

    /**
     * @return array{id: int, name: string, code: string, currency_code: string, is_default: bool}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'currency_code' => $this->currencyCode,
            'is_default' => $this->isDefault,
        ];
    }
}
