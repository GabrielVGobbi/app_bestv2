<?php

namespace App\Http\Requests\Admin;

use App\Modules\Slack\Enums\SlackAlertLevel;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateSlackSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('manageIntegrationSettings') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'log_level' => ['required', 'string', Rule::enum(SlackAlertLevel::class)],
            'webhook_url' => ['nullable', 'string', 'url', 'max:512'],
            'reset_webhook' => ['sometimes', 'boolean'],
        ];
    }

    /** @return list<Closure> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $webhook = $this->input('webhook_url');
            if ($this->boolean('reset_webhook') && is_string($webhook) && trim($webhook) !== '') {
                $validator->errors()->add('webhook_url', 'Não é possível substituir e restaurar o webhook na mesma operação.');
            }
        }];
    }
}
