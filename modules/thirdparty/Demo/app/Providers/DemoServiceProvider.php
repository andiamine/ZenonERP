<?php

namespace Modules\Demo\Providers;

use App\Foundation\Modules\ModuleServiceProvider;
use Modules\Core\Contracts\Events\CompanyDeleted;
use Modules\Core\Contracts\Hooks\CompanyApiResponse;
use Modules\Core\Contracts\Hooks\CompanyDeleting;
use Modules\Demo\Hooks\AddCompanyInsights;
use Modules\Demo\Hooks\VetoProtectedCompanyDeletion;
use Modules\Demo\Listeners\RecordCompanyDeletion;

class DemoServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Demo';

    protected string $nameLower = 'demo';

    public function boot(): void
    {
        parent::boot();

        // Phase 7 extension proof (§6, §12 row 7): registration is platform-wide and
        // unconditional — per-tenant enablement gates at filter()/dispatch time only.
        $this->extend()
            ->filter(CompanyApiResponse::class, AddCompanyInsights::class)
            ->filter(CompanyDeleting::class, VetoProtectedCompanyDeletion::class, priority: 10)
            ->listen(CompanyDeleted::class, RecordCompanyDeletion::class);
    }
}
