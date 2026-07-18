import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { ApiError } from '@zenon/core/apiClient';
import { Alert, AlertDescription, Button, Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@zenon/core/ui';

/**
 * Reusable confirm-delete dialog. State invariant violations the backend guards (default/last
 * company, the admin role, self-delete) come back as a 409 `conflict` envelope; its message
 * renders inline here rather than dismissing the dialog, so the user sees why the delete was
 * refused (design doc §"403 pattern", extended to 409).
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
    const { t } = useTranslation('core');

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>
                {error instanceof ApiError && error.type !== 'validation_error' && (
                    <Alert variant="destructive">
                        <AlertDescription>{error.message}</AlertDescription>
                    </Alert>
                )}
                <DialogFooter>
                    <Button variant="outline" onClick={() => onOpenChange(false)} disabled={isPending}>
                        {t('shell:common.cancel')}
                    </Button>
                    <Button variant="destructive" onClick={onConfirm} disabled={isPending}>
                        {confirmLabel ?? t('shell:common.delete')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
