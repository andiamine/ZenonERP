import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api, listQuery, type Paginated } from '@zenon/core/apiClient';
import type { CompanyDto } from './types';

/** Companies are tenant-scoped (the same set regardless of the current company). */
export interface CompanyListParams {
    name?: string;
    page?: number;
}

export function useCompanies(params: CompanyListParams) {
    const query = listQuery({
        filter: params.name ? { name: params.name } : undefined,
        page: params.page,
    });

    return useQuery({
        queryKey: ['core', 'companies', params],
        queryFn: () => api.get<Paginated<CompanyDto>>(`/api/v1/core/companies${query}`),
        placeholderData: keepPreviousData,
    });
}

export interface CompanyInput {
    name: string;
    code: string;
    currency_code: string;
    legal_name: string | null;
    country_code: string | null;
    timezone: string | null;
    active: boolean;
}

export function useCreateCompany() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (input: CompanyInput) => api.post<{ data: CompanyDto }>('/api/v1/core/companies', input),
        onSuccess: () => queryClient.invalidateQueries({ queryKey: ['core', 'companies'] }),
    });
}

export function useUpdateCompany() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({ id, ...input }: CompanyInput & { id: number }) =>
            api.patch<{ data: CompanyDto }>(`/api/v1/core/companies/${id}`, input),
        onSuccess: () => queryClient.invalidateQueries({ queryKey: ['core', 'companies'] }),
    });
}

export function useDeleteCompany() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (id: number) => api.delete<void>(`/api/v1/core/companies/${id}`),
        onSuccess: () => queryClient.invalidateQueries({ queryKey: ['core', 'companies'] }),
    });
}
