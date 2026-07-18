import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api, listQuery, type Paginated } from '@zenon/core/apiClient';
import type { ResetPeriod, SequenceDefinitionDto, SequenceDto } from './types';

/**
 * Sequence rows are per (code, company) — CLAUDE.md §9.2 — but the Sequence model is
 * deliberately NOT company-scoped by a global CompanyScope (see the model's own doc comment,
 * modules/zenon/Sequence/app/Models/Sequence.php): a tenant-wide sequence
 * (`company_id === null`) must stay visible even while a company context is active. So
 * `GET /sequences` always returns every company's rows in one list — the page renders a
 * "company" column (same pattern as Core's teams page) instead of filtering server-side. The
 * current company id still rides the query key per the design doc's "company-scoped"
 * convention, so the cache stays correct if a later phase adds default company filtering.
 */
export interface SequenceListParams {
    code?: string;
    page?: number;
}

export function useSequences(companyId: number | null, params: SequenceListParams) {
    const query = listQuery({
        filter: params.code ? { code: params.code } : undefined,
        page: params.page,
    });

    return useQuery({
        queryKey: ['sequence', 'sequences', companyId, params],
        queryFn: () => api.get<Paginated<SequenceDto>>(`/api/v1/sequence/sequences${query}`),
        placeholderData: keepPreviousData,
    });
}

/** All registered definitions (materialized or not) — tenant-scoped, identical for every company. */
export function useSequenceDefinitions() {
    return useQuery({
        queryKey: ['sequence', 'definitions'],
        queryFn: () => api.get<{ data: SequenceDefinitionDto[] }>('/api/v1/sequence/definitions'),
    });
}

export interface UpdateSequenceInput {
    id: number;
    mask: string;
    reset_period: ResetPeriod;
}

/**
 * Optimistic row update (CLAUDE.md §7): patches every currently-cached `sequences` list page
 * immediately (the edited row may sit on any page/company), rolls back on error (e.g. the
 * backend's {seq}-token 422), and settles with an invalidate so the server-computed `preview`
 * field refreshes.
 */
export function useUpdateSequence() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({ id, ...input }: UpdateSequenceInput) =>
            api.patch<{ data: SequenceDto }>(`/api/v1/sequence/sequences/${id}`, input),
        onMutate: async ({ id, ...input }) => {
            await queryClient.cancelQueries({ queryKey: ['sequence', 'sequences'] });
            const previous = queryClient.getQueriesData<Paginated<SequenceDto>>({ queryKey: ['sequence', 'sequences'] });

            queryClient.setQueriesData<Paginated<SequenceDto>>({ queryKey: ['sequence', 'sequences'] }, (data) =>
                data === undefined ? data : { ...data, data: data.data.map((row) => (row.id === id ? { ...row, ...input } : row)) },
            );

            return { previous };
        },
        onError: (_error, _input, context) => {
            context?.previous.forEach(([key, data]) => queryClient.setQueryData(key, data));
        },
        onSettled: () => {
            void queryClient.invalidateQueries({ queryKey: ['sequence', 'sequences'] });
        },
    });
}
