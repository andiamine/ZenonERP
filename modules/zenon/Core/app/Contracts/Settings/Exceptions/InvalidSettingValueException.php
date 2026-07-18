<?php

namespace Modules\Core\Contracts\Settings\Exceptions;

use InvalidArgumentException;

/** Thrown by SettingsRepository::set() when the value doesn't match the definition's type. */
class InvalidSettingValueException extends InvalidArgumentException
{
    public static function forKey(string $key, string $expectedType): self
    {
        return new self(sprintf('Value for setting [%s] must be of type [%s].', $key, $expectedType));
    }
}
