<?php

namespace Modules\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // authorization is the route's permission: middleware (CLAUDE.md §2, no Policies)
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'alpha_dash', 'max:50', Rule::unique('companies', 'code')],
            'currency_code' => ['required', 'string', 'size:3', Rule::exists('currencies', 'code')],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
