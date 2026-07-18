<?php

namespace Modules\Sequence\Contracts;

/**
 * A module-registered sequence: its code, mask and reset/scope behaviour (CLAUDE.md §9.2).
 * Registered via SequenceRegistrar::define() in a consumer module's provider boot(). The
 * first next($code) materialises a `sequences` row copying these defaults; an
 * unregistered code still works, falling back to the `{seq:5}`/never bare defaults.
 */
final readonly class SequenceDefinition
{
    public function __construct(
        public string $code,
        public string $mask,
        public string $resetPeriod = 'never',
        public bool $perCompany = false,
        public bool $gapless = true,
        public ?string $label = null,
    ) {}
}
