<?php

declare(strict_types=1);

namespace App\Modules\Timeline\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTimelineCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxLength = (int) config('timeline.comments.max_length', 10000);

        return [
            'body' => ['required', 'string', 'max:'.$maxLength],
            'body_format' => ['nullable', Rule::in(['plain', 'html'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('body') && is_string($this->input('body'))) {
            $this->merge(['body' => trim((string) $this->input('body'))]);
        }
    }
}
