<?php

namespace App\Http\Requests\Installer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Payload for POST /install/api/database (CLAUDE.md §7 Phase 8 Task 6). `driver`
 * defaults to 'mysql' server-side (Actions\WriteEnvironment) — the only real production
 * shape, a pre-created Plesk/cPanel database — but this validates 'mariadb'/'sqlite'
 * too: sqlite is how InstallerFlowTest exercises the whole wizard without a real MySQL
 * server, so validation must not hard-require mysql-only fields. Tenant host/port/
 * username/password are all optional: WriteEnvironment defaults each to the matching
 * central value when blank (the common cPanel/Plesk case of one DB user granted on both
 * pre-created databases) — only `tenant.database` is required.
 */
class DatabaseStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        // No auth exists yet at this point in a fresh install — the installer's only
        // gate is EnsureInstallerAvailable (standalone + not-yet-installed + same-origin).
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'app_name' => ['required', 'string', 'max:100'],
            'app_url' => ['required', 'string', 'max:255', 'regex:/^https?:\/\/.+/'],
            'driver' => ['nullable', 'string', Rule::in(['mysql', 'mariadb', 'sqlite'])],

            'central' => ['required', 'array'],
            'central.database' => ['required', 'string', 'max:255'],
            'central.host' => ['nullable', 'string', 'max:255'],
            'central.port' => ['nullable', 'string', 'max:10'],
            'central.username' => ['nullable', 'string', 'max:255'],
            'central.password' => ['nullable', 'string', 'max:255'],

            'tenant' => ['required', 'array'],
            'tenant.database' => ['required', 'string', 'max:255'],
            'tenant.host' => ['nullable', 'string', 'max:255'],
            'tenant.port' => ['nullable', 'string', 'max:10'],
            'tenant.username' => ['nullable', 'string', 'max:255'],
            'tenant.password' => ['nullable', 'string', 'max:255'],
        ];
    }
}
