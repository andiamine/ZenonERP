<?php

namespace Modules\Core\Contracts\Settings;

use Modules\Core\Contracts\Settings\Exceptions\InvalidSettingValueException;
use Modules\Core\Contracts\Settings\Exceptions\UnknownSettingException;

interface SettingsRepository extends SettingsReader
{
    /**
     * @throws UnknownSettingException when $key was never registered via SettingsRegistrar
     * @throws InvalidSettingValueException when $value doesn't match the definition's type
     */
    public function set(string $key, mixed $value, ?int $companyId = null): void;

    public function forget(string $key, ?int $companyId = null): void;
}
