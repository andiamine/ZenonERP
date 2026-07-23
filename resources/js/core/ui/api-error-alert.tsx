import Alert from '@mui/material/Alert';
import AlertTitle from '@mui/material/AlertTitle';
import { useTranslation } from 'react-i18next';
import { ApiError } from '../apiClient';

/**
 * Inline error surface for in-page API failures (promoted to the kit in the MUI
 * migration — it was duplicated across Core/Audit/Sequence): a revoked permission
 * (mid-session 403) or any other non-validation error renders here as an error Alert
 * rather than redirecting mid-page. Validation errors (422) are handled by the calling
 * form on its Fields, so they are skipped here. i18n uses the shell namespace only.
 */
export function ApiErrorAlert({ error }: { error: unknown }) {
    const { t } = useTranslation();

    if (!(error instanceof ApiError) || error.type === 'validation_error') {
        return null;
    }

    return (
        <Alert severity="error">
            <AlertTitle>{error.type === 'forbidden' ? t('errors.forbidden') : t('errors.generic')}</AlertTitle>
            {error.message}
        </Alert>
    );
}
