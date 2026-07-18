<?php

namespace Modules\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanyRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => [
                'sometimes', 'string', 'alpha_dash', 'max:50',
                Rule::unique('companies', 'code')->ignore($this->route('company')),
            ],
            'currency_code' => ['sometimes', 'string', 'size:3', Rule::exists('currencies', 'code')],
            'legal_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'country_code' => ['sometimes', 'nullable', 'string', 'size:2'],
            'timezone' => ['sometimes', 'nullable', 'string', 'max:64'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
