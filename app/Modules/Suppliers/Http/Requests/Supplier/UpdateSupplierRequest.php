<?php

declare(strict_types=1);

namespace App\Modules\Suppliers\Http\Requests\Supplier;

use App\Modules\Suppliers\Models\Supplier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        $supplier = $this->route('supplier');

        return $supplier instanceof Supplier
            && ($this->user()?->can('update', $supplier) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Supplier|null $supplier */
        $supplier = $this->route('supplier');
        $companyId = $this->input('company_id', $supplier?->company_id);

        return [
            'company_id' => ['nullable', 'integer', Rule::exists('companies', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'document' => [
                'nullable',
                'string',
                'max:32',
                Rule::unique('suppliers', 'document')
                    ->where('company_id', $companyId)
                    ->ignore($supplier),
            ],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive', 'archived'])],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
