<?php

namespace Modules\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCurrencyRequest extends FormRequest
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
            'code' => [
                'sometimes', 'string', 'size:3',
                Rule::unique('currencies', 'code')->ignore($this->route('currency')),
            ],
            'name' => ['sometimes', 'string', 'max:255'],
            'symbol' => ['sometimes', 'nullable', 'string', 'max:10'],
            'decimal_places' => ['sometimes', 'integer', 'between:0,6'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
