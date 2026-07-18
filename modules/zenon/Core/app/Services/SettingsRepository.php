<?php

namespace Modules\Core\Services;

use App\Foundation\Modules\ModuleRegistry;
use Modules\Core\Contracts\Settings\Exceptions\InvalidSettingValueException;
use Modules\Core\Contracts\Settings\Exceptions\UnknownSettingException;
use Modules\Core\Contracts\Settings\SettingDefinition;
use Modules\Core\Contracts\Settings\SettingsRegistrar;
use Modules\Core\Contracts\Settings\SettingsRepository as SettingsRepositoryContract;
use Modules\Core\Models\Setting;

/**
 * Reads resolve company-override ← tenant-level ← registered-default explicitly
 * (CLAUDE.md §9.1) — see Models\Setting's docblock for why this can't be a global scope.
 *
 * Definitions are registered platform-wide (every installed module's provider boots
 * regardless of tenant enablement, CLAUDE.md §5 — same as routes always registering), but
 * a definition tagged with an owning `module` is gated at the READ/WRITE path here, the
 * same way EnsureModuleEnabled gates routes (CLAUDE.md §6/§13 risk #1: disabled must be
 * behaviorally invisible, not just hidden from the menu — that includes settings).
 */
final class SettingsRepository implements SettingsRepositoryContract
{
    public function __construct(
        private readonly SettingsRegistrar $registrar,
        private readonly ModuleRegistry $modules,
    ) {}

    public function get(string $key, ?int $companyId = null): mixed
    {
        $definition = $this->registrar->definitions()[$key] ?? null;

        if ($definition !== null && ! $this->isVisible($definition)) {
            return null;
        }

        if ($companyId !== null) {
            $companyRow = Setting::query()->where('company_id', $companyId)->where('key', $key)->first();

            if ($companyRow !== null) {
                return $companyRow->value;
            }
        }

        $tenantRow = Setting::query()->whereNull('company_id')->where('key', $key)->first();

        if ($tenantRow !== null) {
            return $tenantRow->value;
        }

        return $definition?->default;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(?int $companyId = null): array
    {
        $merged = [];

        foreach ($this->registrar->definitions() as $key => $definition) {
            if ($this->isVisible($definition)) {
                $merged[$key] = $definition->default;
            }
        }

        foreach (Setting::query()->whereNull('company_id')->get(['key', 'value']) as $row) {
            if (array_key_exists($row->key, $merged)) {
                $merged[$row->key] = $row->value;
            }
        }

        if ($companyId !== null) {
            foreach (Setting::query()->where('company_id', $companyId)->get(['key', 'value']) as $row) {
                if (array_key_exists($row->key, $merged)) {
                    $merged[$row->key] = $row->value;
                }
            }
        }

        return $merged;
    }

    public function set(string $key, mixed $value, ?int $companyId = null): void
    {
        $definition = $this->registrar->definitions()[$key] ?? null;

        if ($definition === null || ! $this->isVisible($definition)) {
            // Invisible means invisible: a gated-off key behaves exactly like an unknown
            // one — the caller cannot distinguish "never registered" from "registered by
            // a module disabled for this tenant" (CLAUDE.md §13 risk #1).
            throw UnknownSettingException::forKey($key);
        }

        $valid = match ($definition->type) {
            'string' => is_string($value),
            'int' => is_int($value),
            'float' => is_float($value) || is_int($value), // an int is an acceptable float
            'bool' => is_bool($value),
            'array' => is_array($value),
            default => true,
        };

        if (! $valid) {
            throw InvalidSettingValueException::forKey($key, $definition->type);
        }

        Setting::query()->updateOrCreate(
            ['company_id' => $companyId, 'key' => $key],
            ['value' => $value],
        );
    }

    public function forget(string $key, ?int $companyId = null): void
    {
        Setting::query()->where('company_id', $companyId)->where('key', $key)->delete();
    }

    private function isVisible(SettingDefinition $definition): bool
    {
        return $definition->module === null || $this->modules->isEnabledForCurrentTenant($definition->module);
    }
}
