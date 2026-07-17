<?php

namespace App\Http\Requests\Api\V1;

use App\Foundation\Tenancy\Actions\CreateTenant;
use Illuminate\Foundation\Http\FormRequest;

class SignupRequest extends FormRequest
{
    public function authorize(): bool
    {
        // TODO(Phase 3): central-guard auth + signup abuse hardening.
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'subdomain' => CreateTenant::subdomainRules(),
            'name' => ['nullable', 'string', 'max:100'],
        ];
    }
}
