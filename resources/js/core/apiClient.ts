/**
 * Minimal fetch wrapper for the ZenonERP API (Sanctum SPA cookie mode, CLAUDE.md §8).
 *
 * How the auth flow works: GET /sanctum/csrf-cookie sets the XSRF-TOKEN cookie
 * (readable, URL-encoded) plus the session cookie; every mutating request echoes the
 * token back as X-XSRF-TOKEN (the same thing axios automates). Laravel serves the SPA
 * (same origin, stance §3), and SESSION_DOMAIN=null keeps cookies host-only, so each
 * tenant subdomain has its own isolated session and `credentials: 'same-origin'`
 * suffices.
 */

import { useUiStore } from './store';

/** The §8 error envelope, thrown for every non-2xx response. */
export class ApiError extends Error {
    constructor(
        readonly status: number,
        readonly type: string,
        message: string,
        readonly errors?: Record<string, string[]>,
        readonly traceId?: string,
    ) {
        super(message);
        this.name = 'ApiError';
    }
}

interface ErrorEnvelope {
    error?: {
        type?: string;
        message?: string;
        errors?: Record<string, string[]>;
        trace_id?: string;
    };
}

function xsrfToken(): string | null {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match?.[1] !== undefined ? decodeURIComponent(match[1]) : null;
}

/** Prime the CSRF/session cookies; no-ops when the XSRF cookie is already present. */
export async function primeCsrf(): Promise<void> {
    if (xsrfToken() === null) {
        await fetch('/sanctum/csrf-cookie', { credentials: 'same-origin' });
    }
}

async function request<T>(method: string, url: string, body?: unknown, retried = false): Promise<T> {
    const headers: Record<string, string> = { Accept: 'application/json' };

    if (body !== undefined) {
        headers['Content-Type'] = 'application/json';
    }

    if (method !== 'GET') {
        const token = xsrfToken();
        if (token !== null) {
            headers['X-XSRF-TOKEN'] = token;
        }
    }

    // Company scoping (§8): SetCurrentCompany reads X-Company-Id. Send it on every API
    // request once a company is selected. The /sanctum/csrf-cookie calls use raw fetch()
    // (primeCsrf + the 419 re-prime), never request(), so they never carry the header. A
    // stale id left in localStorage on a central host is harmless — the tenant-api group
    // 404s there server-side (PreventAccessFromCentralDomains), ignoring the header.
    const companyId = useUiStore.getState().currentCompanyId;
    if (companyId !== null) {
        headers['X-Company-Id'] = String(companyId);
    }

    const response = await fetch(url, {
        method,
        headers,
        credentials: 'same-origin',
        body: body === undefined ? undefined : JSON.stringify(body),
    });

    // Expired CSRF token / session: re-prime once and retry.
    if (response.status === 419 && !retried) {
        await fetch('/sanctum/csrf-cookie', { credentials: 'same-origin' });

        return request<T>(method, url, body, true);
    }

    if (response.status === 204) {
        return undefined as T;
    }

    const json: unknown = await response.json().catch(() => null);

    if (!response.ok) {
        const error = (json as ErrorEnvelope | null)?.error;

        throw new ApiError(
            response.status,
            error?.type ?? 'server_error',
            error?.message ?? response.statusText,
            error?.errors,
            error?.trace_id,
        );
    }

    return json as T;
}

export const api = {
    get: <T>(url: string): Promise<T> => request<T>('GET', url),
    post: <T>(url: string, body?: unknown): Promise<T> => request<T>('POST', url, body),
    put: <T>(url: string, body?: unknown): Promise<T> => request<T>('PUT', url, body),
    patch: <T>(url: string, body?: unknown): Promise<T> => request<T>('PATCH', url, body),
    // DELETE tolerates a 204 No Content response (request() returns undefined for 204).
    delete: <T>(url: string, body?: unknown): Promise<T> => request<T>('DELETE', url, body),
};

/**
 * spatie/laravel-query-builder page-based paginator meta (CLAUDE.md §8). The backend
 * paginator emits more fields (from/to/links); the shell only types what it consumes —
 * DataTable derives the "x–y of total" range from these four.
 */
export interface PageMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

/** A paginated list response: API Resource `data` array plus `meta` (§8). */
export interface Paginated<T> {
    data: T[];
    meta: PageMeta;
}

/**
 * Builds a spatie/laravel-query-builder query string (§8), e.g.
 * `?filter[name]=x&sort=-id&page=2`. Brackets stay literal (Laravel parses `filter[key]`
 * array syntax); keys and values are percent-encoded. Returns '' when no params are set.
 */
export function listQuery(params: {
    filter?: Record<string, string | number | boolean>;
    sort?: string;
    include?: string;
    page?: number;
    per_page?: number;
}): string {
    const parts: string[] = [];

    if (params.filter !== undefined) {
        for (const [key, value] of Object.entries(params.filter)) {
            parts.push(`filter[${encodeURIComponent(key)}]=${encodeURIComponent(String(value))}`);
        }
    }
    if (params.sort !== undefined) {
        parts.push(`sort=${encodeURIComponent(params.sort)}`);
    }
    if (params.include !== undefined) {
        parts.push(`include=${encodeURIComponent(params.include)}`);
    }
    if (params.page !== undefined) {
        parts.push(`page=${params.page}`);
    }
    if (params.per_page !== undefined) {
        parts.push(`per_page=${params.per_page}`);
    }

    return parts.length === 0 ? '' : `?${parts.join('&')}`;
}
