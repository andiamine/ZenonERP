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
     */
    public function __construct(
        public string $key,
        public string $type,
        public mixed $default,
        public ?string $label = null,
    ) {}
}
