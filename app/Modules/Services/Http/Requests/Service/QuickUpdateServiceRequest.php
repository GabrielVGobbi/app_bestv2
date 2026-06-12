<?php

declare(strict_types=1);

namespace App\Modules\Services\Http\Requests\Service;

use App\Modules\Services\Models\Service;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class QuickUpdateServiceRequest extends FormRequest
{
    /** @var array<int, string> */
    private const ALLOWED_FIELDS = ['name'];

    public function authorize(): bool
    {
        $service = $this->route('service');

        return $service instanceof Service
            && ($this->user()?->can('update', $service) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'field' => ['required', 'string', Rule::in(self::ALLOWED_FIELDS)],
            'value' => ['required'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $unexpectedFields = collect($this->all())
                    ->keys()
                    ->diff(['field', 'value']);

                if ($unexpectedFields->isNotEmpty()) {
                    $validator->errors()->add('field', 'A atualizacao rapida aceita apenas os campos field e value.');
                }

                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $this->validateName($validator);
            },
        ];
    }

    /**
     * @return array{name: string}
     */
    public function updatePayload(): array
    {
        return ['name' => trim((string) $this->input('value'))];
    }

    private function validateName(Validator $validator): void
    {
        $value = $this->input('value');

        if (! is_string($value) || trim($value) === '') {
            $validator->errors()->add('value', 'O nome do servico e obrigatorio.');

            return;
        }

        if (mb_strlen(trim($value)) < 3) {
            $validator->errors()->add('value', 'O nome do servico deve ter pelo menos 3 caracteres.');

            return;
        }

        if (mb_strlen(trim($value)) > 255) {
            $validator->errors()->add('value', 'O nome do servico nao pode ter mais que 255 caracteres.');
        }
    }
}
