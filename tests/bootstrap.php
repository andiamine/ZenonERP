<?php

use Tests\TestCase;

/*
 * Pre-creates the testing nwidart statuses file BEFORE the first app bootstrap; each
 * test resets it again in TestCase::setUp() because some tests (uninstall) mutate it.
 */

require __DIR__.'/../vendor/autoload.php';

TestCase::writeFixtureModuleStatuses();
