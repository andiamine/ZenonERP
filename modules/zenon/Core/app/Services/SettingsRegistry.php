<?php

namespace Modules\Core\Services;

use Modules\Core\Contracts\Settings\SettingDefinition;
use Modules\Core\Contracts\Settings\SettingsRegistrar;

/**
 * In-memory registry of module-declared setting definitions (CLAUDE.md §9.1). Bound as
 * a scoped singleton (CoreServiceProvider) — every module provider's boot() call to
 * register() accumulates into the same instance for the lifetime of the
 * request/worker cycle. Persistence of actual values is Services\SettingsRepository's job.
 */
final class SettingsRegistry implements SettingsRegistrar
{
    /** @var array<string, SettingDefinition> */
    private array $definitions = [];

    public function register(SettingDefinition ...$definitions): void
    {
        foreach ($definitions as $definition) {
            // Last write wins — a later-booted module deliberately overriding an
            // earlier definition (e.g. redefining a label) is not an error case.
            $this->definitions[$definition->key] = $definition;
        }
    }

    /**
     * @return array<string, SettingDefinition>
     */
    public function definitions(): array
    {
        return $this->definitions;
    }
}
