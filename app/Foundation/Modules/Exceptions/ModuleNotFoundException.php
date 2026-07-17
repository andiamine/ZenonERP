<?php

namespace App\Foundation\Modules\Exceptions;

use RuntimeException;

/** No discovered module on disk carries the given alias. */
class ModuleNotFoundException extends RuntimeException
{
    public static function forAlias(string $alias): self
    {
        return new self(sprintf('No module with alias [%s] was found in any module path.', $alias));
    }
}
