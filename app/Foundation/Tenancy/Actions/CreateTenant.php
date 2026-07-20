<?php

namespace App\Foundation\Tenancy\Actions;

use App\Foundation\DeploymentMode;
use App\Models\Tenant;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * The single provisioning path for new tenants — used by the central signup API,
 * the zenon:tenant:create command, and tests alike. Standalone mode has exactly one
 * tenant, provisioned by the installer wizard (Phase 8 Task 4) — this path refuses
 * outright rather than silently accepting a second tenant.
 */
final class CreateTenant
{
    /**
     * Creates the tenant + subdomain and synchronously provisions its database
     * (TenantCreated → CreateDatabase → MigrateDatabase pipeline).
     *
     * @throws ValidationException
     * @throws LogicException standalone mode manages tenant creation via the installer
     */
    public function handle(string $subdomain, ?string $name = null): Tenant
    {
        if (DeploymentMode::isStandalone()) {
            throw new LogicException('Tenant creation is managed by the installer in standalone mode.');
        }

        Validator::make(
            ['subdomain' => $subdomain],
            ['subdomain' => self::subdomainRules()],
        )->validate();

        $tenant = Tenant::create([
            'id' => $subdomain, // id = subdomain slug → DB zenon_tenant_{slug}
            'name' => $name ?? Str::headline($subdomain),
        ]);

        $tenant->domains()->create(['domain' => $subdomain]); // bare label — v3 subdomain identification

        return $tenant;
    }

    /**
     * @return list<mixed>
     */
    public static function subdomainRules(): array
    {
        /** @var list<string> $reserved */
        $reserved = config('zenon.reserved_subdomains', []);

        return [
            'required',
            'string',
            'min:2',
            'max:40', // keeps zenon_tenant_{slug} well under MySQL's 64-char DB name limit
            'lowercase',
            'regex:/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/', // DNS-label shape, no leading/trailing '-'
            Rule::notIn($reserved),
            Rule::unique('tenants', 'id'),
            Rule::unique('domains', 'domain'),
        ];
    }
}
