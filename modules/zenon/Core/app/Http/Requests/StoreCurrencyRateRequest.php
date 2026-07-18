<?php

namespace Modules\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCurrencyRateRequest extends FormRequest
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
            'rate' => ['required', 'numeric', 'gt:0'],
            'valid_from' => ['required', 'date'],
            'company_id' => ['nullable', 'integer', Rule::exists('companies', 'id')],
        ];
    }
}
