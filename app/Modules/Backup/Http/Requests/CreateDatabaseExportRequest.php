<?php

declare(strict_types=1);

namespace App\Modules\Backup\Http\Requests;

use App\Supports\DatabaseDumpOptions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CreateDatabaseExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('developer') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();
        $types = DatabaseDumpOptions::supportedTypes($driver);

        return [
            'export_type' => ['required', Rule::in($types)],
            'tables' => ['nullable', 'array'],
            'tables.*' => ['bail', 'string', 'regex:'.DatabaseDumpOptions::tablePattern($driver),
                Rule::in($types === [] ? [] : DatabaseDumpOptions::tables($connection))],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'export_type.in' => DB::connection()->getDriverName() === 'sqlite'
                ? 'SQLite permite somente dump completo. Estrutura e dados separados não são suportados.'
                : 'O tipo de dump não é suportado pelo driver atual.',
            'tables.*.regex' => 'O nome da tabela não permite uma seleção literal segura neste driver.',
        ];
    }
}
