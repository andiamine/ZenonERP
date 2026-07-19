import { useTranslation } from 'react-i18next';
import {
    Alert,
    AlertDescription,
    AlertTitle,
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
    Skeleton,
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@zenon/core/ui';
import { useDemoCompanies } from '../api';

/**
 * The Demo addon page — makes the addon-computed fields visible: each company's name/code from
 * the Core API, alongside the `insight` and `computed_by` values the Demo PHP hook injected into
 * the response `extra` map. When the backend filter is off (or a company has no entry) the
 * insight cell shows a placeholder — proving the remote degrades gracefully. UI is sourced only
 * from `@zenon/core/ui`.
 */
export function DemoPage() {
    const { t } = useTranslation('demo');
    const query = useDemoCompanies();
    const companies = query.data?.data ?? [];
    const extra = query.data?.extra ?? {};

    return (
        <div className="flex flex-col gap-6">
            <h1 className="text-lg font-semibold">{t('page.title')}</h1>

            {query.isError && (
                <Alert variant="destructive">
                    <AlertTitle>{t('page.error')}</AlertTitle>
                    <AlertDescription>{t('page.errorHint')}</AlertDescription>
                </Alert>
            )}

            <Card>
                <CardHeader>
                    <CardTitle>{t('page.cardTitle')}</CardTitle>
                    <CardDescription>{t('page.cardDescription')}</CardDescription>
                </CardHeader>
                <CardContent>
                    {query.isLoading ? (
                        <div className="flex flex-col gap-2">
                            {Array.from({ length: 3 }).map((_, index) => (
                                <Skeleton key={index} className="h-8 w-full" />
                            ))}
                        </div>
                    ) : companies.length === 0 ? (
                        <p className="text-sm text-muted-foreground">{t('page.empty')}</p>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('columns.name')}</TableHead>
                                    <TableHead>{t('columns.code')}</TableHead>
                                    <TableHead>{t('columns.insight')}</TableHead>
                                    <TableHead>{t('columns.computedBy')}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {companies.map((company) => {
                                    const insight = extra[company.id];

                                    return (
                                        <TableRow key={company.id}>
                                            <TableCell className="font-medium">{company.name}</TableCell>
                                            <TableCell>{company.code}</TableCell>
                                            <TableCell>
                                                {insight ? (
                                                    insight.insight
                                                ) : (
                                                    <span className="text-muted-foreground">{t('page.noInsight')}</span>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">{insight?.computed_by ?? '—'}</TableCell>
                                        </TableRow>
                                    );
                                })}
                            </TableBody>
                        </Table>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
