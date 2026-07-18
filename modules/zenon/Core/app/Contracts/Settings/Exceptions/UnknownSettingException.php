<?php

namespace Modules\Core\Contracts\Settings\Exceptions;

use InvalidArgumentException;

/** Thrown by SettingsRepository::set() for a key no module ever registered. */
class UnknownSettingException extends InvalidArgumentException
{
    public static function forKey(string $key): self
    {
        return new self(sprintf('Setting [%s] is not registered.', $key));
    }
}
