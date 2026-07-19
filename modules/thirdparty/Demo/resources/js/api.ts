import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { api, listQuery, type Paginated } from '@zenon/core/apiClient';
import type { Company } from '@zenon/core/moduleTypes';

/**
 * The addon-computed fields the Demo PHP hook (AddCompanyInsights) appends per company id to
 * the Core companies response `extra` map. Present ONLY when the Demo backend filter is enabled
 * for the tenant — the UI renders gracefully (falls back to a placeholder) when a company has
 * no entry.
 */
export interface CompanyInsight {
    insight: string;
    name_length: number;
    computed_by: string;
    platform: string;
}

/**
 * `GET /api/v1/core/companies` shape: the standard paginated list plus the addon `extra` map
 * keyed by (stringified) company id — `CompaniesController::index` calls `->additional(['extra' => …])`.
 * `noUncheckedIndexedAccess` makes a lookup miss surface as `undefined`, which the UI handles.
 */
export interface CompaniesResponse extends Paginated<Company> {
    extra: Record<string, CompanyInsight | undefined>;
}

/**
 * Shared companies+insights query, consumed by both the `/demo` page and the dashboard widget
 * (one cache entry, one request). No company scoping — companies are tenant-scoped.
 */
export function useDemoCompanies() {
    const query = listQuery({ per_page: 50 });

    return useQuery({
        queryKey: ['demo', 'companies'],
        queryFn: () => api.get<CompaniesResponse>(`/api/v1/core/companies${query}`),
        placeholderData: keepPreviousData,
    });
}
