import { useTranslation } from 'react-i18next';
import { ApiError } from '@zenon/core/apiClient';
import { Alert, AlertDescription, AlertTitle } from '@zenon/core/ui';

/**
 * Inline error surface for in-page API failures (design doc §"403 pattern"): a revoked
 * permission (mid-session 403) or any other non-validation error renders here as a
 * destructive Alert rather than redirecting mid-page. Validation errors (422) are handled
 * by the calling form on its Fields, so they are skipped here.
 */
export function ApiErrorAlert({ error }: { error: unknown }) {
    const { t } = useTranslation('core');

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
