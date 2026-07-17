<?php

namespace App\Foundation\Modules\Exceptions;

use RuntimeException;

class ModuleStateException extends RuntimeException
{
    public static function cannotDisableCore(string $alias): self
    {
        return new self(sprintf('Module [%s] is a core module and cannot be disabled.', $alias));
    }

    public static function purgeRequiresDisabled(string $alias, string $tenantId): self
    {
        return new self(sprintf(
            'Module [%s] must be disabled for tenant [%s] before it can be purged.',
            $alias, $tenantId,
        ));
    }

    public static function notEnabledForTenant(string $alias, string $tenantId): self
    {
        return new self(sprintf('Module [%s] is not enabled for tenant [%s].', $alias, $tenantId));
    }

    /**
     * @param  list<string>  $tenantIds
     */
    public static function uninstallWithEnabledTenants(string $alias, array $tenantIds): self
    {
        return new self(sprintf(
            'Module [%s] is still enabled for tenant(s) [%s] and cannot be uninstalled.',
            $alias, implode(', ', $tenantIds),
        ));
    }

    public static function platformIncompatible(string $alias, string $constraint, string $platformVersion): self
    {
        return new self(sprintf(
            'Module [%s] declares platform compatibility [%s], but this platform is version %s.',
            $alias, $constraint, $platformVersion,
        ));
    }
}
