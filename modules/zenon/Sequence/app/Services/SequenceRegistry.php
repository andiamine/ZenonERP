<?php

namespace Modules\Sequence\Services;

use Modules\Sequence\Contracts\SequenceDefinition;
use Modules\Sequence\Contracts\SequenceRegistrar;

/**
 * In-memory sequence registry (CLAUDE.md §9.2). Bound scoped in SequenceServiceProvider —
 * every consumer module provider's boot() accumulates into the same instance for the
 * lifetime of the request/worker cycle.
 */
final class SequenceRegistry implements SequenceRegistrar
{
    /** @var array<string, SequenceDefinition> */
    private array $definitions = [];

    public function define(SequenceDefinition $definition): void
    {
        // Last write wins — a later-booted module deliberately overriding an earlier
        // definition (e.g. widening the mask) is not an error case.
        $this->definitions[$definition->code] = $definition;
    }

    /**
     * @return array<string, SequenceDefinition>
     */
    public function all(): array
    {
        return $this->definitions;
    }
}
