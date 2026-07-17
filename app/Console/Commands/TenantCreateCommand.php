<?php

namespace App\Console\Commands;

use App\Foundation\Tenancy\Actions\CreateTenant;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class TenantCreateCommand extends Command
{
    protected $signature = 'zenon:tenant:create
        {subdomain : Tenant subdomain, also used as tenant id and DB suffix (e.g. "acme")}
        {--name= : Display name (defaults to a headline-cased subdomain)}';

    protected $description = 'Create a tenant and synchronously provision its database';

    public function handle(CreateTenant $createTenant): int
    {
        $subdomain = (string) $this->argument('subdomain');
        $name = $this->option('name');

        try {
            $tenant = $createTenant->handle($subdomain, is_string($name) ? $name : null);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->components->error($message);
                }
            }

            return self::FAILURE;
        }

        /** @var list<string> $centralDomains */
        $centralDomains = config('tenancy.central_domains', []);
        $baseDomain = collect($centralDomains)->first(
            fn (string $domain) => substr_count($domain, '.') === 1,
        );

        $this->components->info(sprintf('Tenant [%s] created.', $tenant->getTenantKey()));
        $this->components->twoColumnDetail('Name', (string) $tenant->name);
        $this->components->twoColumnDetail('Domain', $baseDomain !== null ? "{$subdomain}.{$baseDomain}" : $subdomain);
        $this->components->twoColumnDetail('Database', $tenant->database()->getName());

        return self::SUCCESS;
    }
}
