<?php

declare(strict_types=1);

namespace App\Modules\Backup\Http\Requests;

use App\Modules\Backup\Models\DatabaseExport;
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
        return [
            'export_type' => ['required', Rule::in([
                DatabaseExport::TYPE_SCHEMA,
                DatabaseExport::TYPE_DATA,
                DatabaseExport::TYPE_FULL,
            ])],
            'tables' => ['nullable', 'array'],
            'tables.*' => ['string', Rule::in($this->getAvailableTables())],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function getAvailableTables(): array
    {
        $driver = config('database.default');

        $tables = match ($driver) {
            'sqlite' => DB::select("SELECT name FROM sqlite_master WHERE type='table'"),
            'pgsql' => DB::select("SELECT tablename as name FROM pg_tables WHERE schemaname = 'public'"),
            default => DB::select('SHOW TABLES'),
        };

        return array_map(fn ($row) => array_values((array) $row)[0], $tables);
    }
}
