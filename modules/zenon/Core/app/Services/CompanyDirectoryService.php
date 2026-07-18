<?php

namespace Modules\Core\Services;

use App\Foundation\Company\Contracts\CompanyResolver;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Core\Contracts\Companies\CompanyData;
use Modules\Core\Contracts\Companies\CompanyDirectory;
use Modules\Core\Models\Company;

/**
 * Implements both the module-facing CompanyDirectory contract and the Foundation-facing
 * CompanyResolver port (CLAUDE.md §8) — SetCurrentCompany depends on the latter without
 * ever importing Modules\* (CLAUDE.md §5); this is the sanctioned binding zenon/core
 * supplies for it (CoreServiceProvider).
 */
final class CompanyDirectoryService implements CompanyDirectory, CompanyResolver
{
    /**
     * @return list<CompanyData>
     */
    public function companiesFor(User $user): array
    {
        return array_values(
            Company::query()
                ->whereHas('users', fn ($query) => $query->whereKey($user->getKey()))
                ->orderByDesc('is_default')
                ->orderBy('id')
                ->get()
                ->map(fn (Company $company) => new CompanyData(
                    id: $company->id,
                    name: $company->name,
                    code: $company->code,
                    currencyCode: $company->currency_code,
                    isDefault: $company->is_default,
                ))
                ->all(),
        );
    }

    public function defaultCompanyIdFor(Authenticatable $user): ?int
    {
        if (! $user instanceof User) {
            return null;
        }

        return $this->companiesFor($user)[0]->id ?? null;
    }

    /**
     * @return list<int>
     */
    public function companyIdsFor(Authenticatable $user): array
    {
        if (! $user instanceof User) {
            return [];
        }

        return array_map(fn (CompanyData $company) => $company->id, $this->companiesFor($user));
    }
}
