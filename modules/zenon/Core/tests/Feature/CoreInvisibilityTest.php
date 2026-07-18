<?php

/*
 * Standing invisibility invariant (CLAUDE.md §6/§11) for zenon/core specifically: even
 * though core is `core: true` (auto-enabled for every NORMALLY-provisioned tenant), a
 * tenant that existed before Core was installed platform-wide must NOT retroactively
 * gain it — ProvisionTenantModules only auto-enables core:true modules that are
 * INSTALLED at tenant-creation time (see ProvisionTenantModulesTest).
 */

it('is behaviorally invisible for a tenant created before zenon/core was installed', function () {
    $tenant = createTenant('acme'); // created BEFORE installModule('core') below
    installModule('core');

    assertModuleInvisibleFor($tenant, '/api/v1/core/companies');
    assertModuleInvisibleFor($tenant, '/api/v1/core/settings');
});
