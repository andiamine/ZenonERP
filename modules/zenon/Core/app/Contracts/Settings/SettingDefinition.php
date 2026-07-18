<?php

namespace Modules\Core\Contracts\Settings;

/**
 * A module-registered setting: key, primitive type, default value (CLAUDE.md §9.1).
 * Registered via SettingsRegistrar::register() in a module provider's boot().
 */
final readonly class SettingDefinition
{
    /**
     * @param  string  $type  one of 'string'|'int'|'float'|'bool'|'array'
     * @param  string|null  $module  owning module alias (e.g. 'audit'). When set, the
     *                               definition — and any stored value for its key — is
     *                               invisible to tenants where that module is not enabled
     *                               (CLAUDE.md §6/§13 risk #1: disabled ⇒ behaviorally
     *                               invisible, including reads/writes of its settings).
     *                               Null = ungated (kernel-owned, e.g. Core's own settings).
     */
    public function __construct(
        public string $key,
        public string $type,
        public mixed $default,
        public ?string $label = null,
        public ?string $module = null,
    ) {}
}
