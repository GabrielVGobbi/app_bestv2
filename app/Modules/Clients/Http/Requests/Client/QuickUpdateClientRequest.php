<?php

declare(strict_types=1);

namespace App\Modules\Clients\Http\Requests\Client;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class QuickUpdateClientRequest extends FormRequest
{
    /** @var array<int, string> */
    private const ALLOWED_FIELDS = ['name', 'responsible_user_id'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'field' => ['required', 'string', Rule::in(self::ALLOWED_FIELDS)],
            'value' => ['nullable'],
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
                    $validator->errors()->add('field', 'A atualização rápida aceita apenas os campos field e value.');
                }

                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                match ($this->input('field')) {
                    'name' => $this->validateName($validator),
                    'responsible_user_id' => $this->validateResponsibleUser($validator),
                    default => null,
                };
            },
        ];
    }

    /**
     * @return array{name?: string, responsible_user_id?: int|null}
     */
    public function updatePayload(): array
    {
        if ($this->input('field') === 'name') {
            return ['name' => trim((string) $this->input('value'))];
        }

        $value = $this->input('value');

        return [
            'responsible_user_id' => $value === null || $value === ''
                ? null
                : (int) $value,
        ];
    }

    private function validateName(Validator $validator): void
    {
        $value = $this->input('value');

        if (! is_string($value) || trim($value) === '') {
            $validator->errors()->add('value', 'O nome do cliente é obrigatório.');

            return;
        }

        if (mb_strlen(trim($value)) > 255) {
            $validator->errors()->add('value', 'O nome do cliente não pode ter mais que 255 caracteres.');
        }
    }

    private function validateResponsibleUser(Validator $validator): void
    {
        $value = $this->input('value');

        if ($value === null || $value === '') {
            return;
        }

        if (! filter_var($value, FILTER_VALIDATE_INT)) {
            $validator->errors()->add('value', 'O responsável interno selecionado é inválido.');

            return;
        }

        $isCommercialUser = User::query()
            ->whereKey((int) $value)
            ->withRole('commercial')
            ->exists();

        if (! $isCommercialUser) {
            $validator->errors()->add('value', 'O responsável interno deve ter a role Comercial.');
        }
    }
}
