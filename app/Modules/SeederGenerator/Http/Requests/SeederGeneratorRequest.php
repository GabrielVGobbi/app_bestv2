<?php

declare(strict_types=1);

namespace App\Modules\SeederGenerator\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SeederGeneratorRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'table' => ['required', 'string'],
            'mode' => ['required', 'in:data,factory'],
            'limit' => ['integer', 'min:1', 'max:1000'],
            'truncate' => ['boolean'],
            'idempotent' => ['boolean'],
            'unique_column' => ['nullable', 'string'],
            'seeder_name' => ['required', 'string', 'regex:/^[A-Za-z][A-Za-z0-9_]*$/'],
        ];
    }
}
