<?php

use App\Foundation\Modules\Events\ModuleInstalled;
use App\Foundation\Modules\Exceptions\ModuleNotFoundException;
use App\Foundation\Modules\Models\InstalledModule;
use Illuminate\Support\Facades\Event;
use Modules\Dummy\DummyModule;

beforeEach(function () {
    DummyModule::$log = [];
});

it('installs a module: central row, statuses file, lifecycle, event', function () {
    Event::fake([ModuleInstalled::class]);

    installModule('dummy');

    $row = InstalledModule::query()->where('alias', 'dummy')->first();

    expect($row)->not->toBeNull()
        ->and($row->name)->toBe('Dummy')
        ->and($row->version)->toBe('1.0.0')
        ->and($row->core)->toBeFalse();

    $statuses = json_decode((string) file_get_contents(base_path((string) env('MODULES_STATUSES_FILE'))), true);
    expect($statuses['Dummy'] ?? null)->toBeTrue();

    expect(DummyModule::$log)->toContain('installed');

    Event::assertDispatched(ModuleInstalled::class, fn (ModuleInstalled $e) => $e->alias === 'dummy' && $e->version === '1.0.0');
});

it('is idempotent on repeated install', function () {
    installModule('dummy');
    installModule('dummy');

    expect(InstalledModule::query()->where('alias', 'dummy')->count())->toBe(1);
});

it('auto-installs dependencies first, in topological order', function () {
    Event::fake([ModuleInstalled::class]);

    installModule('dummydep');

    expect(InstalledModule::query()->pluck('alias')->all())->toContain('dummy', 'dummydep');

    $dispatchOrder = Event::dispatched(ModuleInstalled::class)->map(fn (array $args) => $args[0]->alias)->all();
    expect($dispatchOrder)->toBe(['dummy', 'dummydep']);
});

it('throws for an unknown alias', function () {
    expect(fn () => installModule('nope'))->toThrow(ModuleNotFoundException::class);
});

it('exposes install via zenon:module:install', function () {
    $this->artisan('zenon:module:install', ['alias' => 'dummy'])->assertSuccessful();
    $this->artisan('zenon:module:install', ['alias' => 'nope'])->assertFailed();
});
