<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Services\DevToolsService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class UpdateEnvRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('useDeveloperTools') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->isJson()) {
            $original = json_decode($this->getContent(), true);
        } else {
            parse_str($this->getContent(), $original);
        }

        $value = is_array($original) && array_key_exists('value', $original)
            ? $original['value']
            : ($_POST['value'] ?? $this->input('value'));

        if (is_string($value) && preg_match('/[\x00-\x1F\x7F]/', $value)) {
            throw ValidationException::withMessages(['value' => 'Caracteres de controle não são permitidos nas configurações operacionais.']);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $key = $this->input('key');

        return DevToolsService::environmentRules(is_string($key) ? $key : null);
    }
}
