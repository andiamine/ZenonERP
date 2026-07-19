<?php

namespace App\Foundation\Hooks;

use Illuminate\Contracts\Events\Dispatcher;

/**
 * Per-module registration facade (§6) — the ONLY sanctioned way a module wires
 * cross-module hooks and listeners (§13 risk #1). Handed out by the base
 * ModuleServiceProvider::extend() pre-stamped with the module's alias, so everything
 * registered through it is automatically tenant-gated; a module cannot register
 * ungated. Call from the provider's boot():
 *
 *   $this->extend()
 *       ->filter(SalesOrderConfirming::class, EnforceCreditLimit::class, priority: 10)
 *       ->listen(SalesOrderConfirmed::class, RecordCreditExposure::class);
 */
final class Extend
{
    public function __construct(
        private readonly HookBus $bus,
        private readonly Dispatcher $events,
        private readonly string $module,
    ) {}

    /**
     * @param  class-string  $payloadClass
     * @param  class-string  $filterClass
     */
    public function filter(string $payloadClass, string $filterClass, int $priority = 100): self
    {
        $this->bus->register($payloadClass, $filterClass, $this->module, $priority);

        return $this;
    }

    /**
     * @param  class-string  $eventClass
     * @param  class-string  $listenerClass
     */
    public function listen(string $eventClass, string $listenerClass): self
    {
        $gated = new TenantGatedListener($listenerClass, $this->module);

        // First-class callable of __invoke — the Dispatcher contract wants a Closure.
        $this->events->listen($eventClass, $gated(...));

        return $this;
    }
}
