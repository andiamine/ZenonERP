import { Alert, Box, Button, OutlinedInput, Skeleton, Stack, Switch, Typography } from '@mui/material';
import { type FormEvent, useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ApiError } from '@zenon/core/apiClient';
import { useBoot } from '@zenon/core/bootstrap';
import { hasPermission } from '@zenon/core/permissions';
import { useUiStore } from '@zenon/core/store';
import { ApiErrorAlert, Field } from '@zenon/core/ui';
import { useSaveSettings, useSettingDefinitions, useSettings } from '../api/settings';
import type { SettingType, SettingsValues } from '../api/types';

/** Coerce a form value back to the JSON type the backend's per-key type check expects. */
function coerce(type: SettingType, raw: unknown): unknown {
    switch (type) {
        case 'bool':
            return Boolean(raw);
        case 'int': {
            const n = Number.parseInt(String(raw), 10);
            return Number.isNaN(n) ? raw : n;
        }
        case 'float': {
            const n = Number.parseFloat(String(raw));
            return Number.isNaN(n) ? raw : n;
        }
        case 'string':
            return raw == null ? '' : String(raw);
        default:
            return raw;
    }
}

/**
 * Typed settings editor (CLAUDE.md §9.1) and THE X-Company-Id verify surface: the values are
 * the current company's effective settings (company override ← tenant ← default), and the
 * header that scopes them rides apiClient automatically — no special code here. Form is
 * definition-driven: string → OutlinedInput, int/float → number OutlinedInput, bool → Switch.
 */
export function SettingsPage() {
    const { t } = useTranslation('core');
    const boot = useBoot();
    const companyId = useUiStore((state) => state.currentCompanyId) ?? boot.current_company_id;

    const definitionsQuery = useSettingDefinitions();
    const valuesQuery = useSettings(companyId);
    const save = useSaveSettings(companyId);

    const [form, setForm] = useState<SettingsValues | null>(null);
    const [saved, setSaved] = useState(false);
    // Guards a window-focus refetch from clobbering in-progress edits; reset once saved.
    const dirty = useRef(false);
    // The effective values as loaded — the baseline we diff against on save so only keys the
    // user actually changed are written. Without this every Save would stamp a company-level
    // override row for EVERY setting, permanently shadowing the tenant layer (§9.1).
    const original = useRef<SettingsValues | null>(null);

    useEffect(() => {
        if (valuesQuery.data && !dirty.current) {
            setForm(valuesQuery.data.data);
            original.current = valuesQuery.data.data;
        }
    }, [valuesQuery.data]);

    const canUpdate = hasPermission(boot, 'core.settings.update');
    const definitions = definitionsQuery.data?.data ?? [];
    const fieldErrors = save.error instanceof ApiError ? save.error.errors : undefined;
    const loading = definitionsQuery.isLoading || valuesQuery.isLoading || form === null;

    function update(key: string, value: unknown) {
        dirty.current = true;
        setSaved(false);
        setForm((previous) => ({ ...(previous ?? {}), [key]: value }));
    }

    function handleSubmit(event: FormEvent) {
        event.preventDefault();
        if (form === null) {
            return;
        }
        // Submit ONLY dirty keys (coerced value !== the loaded baseline). This is what keeps a
        // Save from writing a company-level override for every untouched setting.
        const values: SettingsValues = {};
        for (const definition of definitions) {
            const next = coerce(definition.type, form[definition.key]);
            if (JSON.stringify(next) !== JSON.stringify(original.current?.[definition.key])) {
                values[definition.key] = next;
            }
        }
        // Nothing changed: no-op the request and just confirm — a bare Save must never create
        // override rows.
        if (Object.keys(values).length === 0) {
            dirty.current = false;
            setSaved(true);
            return;
        }
        save.mutate(values, {
            onSuccess: () => {
                dirty.current = false;
                setSaved(true);
            },
        });
    }

    return (
        <Stack spacing={3} sx={{ maxWidth: 672 }}>
            <Typography variant="h5" component="h1" sx={{ fontWeight: 600 }}>
                {t('settings.title')}
            </Typography>

            {(definitionsQuery.isError || valuesQuery.isError) && (
                <ApiErrorAlert error={definitionsQuery.error ?? valuesQuery.error} />
            )}

            {loading ? (
                <Stack spacing={2}>
                    {Array.from({ length: 4 }).map((_, index) => (
                        <Skeleton key={index} variant="rounded" height={36} />
                    ))}
                </Stack>
            ) : (
                <Stack component="form" spacing={2.5} onSubmit={handleSubmit}>
                    {definitions.map((definition) => {
                        const label = definition.label ?? definition.key;
                        const error = fieldErrors?.[`values.${definition.key}`];
                        const value = form?.[definition.key];

                        return (
                            <Field key={definition.key} label={label} htmlFor={definition.key} error={error}>
                                {definition.type === 'bool' ? (
                                    <Switch
                                        id={definition.key}
                                        checked={Boolean(value)}
                                        onChange={(event) => update(definition.key, event.target.checked)}
                                        disabled={!canUpdate}
                                        sx={{ alignSelf: 'flex-start' }}
                                    />
                                ) : (
                                    <OutlinedInput
                                        id={definition.key}
                                        type={definition.type === 'int' || definition.type === 'float' ? 'number' : 'text'}
                                        value={value == null ? '' : String(value)}
                                        onChange={(event) => update(definition.key, event.target.value)}
                                        disabled={!canUpdate}
                                    />
                                )}
                            </Field>
                        );
                    })}

                    {saved && !save.isPending && <Alert severity="success">{t('shell:common.saved')}</Alert>}

                    {canUpdate && (
                        <Box>
                            <Button type="submit" variant="contained" disabled={save.isPending}>
                                {t('shell:common.save')}
                            </Button>
                        </Box>
                    )}
                </Stack>
            )}
        </Stack>
    );
}
