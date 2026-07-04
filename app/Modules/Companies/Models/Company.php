<?php

declare(strict_types=1);

namespace App\Modules\Companies\Models;

use App\Concerns\Exportable;
use App\Concerns\Searchable;
use App\Modules\Companies\Database\Factories\CompanyFactory;
use App\Supports\Traits\HasActivityLog;
use App\Supports\Traits\HasUuidIdentifier;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Fillable(['uuid', 'name', 'nickname', 'cnpj', 'fiscal_metadata', 'logo_disk', 'logo_path', 'logo_updated_at'])]
class Company extends Model
{
    use Exportable, HasActivityLog, HasFactory, HasUuidIdentifier, Searchable, SoftDeletes;

    /** @var array<string, string> */
    protected array $exportable = [
        'name' => 'Nome',
        'nickname' => 'Apelido',
        'cnpj' => 'CNPJ',
        'fiscal_metadata' => 'Metadados fiscais',
    ];

    /** @var array<int, string> */
    protected array $searchable = ['name', 'nickname', 'cnpj'];

    /** @var array<int, string> */
    protected array $filterable = ['uuid', 'name', 'nickname', 'cnpj', 'created_at', 'updated_at'];

    /** @var array<int, string> */
    protected array $sortable = ['id', 'uuid', 'name', 'nickname', 'cnpj', 'created_at', 'updated_at'];

    protected string $activityLogName = 'companies';

    protected string $defaultSortColumn = 'id';

    protected string $defaultSortDirection = 'desc';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fiscal_metadata' => 'array',
            'logo_updated_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function logoUrl(): ?string
    {
        if (blank($this->logo_path)) {
            return null;
        }

        return Storage::disk($this->logo_disk ?: 'public')->url($this->logo_path);
    }

    public function logoInitials(): string
    {
        $name = trim((string) ($this->nickname ?: $this->name));

        if ($name === '') {
            return 'EM';
        }

        $initials = '';
        $words = preg_split('/\s+/', $name) ?: [];

        if (count($words) === 1) {
            return Str::upper(Str::substr($words[0], 0, 2));
        }

        foreach ($words as $word) {
            if ($word === '') {
                continue;
            }

            $initials .= Str::substr($word, 0, 1);

            if (Str::length($initials) >= 2) {
                break;
            }
        }

        return Str::upper($initials !== '' ? $initials : Str::substr($name, 0, 2));
    }

    public function logoColor(): string
    {
        $palette = ['rose', 'amber', 'emerald', 'sky', 'violet', 'slate'];
        $source = (string) ($this->uuid ?: $this->name ?: $this->id);

        return $palette[abs(crc32($source)) % count($palette)];
    }

    protected static function newFactory(): Factory
    {
        return CompanyFactory::new();
    }
}
