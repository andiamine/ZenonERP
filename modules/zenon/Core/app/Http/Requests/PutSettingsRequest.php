<?php

namespace Modules\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PutSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Keys are validated against the settings registry inside PutSettings, not here —
     * the registry is assembled at runtime from every module provider's boot()
     * (CLAUDE.md §9.1), so it cannot be known statically at request-validation time.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'values' => ['required', 'array'],
        ];
    }
}
