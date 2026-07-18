import { useTranslation } from 'react-i18next';
import { ApiError } from '@zenon/core/apiClient';
import { Alert, AlertDescription, AlertTitle } from '@zenon/core/ui';

/**
 * Inline error surface for in-page API failures (design doc §"403 pattern"). Replicated
 * locally per module — the eslint cross-module boundary (CLAUDE.md §2) forbids importing
 * Core's copy from `@modules/Core/...`; see
 * modules/zenon/Core/resources/js/components/api-error-alert.tsx for the original. Validation
 * errors (422) are handled by the calling form on its Fields, so they are skipped here.
 */
export function ApiErrorAlert({ error }: { error: unknown }) {
    const { t } = useTranslation('sequence');

    if (!(error instanceof ApiError) || error.type === 'validation_error') {
        return null;
    }

    return (
        <Alert variant="destructive">
            <AlertTitle>{error.type === 'forbidden' ? t('errors.forbidden') : t('errors.generic')}</AlertTitle>
            <AlertDescription>{error.message}</AlertDescription>
        </Alert>
    );
}
