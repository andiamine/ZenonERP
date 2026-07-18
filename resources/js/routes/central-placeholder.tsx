import { useTranslation } from 'react-i18next';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@zenon/core/ui';

/**
 * Rendered router-less from main.tsx when boot detects a central host (the
 * tenant-api bootstrap 404s there). Swapped for the platform admin UI in a
 * later milestone.
 */
export function CentralPlaceholder() {
    const { t } = useTranslation();

    return (
        <div className="flex min-h-screen items-center justify-center bg-background p-6">
            <Card className="w-full max-w-md">
                <CardHeader>
                    <CardTitle className="text-lg">{t('central.title')}</CardTitle>
                    <CardDescription>{t('central.body')}</CardDescription>
                </CardHeader>
                <CardContent />
            </Card>
        </div>
    );
}
