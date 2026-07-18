<?php

namespace App\Foundation\Company;

/**
 * Per-request holder of the active company id (CLAUDE.md §8/§9.3), set by
 * SetCurrentCompany and consulted by CompanyScope. Scoped, never singleton
 * (AppServiceProvider::register()) — like ModuleRegistry, its state must reset
 * between requests/queue jobs (Octane, queue workers reuse the container).
 */
final class CurrentCompany
{
    private ?int $id = null;

    public function set(?int $id): void
    {
        $this->id = $id;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function isSet(): bool
    {
        return $this->id !== null;
    }
}
