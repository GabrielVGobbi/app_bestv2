<?php

declare(strict_types=1);

namespace App\Modules\Clients\Http\Requests\Client;

use App\Models\User;
use App\Modules\Clients\Models\Client;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreClientRequest extends FormRequest
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
        return [
            'company_id' => ['nullable', 'integer'],
            'responsible_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('clients', 'email')],
            'phone' => ['nullable', 'string', 'max:255', Rule::unique('clients', 'phone')],
            'document' => ['nullable', 'string', 'max:255', Rule::unique('clients', 'document')],
            'musical_genre' => ['nullable', 'string', 'max:255'],
            'is_agent' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
            'classification' => ['nullable', 'string', Rule::in(Client::CLASSIFICATIONS)],
            'source_id' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive', 'archived'])],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $responsibleUserId = $this->integer('responsible_user_id');

                if ($responsibleUserId === 0 || $validator->errors()->has('responsible_user_id')) {
                    return;
                }

                $isCommercialUser = User::query()
                    ->whereKey($responsibleUserId)
                    ->withRole('commercial')
                    ->exists();

                if (! $isCommercialUser) {
                    $validator->errors()->add('responsible_user_id', 'O responsável interno deve ter a role Comercial.');
                }
            },
        ];
    }
}
