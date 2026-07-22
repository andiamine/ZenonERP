<?php

namespace App\Http\Requests\Installer;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Payload for POST /install/api/tenant (CLAUDE.md §7 Phase 8 Task 6). Only the tenant's
 * display name is collected here — the tenant database name is read back from the just-
 * written .env (TENANT_DB_DATABASE, already probed successfully in the database step)
 * rather than re-collected, so there is exactly one place a mismatch between "what was
 * probed" and "what gets used" could occur, and it can't (see InstallerController::tenant()).
 */
class TenantStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
        ];
    }
}
