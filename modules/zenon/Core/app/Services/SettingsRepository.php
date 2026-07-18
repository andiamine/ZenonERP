<?php

namespace Modules\Core\Services;

use Modules\Core\Contracts\Settings\Exceptions\InvalidSettingValueException;
use Modules\Core\Contracts\Settings\Exceptions\UnknownSettingException;
use Modules\Core\Contracts\Settings\SettingsRegistrar;
use Modules\Core\Contracts\Settings\SettingsRepository as SettingsRepositoryContract;
use Modules\Core\Models\Setting;

/**
 * Reads resolve company-override ← tenant-level ← registered-default explicitly
 * (CLAUDE.md §9.1) — see Models\Setting's docblock for why this can't be a global scope.
 */
final class SettingsRepository implements SettingsRepositoryContract
{
    public function __construct(private readonly SettingsRegistrar $registrar) {}

    public function get(string $key, ?int $companyId = null): mixed
    {
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

        return $this->registrar->definitions()[$key]->default ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(?int $companyId = null): array
    {
        $merged = [];

        foreach ($this->registrar->definitions() as $key => $definition) {
            $merged[$key] = $definition->default;
        }

        foreach (Setting::query()->whereNull('company_id')->get(['key', 'value']) as $row) {
            $merged[$row->key] = $row->value;
        }

        if ($companyId !== null) {
            foreach (Setting::query()->where('company_id', $companyId)->get(['key', 'value']) as $row) {
                $merged[$row->key] = $row->value;
            }
        }

        return $merged;
    }

    public function set(string $key, mixed $value, ?int $companyId = null): void
    {
        $definition = $this->registrar->definitions()[$key] ?? null;

        if ($definition === null) {
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
}
