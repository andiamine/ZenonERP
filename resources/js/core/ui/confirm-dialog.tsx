import Alert from '@mui/material/Alert';
import Button from '@mui/material/Button';
import Dialog from '@mui/material/Dialog';
import DialogActions from '@mui/material/DialogActions';
import DialogContent from '@mui/material/DialogContent';
import DialogContentText from '@mui/material/DialogContentText';
import DialogTitle from '@mui/material/DialogTitle';
import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { ApiError } from '../apiClient';

/**
 * Reusable confirm-delete dialog (promoted to the kit in the MUI migration — it was
 * duplicated across Core/Audit/Sequence). State invariant violations the backend guards
 * (default/last company, the admin role, self-delete) come back as a 409 `conflict`
 * envelope; its message renders inline here rather than dismissing the dialog, so the
 * user sees why the delete was refused (Core design doc §"403 pattern", extended to 409).
 * i18n uses the shell namespace only — the kit must not depend on any module's bundle.
 */
export function ConfirmDialog({
    open,
    onOpenChange,
    title,
    description,
    confirmLabel,
    onConfirm,
    isPending = false,
    error,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description: ReactNode;
    confirmLabel?: string;
    onConfirm: () => void;
    isPending?: boolean;
    error?: unknown;
}) {
    const { t } = useTranslation();

    return (
        <Dialog open={open} onClose={() => onOpenChange(false)} maxWidth="xs" fullWidth>
            <DialogTitle>{title}</DialogTitle>
            <DialogContent>
                <DialogContentText>{description}</DialogContentText>
                {error instanceof ApiError && error.type !== 'validation_error' && (
                    <Alert severity="error" sx={{ mt: 2 }}>
                        {error.message}
                    </Alert>
                )}
            </DialogContent>
            <DialogActions>
                <Button color="inherit" onClick={() => onOpenChange(false)} disabled={isPending}>
                    {t('common.cancel')}
                </Button>
                <Button variant="contained" color="error" onClick={onConfirm} disabled={isPending}>
                    {confirmLabel ?? t('common.delete')}
                </Button>
            </DialogActions>
        </Dialog>
    );
}
