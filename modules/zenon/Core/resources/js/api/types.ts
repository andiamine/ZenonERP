import type { Company } from '@zenon/core/moduleTypes';

/**
 * DTOs for the Core (`zenon/core`) API (CLAUDE.md §9.1) — the exact shapes the backend
 * Resources emit (modules/zenon/Core/app/Http/Resources/*). `roles`/`permissions`/`users`
 * are `whenLoaded`, so they are optional here and only present when the request `include`s
 * them.
 */
export interface UserDto {
    id: number;
    name: string;
    email: string;
    /** Present only with `?include=roles` — a flat list of role names. */
    roles?: string[];
    created_at: string;
}

export interface RoleDto {
    id: number;
    name: string;
    /** Present only with `?include=permissions` — a flat list of permission names. */
    permissions?: string[];
}

export interface PermissionDto {
    id: number;
    name: string;
}

export interface TeamDto {
    id: number;
    name: string;
    description: string | null;
    /** null = shared across every company (§9.3). */
    company_id: number | null;
    active: boolean;
    /** Present only with `?include=users`. */
    users?: UserDto[];
}

/**
 * The full company resource. Extends the shell's bootstrap `Company` (id, name, code,
 * currency_code, is_default) with the extra columns the management page needs — the list
 * and detail endpoints return all of them (CompanyResource::toArray).
 */
export interface CompanyDto extends Company {
    legal_name: string | null;
    country_code: string | null;
    timezone: string | null;
    active: boolean;
    created_at: string;
    updated_at: string;
}

export interface CurrencyDto {
    id: number;
    code: string;
    name: string;
    symbol: string | null;
    decimal_places: number;
    active: boolean;
    created_at: string;
    updated_at: string;
}

/** One entry from `GET /settings/definitions` (§9.1). */
export type SettingType = 'string' | 'int' | 'float' | 'bool' | 'array';

export interface SettingDefinitionDto {
    key: string;
    type: SettingType;
    default: unknown;
    /** Human label; falls back to the key when null. */
    label: string | null;
}

/** The effective values map from `GET /settings` — key → typed value. */
export type SettingsValues = Record<string, unknown>;
