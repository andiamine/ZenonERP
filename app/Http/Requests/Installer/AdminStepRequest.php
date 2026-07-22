<?php

namespace App\Http\Requests\Installer;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Payload for POST /install/api/admin (CLAUDE.md §7 Phase 8 Task 6).
 */
class AdminStepRequest extends FormRequest
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
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
        ];
    }
}
