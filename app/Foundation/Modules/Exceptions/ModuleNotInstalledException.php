<?php

namespace App\Foundation\Modules\Exceptions;

use RuntimeException;

class ModuleNotInstalledException extends RuntimeException
{
    public static function forAlias(string $alias): self
    {
        return new self(sprintf('Module [%s] is not installed.', $alias));
    }
}
