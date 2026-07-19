<?php

namespace Modules\Demo\Listeners;

use Illuminate\Support\Facades\Log;
use Modules\Core\Contracts\Events\CompanyDeleted;

/**
 * Cross-module listener (§6), registered via the Extend API so TenantGatedListener
 * wraps it. Records executions in process-static state so tests can assert both that
 * it ran (module enabled) and that it did NOT (module disabled) — reset() between
 * tests. Fixture twin: DummyDep's RecordDummyConfirmation.
 */
final class RecordCompanyDeletion
{
    /** @var list<array{id: int, code: string, name: string}> */
    public static array $deleted = [];

    public function handle(CompanyDeleted $event): void
    {
        self::$deleted[] = ['id' => $event->companyId, 'code' => $event->code, 'name' => $event->name];

        Log::info('demo: company deleted', [
            'id' => $event->companyId,
            'code' => $event->code,
            'name' => $event->name,
        ]);
    }

    public static function reset(): void
    {
        self::$deleted = [];
    }
}
