import {
    Alert,
    AlertTitle,
    Card,
    CardContent,
    CardHeader,
    Skeleton,
    Stack,
    Table,
    TableBody,
    TableCell,
    TableContainer,
    TableHead,
    TableRow,
    Typography,
} from '@mui/material';
import { useTranslation } from 'react-i18next';
import { useDemoCompanies } from '../api';

/**
 * The Demo addon page — makes the addon-computed fields visible: each company's name/code from
 * the Core API, alongside the `insight` and `computed_by` values the Demo PHP hook injected into
 * the response `extra` map. When the backend filter is off (or a company has no entry) the
 * insight cell shows a placeholder — proving the remote degrades gracefully. UI comes from
 * `@mui/material` (the host's shared singleton — root barrel ONLY, the addon platform contract):
 * the addon ships no UI code or CSS of its own and inherits the host theme at mount.
 */
export function DemoPage() {
    const { t } = useTranslation('demo');
    const query = useDemoCompanies();
    const companies = query.data?.data ?? [];
    const extra = query.data?.extra ?? {};

    return (
        <Stack spacing={3}>
            <Typography variant="h5" component="h1" sx={{ fontWeight: 600 }}>
                {t('page.title')}
            </Typography>

            {query.isError && (
                <Alert severity="error">
                    <AlertTitle>{t('page.error')}</AlertTitle>
                    {t('page.errorHint')}
                </Alert>
            )}

            <Card variant="outlined">
                <CardHeader title={t('page.cardTitle')} subheader={t('page.cardDescription')} />
                <CardContent>
                    {query.isLoading ? (
                        <Stack spacing={1}>
                            {Array.from({ length: 3 }).map((_, index) => (
                                <Skeleton key={index} variant="rounded" height={32} />
                            ))}
                        </Stack>
                    ) : companies.length === 0 ? (
                        <Typography variant="body2" color="text.secondary">
                            {t('page.empty')}
                        </Typography>
                    ) : (
                        <TableContainer>
                            <Table size="small">
                                <TableHead>
                                    <TableRow>
                                        <TableCell sx={{ fontWeight: 600 }}>{t('columns.name')}</TableCell>
                                        <TableCell sx={{ fontWeight: 600 }}>{t('columns.code')}</TableCell>
                                        <TableCell sx={{ fontWeight: 600 }}>{t('columns.insight')}</TableCell>
                                        <TableCell sx={{ fontWeight: 600 }}>{t('columns.computedBy')}</TableCell>
                                    </TableRow>
                                </TableHead>
                                <TableBody>
                                    {companies.map((company) => {
                                        const insight = extra[company.id];

                                        return (
                                            <TableRow key={company.id}>
                                                <TableCell sx={{ fontWeight: 500 }}>{company.name}</TableCell>
                                                <TableCell>{company.code}</TableCell>
                                                <TableCell>
                                                    {insight ? (
                                                        insight.insight
                                                    ) : (
                                                        <Typography component="span" variant="inherit" sx={{ color: 'text.secondary' }}>
                                                            {t('page.noInsight')}
                                                        </Typography>
                                                    )}
                                                </TableCell>
                                                <TableCell sx={{ color: 'text.secondary' }}>{insight?.computed_by ?? '—'}</TableCell>
                                            </TableRow>
                                        );
                                    })}
                                </TableBody>
                            </Table>
                        </TableContainer>
                    )}
                </CardContent>
            </Card>
        </Stack>
    );
}
