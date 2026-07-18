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
};
