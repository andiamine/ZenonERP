<?php

namespace App\Foundation\Tenancy\Bootstrappers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Facade;
use Stancl\Tenancy\Contracts\TenancyBootstrapper;
use Stancl\Tenancy\Contracts\Tenant;

/**
 * The DB session driver and session guards capture connection/store INSTANCES:
 * SessionManager memoizes the DatabaseSessionHandler (bound to the connection object
 * current at build time) and AuthManager memoizes guards holding that session store.
 * Across tenant transitions in one process (tests, queue workers, future Octane) those
 * go stale — acme's handler would serve beta. Drop them on every transition; they
 * lazily rebuild against the CURRENT default connection on next use.
 *
 * Gotcha: forgetGuards() also wipes actingAs()/login state when tenancy transitions
 * MID-request (e.g. signup provisioning a tenant inside a central request). Auth
 * middleware runs before controllers, so gating is unaffected — but never read
 * auth()->user() after a tenancy transition inside central request handling.
 */
final class SessionAuthTenancyBootstrapper implements TenancyBootstrapper
{
    public function __construct(private readonly Application $app) {}

    public function bootstrap(Tenant $tenant): void
    {
        $this->forgetStatefulInstances();
    }

    public function revert(): void
    {
        $this->forgetStatefulInstances();
    }

    private function forgetStatefulInstances(): void
    {
        $this->app->make('session')->forgetDrivers();
        $this->app->forgetInstance('session.store');
        $this->app->make('auth')->forgetGuards();

        Facade::clearResolvedInstance('session');
        Facade::clearResolvedInstance('session.store');
        Facade::clearResolvedInstance('auth');
        Facade::clearResolvedInstance('auth.driver');
    }
}
