<?php

declare(strict_types=1);

namespace App\Modules\Companies\Actions;

use App\Modules\Companies\Models\Company;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class SyncCompanyLogo
{
    public function handle(Company $company, ?UploadedFile $logo, bool $removeLogo = false): void
    {
        if ($logo === null && ! $removeLogo) {
            return;
        }

        $this->deleteCurrentLogo($company);

        if ($logo === null) {
            $company->forceFill([
                'logo_disk' => null,
                'logo_path' => null,
                'logo_updated_at' => null,
            ])->save();

            return;
        }

        $disk = 'public';
        $path = $logo->store("companies/{$company->uuid}/logos", $disk);

        $company->forceFill([
            'logo_disk' => $disk,
            'logo_path' => $path,
            'logo_updated_at' => now(),
        ])->save();
    }

    private function deleteCurrentLogo(Company $company): void
    {
        if (blank($company->logo_path)) {
            return;
        }

        Storage::disk($company->logo_disk ?: 'public')->delete($company->logo_path);
    }
}
