<?php

declare(strict_types=1);

namespace App\Modules\Backup\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DatabaseExportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'status' => $this->status,
            'export_type' => $this->export_type,
            'tables' => $this->tables,
            'file_name' => $this->file_name,
            'file_size' => $this->file_size,
            'error_message' => $this->when(
                $this->status === 'failed',
                $this->error_message
            ),
            'download_url' => $this->when(
                $this->status === 'completed' && ! $this->isExpired(),
                fn () => route('admin.dev.backups.database-exports.download', $this->uuid)
            ),
            'expires_at' => $this->expires_at,
            'created_at' => $this->created_at,
            'completed_at' => $this->completed_at,
        ];
    }
}
