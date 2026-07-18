<?php

namespace Modules\Core\Contracts\Settings;

/**
 * Bound as a scoped singleton (CoreServiceProvider) — every module provider's boot()
 * registers its own setting definitions here, ahead of any read/write.
 */
interface SettingsRegistrar
{
    public function register(SettingDefinition ...$definitions): void;

    /**
     * @return array<string, SettingDefinition> keyed by SettingDefinition::$key
     */
    public function definitions(): array;
}
