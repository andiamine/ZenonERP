<?php

namespace App\Foundation\Modules\Exceptions;

use RuntimeException;

class DependencyException extends RuntimeException
{
    /**
     * @param  list<string>  $path
     */
    public static function cycleDetected(array $path): self
    {
        return new self(sprintf('Module dependency cycle detected: %s.', implode(' -> ', $path)));
    }

    public static function missing(string $module, string $dependency): self
    {
        return new self(sprintf('Module [%s] requires [%s], which is not available.', $module, $dependency));
    }

    public static function unsatisfied(string $module, string $dependency, string $constraint, string $actual): self
    {
        return new self(sprintf(
            'Module [%s] requires [%s %s], but version %s is present.',
            $module, $dependency, $constraint, $actual,
        ));
    }

    /**
     * @param  list<string>  $dependents
     */
    public static function hasEnabledDependents(string $module, array $dependents): self
    {
        return new self(sprintf(
            'Module [%s] cannot be disabled: enabled module(s) [%s] depend on it. Use --cascade to disable them too.',
            $module, implode(', ', $dependents),
        ));
    }
}
