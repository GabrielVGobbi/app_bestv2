<?php

declare(strict_types=1);

namespace App\Modules\Companies\Observers;

use App\Modules\Companies\Models\Company;

class CompanyObserver
{
    public function created(Company $company): void
    {
        //
    }

    public function updated(Company $company): void
    {
        //
    }

    public function deleted(Company $company): void
    {
        //
    }
}
