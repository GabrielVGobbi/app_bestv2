<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\FinancialCategory;

use App\Modules\Finance\Enums\FinancialCategoryType;
use App\Modules\Finance\Models\FinancialCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreFinancialCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', FinancialCategory::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_id' => ['nullable', 'integer', Rule::exists('companies', 'id')],
            'parent_id' => ['nullable', 'integer', Rule::exists('financial_categories', 'id')],
            'code' => ['nullable', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['required', 'string', Rule::enum(FinancialCategoryType::class)],
            'color' => ['nullable', 'string', 'max:32'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
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

                $this->validateParent($validator);
                $this->validateUniqueCodeAndSlug($validator);
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null): array
    {
        $validated = parent::validated($key, $default);
        $validated['slug'] = blank($validated['slug'] ?? null)
            ? Str::slug((string) $validated['name'])
            : Str::slug((string) $validated['slug']);
        $validated['is_active'] = $this->boolean('is_active', true);
        $validated['sort_order'] = (int) ($validated['sort_order'] ?? 0);

        return $validated;
    }

    protected function validateParent(Validator $validator): void
    {
        $parentId = $this->integer('parent_id');

        if ($parentId === 0) {
            return;
        }

        $parent = FinancialCategory::query()->find($parentId);

        if (! $parent) {
            return;
        }

        if (($parent->type?->value ?? $parent->type) !== $this->string('type')->value()) {
            $validator->errors()->add('parent_id', 'A categoria pai deve ter o mesmo tipo.');
        }

        $companyId = $this->input('company_id');

        if ($parent->company_id !== null && (int) $companyId !== (int) $parent->company_id) {
            $validator->errors()->add('parent_id', 'A categoria pai deve pertencer a mesma empresa ou ser global.');
        }
    }

    protected function validateUniqueCodeAndSlug(Validator $validator): void
    {
        $companyId = $this->input('company_id');
        $type = $this->string('type')->value();
        $slug = blank($this->input('slug')) ? Str::slug($this->string('name')->value()) : Str::slug($this->string('slug')->value());

        $duplicateSlug = FinancialCategory::query()
            ->where('type', $type)
            ->where('slug', $slug)
            ->when($companyId === null, fn ($query) => $query->whereNull('company_id'), fn ($query) => $query->where('company_id', $companyId))
            ->exists();

        if ($duplicateSlug) {
            $validator->errors()->add('slug', 'Ja existe uma categoria financeira com esta chave.');
        }

        $code = $this->string('code')->value();

        if ($code === '') {
            return;
        }

        $duplicateCode = FinancialCategory::query()
            ->where('type', $type)
            ->where('code', $code)
            ->when($companyId === null, fn ($query) => $query->whereNull('company_id'), fn ($query) => $query->where('company_id', $companyId))
            ->exists();

        if ($duplicateCode) {
            $validator->errors()->add('code', 'Ja existe uma categoria financeira com este codigo.');
        }
    }
}
