<?php

namespace App\Http\Requests;

use App\Concerns\Exportable;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $modelClass = $this->input('model_class');
        $allowedModels = config('exports.allowed_models', []);
        if (! is_string($modelClass) || ! is_array($allowedModels) || ! in_array($modelClass, $allowedModels, true)) {
            return true;
        }

        $actor = $this->user();
        if (! $actor instanceof User || ! is_subclass_of($modelClass, Model::class)
            || ! in_array(Exportable::class, class_uses_recursive($modelClass), true)
            || ! method_exists($modelClass, 'authorizedExportQuery')) {
            return false;
        }

        $modelClass::authorizedExportQuery($actor);

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $allowedModels = config('exports.allowed_models', []);
        $allowedModels = is_array($allowedModels) ? $allowedModels : [];
        $modelClass = $this->input('model_class');
        $columnKeys = [];
        if (is_string($modelClass) && in_array($modelClass, $allowedModels, true)
            && is_subclass_of($modelClass, Model::class)
            && in_array(Exportable::class, class_uses_recursive($modelClass), true)
            && method_exists($modelClass, 'getExportableColumns')) {
            $columnKeys = array_keys($modelClass::getExportableColumns());
        }

        return [
            'model_class' => ['required', 'string', Rule::in($allowedModels)],
            'format' => ['required', 'string', Rule::in(['csv', 'xlsx', 'tsv'])],
            'columns' => ['nullable', 'array', 'list'],
            'columns.*' => ['string', 'distinct:strict', Rule::in($columnKeys)],
            'filters' => ['nullable', 'array'],
            'search' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'string', 'max:100'],
            'sort_direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
        ];
    }
}
