<?php

declare(strict_types=1);

namespace App\Modules\SeederGenerator\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SeederGeneratorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('useDeveloperTools');
    }

    public function rules(): array
    {
        if ($this->routeIs('admin.dev.seeder-generator.run')) {
            return ['class' => ['required', 'string', 'max:128', 'regex:/\AGenerated[A-Za-z0-9]+Seeder\z/']];
        }

        return [
            'table' => ['required', 'string', 'max:64', 'regex:/\A[A-Za-z_][A-Za-z0-9_]*\z/'],
            'mode' => ['required', 'in:data,factory'],
            'limit' => ['integer', 'min:1', 'max:1000'],
            'truncate' => ['boolean'],
            'idempotent' => ['boolean'],
            'unique_column' => ['nullable', 'string', 'max:255'],
            'seeder_name' => ['required', 'string', 'max:64', 'regex:/\A[A-Za-z][A-Za-z0-9_]*\z/'],
        ];
    }
}
