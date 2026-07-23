import Box from '@mui/material/Box';
import Card from '@mui/material/Card';
import CardContent from '@mui/material/CardContent';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { useTranslation } from 'react-i18next';

/**
 * Rendered router-less from main.tsx when boot detects a central host (the
 * tenant-api bootstrap 404s there). Swapped for the platform admin UI in a
 * later milestone.
 */
export function CentralPlaceholder() {
    const { t } = useTranslation();

    return (
        <Box sx={{ minHeight: '100vh', display: 'flex', alignItems: 'center', justifyContent: 'center', p: 3, bgcolor: 'background.default' }}>
            <Card variant="outlined" sx={{ width: '100%', maxWidth: 480 }}>
                <CardContent sx={{ p: 4 }}>
                    <Stack spacing={1}>
                        <Typography variant="h5" component="h1" sx={{ fontWeight: 600 }}>
                            {t('central.title')}
                        </Typography>
                        <Typography variant="body2" color="text.secondary">
                            {t('central.body')}
                        </Typography>
                    </Stack>
                </CardContent>
            </Card>
        </Box>
    );
}
