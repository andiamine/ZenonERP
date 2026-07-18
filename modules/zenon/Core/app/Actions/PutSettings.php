<?php

namespace Modules\Core\Actions;

use Modules\Core\Contracts\Settings\Exceptions\InvalidSettingValueException;
use Modules\Core\Contracts\Settings\Exceptions\UnknownSettingException;
use Modules\Core\Contracts\Settings\SettingsRepository as SettingsRepositoryContract;

/**
 * One call = one key. SettingsController iterates the request's `values` map and calls
 * this per key so it can attribute a thrown exception to the exact `values.<key>` field
 * of the §8 422 envelope — batching the whole map inside this Action would lose that
 * per-key association.
 */
final class PutSettings
{
    public function __construct(private readonly SettingsRepositoryContract $settings) {}

    /**
     * @throws UnknownSettingException
     * @throws InvalidSettingValueException
     */
    public function handle(string $key, mixed $value, ?int $companyId): void
    {
        $this->settings->set($key, $value, $companyId);
    }
}
