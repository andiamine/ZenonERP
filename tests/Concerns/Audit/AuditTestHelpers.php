<?php

namespace Tests\Concerns\Audit;

use App\Models\Tenant;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shared Audit-suite test helpers, class-based + PSR-4 autoloaded on purpose. Sequence's
 * equivalent (bootSequenceTenant) is a plain function declared inside one arbitrary test
 * file — it's only visible to sibling test files because Pest loads every Feature test
 * into the same PHP process, so it works by load-order coincidence, not by design (fragile:
 * rename/reorder the file and every other file silently loses the helper at runtime, not
 * at static-analysis time). Putting it on a real class sidesteps that: every Audit test
 * file resolves `AuditTestHelpers::bootAuditTenant()` deterministically via autoload,
 * regardless of which test file Pest happens to load first.
 *
 * Lives under tests/Concerns, NOT tests/Fixtures: it calls Pest's global test helpers
 * (createTenant/installModule/enableModule, declared in tests/Pest.php, never loaded by
 * PHPStan), and tests/Fixtures IS one of Larastan's `paths` (real fixture MODELS like
 * AuditProbe belong there and analyse cleanly; a class calling Pest-only globals does not).
 */
final class AuditTestHelpers
{
    /** Boots a tenant with zenon/core + zenon/audit installed and enabled. */
    public static function bootAuditTenant(string $subdomain = 'acme'): Tenant
    {
        $tenant = createTenant($subdomain);
        installModule('audit'); // auto-installs core (manifest "requires")
        enableModule('audit', $tenant); // auto-enables core first, in topo order

        return $tenant;
    }

    /** Throwaway table for the AuditProbe fixture model — created inside tenant context. */
    public static function createAuditProbesTable(): void
    {
        Schema::create('audit_probes', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('password')->nullable();
        });
    }
}
