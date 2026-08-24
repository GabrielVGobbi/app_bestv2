<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\PaymentMethod;

use App\Modules\Finance\Enums\PaymentMethodType;
use App\Modules\Finance\Models\PaymentMethod;
use App\Modules\Finance\Support\FinancialCompanyAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePaymentMethodRequest extends FormRequest
{
    public function authorize(FinancialCompanyAccess $companyAccess): bool
    {
        $user = $this->user();
        $companyId = $this->integer('company_id');

        return $user !== null
            && $user->can('create', PaymentMethod::class)
            && ($companyId === 0 || $companyAccess->canAccess($user, $companyId));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_id' => ['nullable', 'integer', Rule::exists('companies', 'id')],
            'code' => ['nullable', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::enum(PaymentMethodType::class)],
            'requires_reference' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $this->validateUniqueCode($validator);
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null): array
    {
        $validated = parent::validated($key, $default);
        $validated['code'] = blank($validated['code'] ?? null)
            ? Str::slug((string) $validated['name'], '_')
            : Str::slug((string) $validated['code'], '_');
        $validated['requires_reference'] = $this->boolean('requires_reference', false);
        $validated['is_active'] = $this->boolean('is_active', true);
        $validated['sort_order'] = (int) ($validated['sort_order'] ?? 0);
        $validated['metadata'] = $validated['metadata'] ?? null;

        return $validated;
    }

    protected function paymentMethodBeingUpdated(): ?PaymentMethod
    {
        return null;
    }

    private function validateUniqueCode(Validator $validator): void
    {
        $companyId = $this->input('company_id');
        $code = blank($this->input('code'))
            ? Str::slug($this->string('name')->value(), '_')
            : Str::slug($this->string('code')->value(), '_');

        $duplicate = PaymentMethod::query()
            ->where('code', $code)
            ->when(
                $companyId === null,
                fn ($query) => $query->whereNull('company_id'),
                fn ($query) => $query->where('company_id', $companyId)
            )
            ->when(
                $this->paymentMethodBeingUpdated() instanceof PaymentMethod,
                fn ($query) => $query->whereKeyNot($this->paymentMethodBeingUpdated()?->getKey())
            )
            ->exists();

        if ($duplicate) {
            $validator->errors()->add('code', 'Ja existe um metodo de pagamento com este codigo.');
        }
    }
}
