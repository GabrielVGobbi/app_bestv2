<?php

namespace App\Http\Requests;

use App\Concerns\Searchable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;

class PaginationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'filter' => ['nullable', 'array'],
            'filter.*' => ['nullable'],
            'include' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort' => ['nullable', 'string', 'max:50'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
        ];
    }

    public function searchTerm(): ?string
    {
        return $this->string('search')->value() ?: null;
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return $this->array('filter');
    }

    /**
     * @return array<int, string>
     */
    public function includes(): array
    {
        return str($this->string('include')->value())
            ->explode(',')
            ->map(fn (string $include): string => trim($include))
            ->filter()
            ->values()
            ->all();
    }

    public function sortColumn(): ?string
    {
        return $this->string('sort')->value() ?: null;
    }

    public function sortDirection(): ?string
    {
        return $this->string('direction')->value() ?: null;
    }

    public function perPage(): int
    {
        return $this->integer('per_page', 15);
    }

    public function applyTo(Builder $query): Builder
    {
        if (! in_array(Searchable::class, class_uses_recursive($query->getModel()), true)) {
            return $query;
        }

        return $query
            ->search($this->searchTerm())
            ->apiFilters($this->filters())
            ->apiSort($this->sortColumn(), $this->sortDirection());
    }
}
