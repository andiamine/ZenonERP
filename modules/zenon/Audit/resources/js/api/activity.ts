import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { api, listQuery, type Paginated } from '@zenon/core/apiClient';
import type { ActivityDto } from './types';

/**
 * Activities are tenant-scoped (spatie/laravel-activitylog rows carry no `company_id`), so
 * unlike Core's `settings` the key omits a company id entirely (design doc §Step 6:
 * `['audit','activity', params]`).
 */
export interface ActivityListParams {
    event?: string;
    subject_type?: string;
    causer_id?: number;
    from?: string;
    to?: string;
    page?: number;
    /** Override the default page size — the recent-activity dashboard widget asks for 5. */
    per_page?: number;
}

export function useActivity(params: ActivityListParams) {
    const filter: Record<string, string | number> = {};
    if (params.event !== undefined) {
        filter.event = params.event;
    }
    if (params.subject_type !== undefined) {
        filter.subject_type = params.subject_type;
    }
    if (params.causer_id !== undefined) {
        filter.causer_id = params.causer_id;
    }
    if (params.from !== undefined) {
        filter.from = params.from;
    }
    if (params.to !== undefined) {
        filter.to = params.to;
    }

    const query = listQuery({
        filter: Object.keys(filter).length > 0 ? filter : undefined,
        page: params.page,
        per_page: params.per_page,
    });

    return useQuery({
        queryKey: ['audit', 'activity', params],
        queryFn: () => api.get<Paginated<ActivityDto>>(`/api/v1/audit/activities${query}`),
        placeholderData: keepPreviousData,
    });
}
