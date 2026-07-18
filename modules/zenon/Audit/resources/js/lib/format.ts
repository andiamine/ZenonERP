import type { TFunction } from 'i18next';

/** Shared cosmetic helpers for the activity list page + the recent-activity widget (same module — safe to share, unlike cross-module code). */
export type EventBadgeVariant = 'success' | 'info' | 'destructive' | 'secondary';

export function eventBadgeVariant(event: string | null): EventBadgeVariant {
    switch (event) {
        case 'created':
            return 'success';
        case 'updated':
            return 'info';
        case 'deleted':
            return 'destructive';
        default:
            return 'secondary';
    }
}

/** Renders a property value for the properties Dialog — objects (arrays, nested shapes) render as JSON. */
export function formatPropertyValue(value: unknown): string {
    if (value === undefined || value === null) {
        return '—';
    }
    if (typeof value === 'object') {
        return JSON.stringify(value);
    }
    return String(value);
}

/**
 * Compact "relative-ish" timestamp for the dashboard widget (design doc §Step 6) — coarse
 * minute/hour/day buckets, not a full calendar-aware relative-time library (none is in the
 * Phase 5 dependency set, CLAUDE.md §2 pins the stack deliberately).
 */
export function relativeTime(iso: string, t: TFunction): string {
    const minutes = Math.floor((Date.now() - new Date(iso).getTime()) / 60_000);

    if (minutes < 1) {
        return t('widgets.recent.justNow');
    }
    if (minutes < 60) {
        return t('widgets.recent.minutesAgo', { count: minutes });
    }
    const hours = Math.floor(minutes / 60);
    if (hours < 24) {
        return t('widgets.recent.hoursAgo', { count: hours });
    }
    const days = Math.floor(hours / 24);
    return t('widgets.recent.daysAgo', { count: days });
}
