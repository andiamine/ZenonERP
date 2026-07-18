/**
 * DTOs for the Sequence (`zenon/sequence`) API (CLAUDE.md §9.2) — the exact shapes the
 * backend Resources emit (modules/zenon/Sequence/app/Http/Resources/SequenceResource.php,
 * SequencesController::definitions()).
 */
export type ResetPeriod = 'never' | 'year' | 'month';

/** One materialized counter row — a row exists once a code has been drawn. */
export interface SequenceDto {
    id: number;
    code: string;
    /** null = tenant-wide (shared across every company, §9.3). */
    company_id: number | null;
    mask: string;
    next_number: number;
    reset_period: ResetPeriod;
    current_period: string | null;
    gapless: boolean;
    /** Read-only render of the value the NEXT allocation would produce (no counter mutation). */
    preview: string;
}

/** One registered definition from `GET /definitions` — includes codes with no row yet. */
export interface SequenceDefinitionDto {
    code: string;
    mask: string;
    reset_period: ResetPeriod;
    per_company: boolean;
    gapless: boolean;
    label: string | null;
    /** Whether a Sequence row has been drawn for this code yet. */
    materialized: boolean;
}
