<?php

namespace Modules\Sequence\Providers;

use App\Foundation\Modules\ModuleServiceProvider;
use Modules\Sequence\Console\SequenceStressCommand;
use Modules\Sequence\Contracts\SequenceGenerator;
use Modules\Sequence\Contracts\SequenceRegistrar;
use Modules\Sequence\Services\SequenceRegistry;
use Modules\Sequence\Services\SequenceService;

class SequenceServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Sequence';

    protected string $nameLower = 'sequence';

    public function register(): void
    {
        parent::register();

        // One registry per request/worker cycle — every consumer provider's boot()
        // define()s into the SAME instance (mirrors Core's SettingsRegistry).
        $this->app->scoped(SequenceRegistry::class);

        $this->app->bind(SequenceRegistrar::class, fn ($app) => $app->make(SequenceRegistry::class));

        $this->app->bind(SequenceGenerator::class, SequenceService::class);
    }

    public function boot(): void
    {
        parent::boot();

        // nwidart's registerCommands() only registers the (empty) $commands property;
        // module console classes are not auto-discovered, so register explicitly.
        $this->commands([SequenceStressCommand::class]);
    }
}
