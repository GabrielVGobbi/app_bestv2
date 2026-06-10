<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'status' => $this->status,
            'format' => $this->format,
            'model_class' => $this->model_class,
            'file_name' => $this->file_name,
            'file_size' => $this->file_size,
            'total_records' => $this->total_records,
            'processed_records' => $this->processed_records,
            'error_message' => $this->when(
                $this->status === 'failed',
                $this->error_message
            ),
            'download_url' => $this->when(
                $this->status === 'completed' && ! $this->isExpired(),
                fn () => route('exports.download', $this->uuid)
            ),
            'expires_at' => $this->expires_at,
            'created_at' => $this->created_at,
            'completed_at' => $this->completed_at,
        ];
    }
}
