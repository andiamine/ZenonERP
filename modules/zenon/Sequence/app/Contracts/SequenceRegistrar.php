<?php

namespace Modules\Sequence\Contracts;

/**
 * In-memory registry of sequence definitions (CLAUDE.md §9.2, REG binding). Consumer
 * modules call define() in their provider's boot(): every module's register() completes
 * before any boot() runs, so registration ordering is always safe. Persistence of
 * counters is Models\Sequence's job — this only holds the declared shapes.
 */
interface SequenceRegistrar
{
    public function define(SequenceDefinition $definition): void;

    /**
     * @return array<string, SequenceDefinition> keyed by code
     */
    public function all(): array;
}
