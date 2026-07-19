<?php

namespace Modules\DummyDep\Listeners;

use Modules\Dummy\Contracts\Events\DummyItemConfirmed;

/**
 * Cross-module listener (§6), registered via the Extend API so TenantGatedListener
 * wraps it. Records executions in process-static state so tests can assert both that
 * it ran (module enabled) and that it did NOT (module disabled) — reset() between tests.
 */
final class RecordDummyConfirmation
{
    /** @var list<string> */
    public static array $confirmed = [];

    public function handle(DummyItemConfirmed $event): void
    {
        self::$confirmed[] = $event->name;
    }

    public static function reset(): void
    {
        self::$confirmed = [];
    }
}
