import { type FormEvent, useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ApiError } from '@zenon/core/apiClient';
import { useBoot } from '@zenon/core/bootstrap';
import { hasPermission } from '@zenon/core/permissions';
import { useUiStore } from '@zenon/core/store';
import { Alert, AlertDescription, Button, Field, Input, Skeleton, Switch } from '@zenon/core/ui';
import { ApiErrorAlert } from '../components/api-error-alert';
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
 * definition-driven: string → Input, int/float → number Input, bool → Switch.
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
        <div className="flex max-w-2xl flex-col gap-6">
            <h1 className="text-lg font-semibold">{t('settings.title')}</h1>

            {(definitionsQuery.isError || valuesQuery.isError) && (
                <ApiErrorAlert error={definitionsQuery.error ?? valuesQuery.error} />
            )}

            {loading ? (
                <div className="flex flex-col gap-4">
                    {Array.from({ length: 4 }).map((_, index) => (
                        <Skeleton key={index} className="h-9 w-full" />
                    ))}
                </div>
            ) : (
                <form className="flex flex-col gap-5" onSubmit={handleSubmit}>
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
                                        onCheckedChange={(checked) => update(definition.key, checked)}
                                        disabled={!canUpdate}
                                    />
                                ) : (
                                    <Input
                                        id={definition.key}
                                        type={definition.type === 'int' || definition.type === 'float' ? 'number' : 'text'}
                                        value={value == null ? '' : String(value)}
                                        onValueChange={(next) => update(definition.key, next)}
                                        disabled={!canUpdate}
                                    />
                                )}
                            </Field>
                        );
                    })}

                    {saved && !save.isPending && (
                        <Alert variant="success">
                            <AlertDescription>{t('shell:common.saved')}</AlertDescription>
                        </Alert>
                    )}

                    {canUpdate && (
                        <div>
                            <Button type="submit" disabled={save.isPending}>
                                {t('shell:common.save')}
                            </Button>
                        </div>
                    )}
                </form>
            )}
        </div>
    );
}
