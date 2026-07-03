<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\FinancialCategory;

use App\Modules\Finance\Models\FinancialCategory;
use Illuminate\Validation\Validator;

class UpdateFinancialCategoryRequest extends StoreFinancialCategoryRequest
{
    public function authorize(): bool
    {
        $category = $this->route('financial_category');

        return $category instanceof FinancialCategory
            && ($this->user()?->can('update', $category) ?? false);
    }

    protected function validateParent(Validator $validator): void
    {
        parent::validateParent($validator);

        $category = $this->route('financial_category');

        if (! $category instanceof FinancialCategory) {
            return;
        }

        $parentId = $this->integer('parent_id');

        if ($parentId === 0) {
            return;
        }

        if ($parentId === (int) $category->id) {
            $validator->errors()->add('parent_id', 'A categoria financeira nao pode ser pai dela mesma.');

            return;
        }

        $parent = FinancialCategory::query()->find($parentId);

        if ($parent && $parent->isDescendantOf($category)) {
            $validator->errors()->add('parent_id', 'A categoria pai nao pode ser descendente da categoria financeira.');
        }
    }

    protected function validateUniqueCodeAndSlug(Validator $validator): void
    {
        $category = $this->route('financial_category');

        if (! $category instanceof FinancialCategory) {
            parent::validateUniqueCodeAndSlug($validator);

            return;
        }

        $companyId = $this->input('company_id');
        $type = $this->string('type')->value();
        $slug = blank($this->input('slug')) ? str($this->string('name')->value())->slug()->value() : str($this->string('slug')->value())->slug()->value();

        $duplicateSlug = FinancialCategory::query()
            ->whereKeyNot($category->id)
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
            ->whereKeyNot($category->id)
            ->where('type', $type)
            ->where('code', $code)
            ->when($companyId === null, fn ($query) => $query->whereNull('company_id'), fn ($query) => $query->where('company_id', $companyId))
            ->exists();

        if ($duplicateCode) {
            $validator->errors()->add('code', 'Ja existe uma categoria financeira com este codigo.');
        }
    }
}
