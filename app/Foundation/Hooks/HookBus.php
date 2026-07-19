<?php

namespace App\Foundation\Hooks;

use App\Foundation\Modules\ModuleRegistry;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

/**
 * The typed filter pipeline (§6): payload CLASSES are the keys — no magic strings.
 * Filters are container-resolved invokables run in ascending priority order (ties keep
 * registration order); filters whose module is not enabled for the current tenant are
 * skipped — one of the three enablement gates (§13 risk #1), consulting ONLY
 * ModuleRegistry like the other two (route middleware, TenantGatedListener).
 *
 * Bound as a SINGLETON, not scoped: registrations happen in provider boot() (once per
 * process) — a scoped bus would be flushed at each request/job lifecycle and lose them
 * while providers don't re-boot. The registration set is process-stable; all per-tenant
 * variability lives in the gating check at filter() time. For the same reason the
 * container-scoped ModuleRegistry is resolved lazily per filter() call, never captured.
 */
final class HookBus
{
    /** @var array<class-string, list<array{filter: class-string, module: string, priority: int}>> */
    private array $filters = [];

    /** @var array<class-string, bool> payload classes whose filter list is already priority-sorted */
    private array $sorted = [];

    public function __construct(private readonly Container $container) {}

    /**
     * @param  class-string  $payloadClass
     * @param  class-string  $filterClass  invokable: __invoke($payload), mutates in place
     */
    public function register(string $payloadClass, string $filterClass, string $module, int $priority = 100): void
    {
        $this->filters[$payloadClass][] = ['filter' => $filterClass, 'module' => $module, 'priority' => $priority];
        unset($this->sorted[$payloadClass]);
    }

    /**
     * Runs the payload through every enabled filter and returns it (mutated in place;
     * filter return values are ignored). An ActionVetoedException thrown by a filter
     * propagates — later filters are skipped by design.
     *
     * @template TPayload of object
     *
     * @param  TPayload  $payload
     * @return TPayload
     */
    public function filter(object $payload): object
    {
        if (! isset($this->filters[$payload::class])) {
            return $payload;
        }

        if (! isset($this->sorted[$payload::class])) {
            usort($this->filters[$payload::class], fn (array $a, array $b): int => $a['priority'] <=> $b['priority']);
            $this->sorted[$payload::class] = true;
        }

        $registry = $this->container->make(ModuleRegistry::class);

        foreach ($this->filters[$payload::class] as $entry) {
            if (! $registry->isEnabledForCurrentTenant($entry['module'])) {
                continue;
            }

            $filter = $this->container->make($entry['filter']);

            if (! is_callable($filter)) {
                throw new InvalidArgumentException(sprintf('Hook filter [%s] must be invokable (define __invoke).', $entry['filter']));
            }

            $filter($payload);
        }

        return $payload;
    }
}
