<?php

namespace App\Foundation\Hooks;

use App\Foundation\Modules\ModuleRegistry;
use LogicException;

/**
 * Decorator the Extend API wraps around every cross-module event listener (§6): a
 * dispatch-time no-op when the listener's module is not enabled for the current tenant
 * — one of the three enablement gates (§13 risk #1), consulting ONLY ModuleRegistry
 * like the other two (route middleware, HookBus).
 *
 * ModuleRegistry and the listener resolve lazily at dispatch time, never at
 * registration: providers register listeners at boot, before tenancy initializes, and
 * the registry binding is container-scoped so a captured instance would go stale.
 *
 * v1 limitation (on record): the decorated listener always runs synchronously —
 * ShouldQueue on the inner listener is NOT honored. Queued cross-module listeners are
 * a later design; nothing in M1 needs them.
 */
final class TenantGatedListener
{
    /** @param  class-string  $listenerClass  must define handle($event) */
    public function __construct(
        private readonly string $listenerClass,
        private readonly string $module,
    ) {}

    public function __invoke(object $event): void
    {
        if (! app(ModuleRegistry::class)->isEnabledForCurrentTenant($this->module)) {
            return;
        }

        $listener = app($this->listenerClass);

        if (! is_object($listener) || ! method_exists($listener, 'handle')) {
            throw new LogicException(sprintf('Cross-module listener [%s] must define handle($event).', $this->listenerClass));
        }

        $listener->handle($event);
    }
}
