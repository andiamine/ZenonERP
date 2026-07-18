<?php

/*
 * Activates the §2 boundary rule with Phase 5's real modules: cross-module PHP
 * references are only legal through the target module's Contracts\ namespace, and the
 * host app (App\, including App\Foundation) may reach into a module ONLY via its
 * Contracts\ namespace too. app/Foundation must not reference any module at all — the
 * CompanyResolver port + config('zenon.company_model'/'kernel_module') string
 * indirection exist specifically so Foundation stays module-free.
 */

$modules = ['Core', 'Sequence', 'Audit'];

foreach ($modules as $from) {
    foreach ($modules as $to) {
        if ($from === $to) {
            continue;
        }

        // Trailing backslash: pest-arch's ignoring() is a raw str_starts_with prefix
        // match — without it, a hypothetical sibling class named e.g.
        // "Modules\{$to}\ContractsSomething" would also be (wrongly) excluded.
        arch("Modules\\{$from} only reaches Modules\\{$to} through its Contracts namespace")
            ->expect("Modules\\{$from}")
            ->not->toUse("Modules\\{$to}")
            ->ignoring("Modules\\{$to}\\Contracts\\");
    }
}

arch('App\Foundation is module-free — the CompanyResolver port + config indirection exist for this')
    ->expect('App\Foundation')
    ->not->toUse('Modules');

arch('App reaches modules only through their Contracts namespace')
    ->expect('App')
    ->not->toUse('Modules')
    ->ignoring([
        'Modules\Core\Contracts\\',
        'Modules\Sequence\Contracts\\',
        'Modules\Audit\Contracts\\',
    ]);
