/**
 * DTOs for the Audit (`zenon/audit`) API (CLAUDE.md §9.2) — the exact shape
 * ActivityResource::toArray() emits (modules/zenon/Audit/app/Http/Resources/ActivityResource.php).
 */
export interface CauserDto {
    id: number;
    name: string | null;
}

/**
 * As stored by spatie/laravel-activitylog's LogsActivity::attributeValuesToBeLogged():
 * `{ attributes }` on create/delete, `{ attributes, old }` (dirty keys only) on update.
 */
export interface ActivityPropertiesDto {
    attributes?: Record<string, unknown>;
    old?: Record<string, unknown>;
}

export interface ActivityDto {
    id: number;
    log_name: string | null;
    description: string | null;
    event: string | null;
    subject_type: string | null;
    subject_id: number | string | null;
    causer: CauserDto | null;
    properties: ActivityPropertiesDto;
    batch_uuid: string | null;
    created_at: string;
}
