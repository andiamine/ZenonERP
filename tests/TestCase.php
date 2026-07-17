<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * In production every HTTP request is its own process, so tenancy initialized by
     * the subdomain middleware dies with the request. In-process test requests would
     * otherwise leak that tenant context into the rest of the test — end it here to
     * model the real request boundary.
     *
     * @param  array<string, mixed>  $parameters
     * @param  array<string, mixed>  $cookies
     * @param  array<string, mixed>  $files
     * @param  array<string, mixed>  $server
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $response = parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);

        if (tenancy()->initialized) {
            tenancy()->end();
        }

        return $response;
    }

    protected function setUp(): void
    {
        // Must happen BEFORE the app boots: nwidart registers module providers from
        // this file during provider registration, and tests (e.g. uninstall) mutate it.
        self::writeFixtureModuleStatuses();

        parent::setUp();
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end(); // restore the central default connection before RefreshDatabase rolls back
        }

        parent::tearDown(); // closes sqlite connections first — required for unlink on Windows

        foreach (glob(database_path('zenon_tenant_*')) ?: [] as $file) {
            @unlink($file);
        }
    }

    /** Resets the testing nwidart statuses file to "all fixture modules active". */
    public static function writeFixtureModuleStatuses(): void
    {
        $statuses = __DIR__.'/../storage/framework/testing/modules_statuses.json';

        if (! is_dir(dirname($statuses))) {
            mkdir(dirname($statuses), 0777, true);
        }

        file_put_contents($statuses, json_encode([
            'Dummy' => true,     // activator keys = nwidart module NAMES, not aliases
            'DummyDep' => true,
            'DummyCore' => true,
        ], JSON_PRETTY_PRINT));
    }
}
