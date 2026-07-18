<?php

namespace App\Http\Requests\Api\V1;

use App\Foundation\Tenancy\Actions\CreateTenant;
use Illuminate\Foundation\Http\FormRequest;

class SignupRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authentication is the route's auth:central middleware (401 envelope); operator-level
        // authorization escalation (granular signup permissions) is a later phase.
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
