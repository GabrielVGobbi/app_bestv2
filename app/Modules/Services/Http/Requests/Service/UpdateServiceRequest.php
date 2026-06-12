<?php

declare(strict_types=1);

namespace App\Modules\Services\Http\Requests\Service;

use App\Modules\Services\Models\Service;

class UpdateServiceRequest extends StoreServiceRequest
{
    public function authorize(): bool
    {
        $service = $this->route('service');

        return $service instanceof Service
            && ($this->user()?->can('update', $service) ?? false);
    }
}
