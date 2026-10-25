<?php

declare(strict_types=1);

namespace App\Modules\CrudGenerator\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CrudGeneratorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('useDeveloperTools') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'model' => ['required', 'string', 'max:80', 'regex:/\A[A-Za-z][A-Za-z0-9_]*\z/'],
            'target' => ['nullable', 'string', 'in:app,module'],
            'module' => ['nullable', 'string', 'max:80', 'regex:/\A[A-Za-z][A-Za-z0-9_]*\z/'],
            'icon' => ['nullable', 'string', 'max:80'],
            'with_export' => ['boolean'],
            'with_observer' => ['boolean'],
            'with_policy' => ['boolean'],
            'with_factory' => ['boolean'],
            'with_seeder' => ['boolean'],
            'with_tests' => ['boolean'],
            'register_provider' => ['boolean'],
            'force' => ['boolean'],
            'fields' => ['required', 'array', 'min:1'],
            'fields.*.name' => ['required', 'string', 'regex:/\A[a-z][a-z0-9_]*\z/'],
            'fields.*.type' => ['required', 'string', 'in:string,text,integer,bigInteger,decimal,float,double,boolean,date,dateTime,timestamp,json'],
            'fields.*.nullable' => ['boolean'],
            'fields.*.unique' => ['boolean'],
            'fields.*.searchable' => ['boolean'],
            'fields.*.filterable' => ['boolean'],
            'fields.*.sortable' => ['boolean'],
            'fields.*.exportable' => ['boolean'],
            'fields.*.validation' => ['nullable', 'array'],
            'fields.*.validation.*' => ['string', 'max:120'],
        ];
    }
}
