<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
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
}
