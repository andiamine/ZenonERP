import { useQuery } from '@tanstack/react-query';
import { api, listQuery, type Paginated } from '@zenon/core/apiClient';
import type { CurrencyDto } from './types';

/**
 * The tenant's currency catalogue — consumed by the companies page currency Select. Core
 * ships no currency-management page in Phase 5, so this file exposes only the read hook
 * (anti-gold-plating: hooks land when a page needs them).
 */
export function useCurrencies() {
    return useQuery({
        queryKey: ['core', 'currencies'],
        queryFn: () => api.get<Paginated<CurrencyDto>>(`/api/v1/core/currencies${listQuery({ sort: 'code', per_page: 100 })}`),
    });
}
