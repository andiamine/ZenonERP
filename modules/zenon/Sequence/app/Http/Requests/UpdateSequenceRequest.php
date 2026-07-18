<?php

namespace Modules\Sequence\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSequenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * next_number is deliberately NOT editable here — the counter is only ever advanced
     * by the gapless allocator; letting an admin rewrite it would reintroduce gaps/dupes.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'mask' => [
                'sometimes', 'string', 'max:255',
                // A mask with no {seq} token can never produce a distinct number per call.
                static function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value) || ! str_contains($value, '{seq')) {
                        $fail('The mask must contain a {seq} counter token.');
                    }
                },
            ],
            'reset_period' => ['sometimes', Rule::in(['never', 'year', 'month'])],
        ];
    }
}
