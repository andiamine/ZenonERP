<?php

namespace Modules\DummyDep\Providers;

use App\Foundation\Modules\ModuleServiceProvider;
use Modules\Dummy\Contracts\Events\DummyItemConfirmed;
use Modules\Dummy\Contracts\Hooks\DummyItemConfirming;
use Modules\Dummy\Contracts\Hooks\DummyItemsApiResponse;
use Modules\DummyDep\Hooks\AddDummyComputedField;
use Modules\DummyDep\Hooks\VetoWhenNameForbidden;
use Modules\DummyDep\Listeners\RecordDummyConfirmation;

class DummyDepServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'DummyDep';

    protected string $nameLower = 'dummydep';

    public function boot(): void
    {
        parent::boot();

        // Phase 6 acceptance wiring (§6, §12 row 6): registration is platform-wide and
        // unconditional — per-tenant enablement gates at filter()/dispatch time only.
        $this->extend()
            ->filter(DummyItemsApiResponse::class, AddDummyComputedField::class)
            ->filter(DummyItemConfirming::class, VetoWhenNameForbidden::class, priority: 10)
            ->listen(DummyItemConfirmed::class, RecordDummyConfirmation::class);
    }
}
