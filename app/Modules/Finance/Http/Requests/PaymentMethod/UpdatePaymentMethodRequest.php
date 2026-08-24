<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\PaymentMethod;

use App\Modules\Finance\Models\PaymentMethod;
use App\Modules\Finance\Support\FinancialCompanyAccess;

class UpdatePaymentMethodRequest extends StorePaymentMethodRequest
{
    public function authorize(FinancialCompanyAccess $companyAccess): bool
    {
        $paymentMethod = $this->route('payment_method');
        $user = $this->user();
        $companyId = $this->integer('company_id');

        return $paymentMethod instanceof PaymentMethod
            && $user !== null
            && $user->can('update', $paymentMethod)
            && ($companyId === 0 || $companyAccess->canAccess($user, $companyId));
    }

    protected function paymentMethodBeingUpdated(): ?PaymentMethod
    {
        $paymentMethod = $this->route('payment_method');

        return $paymentMethod instanceof PaymentMethod ? $paymentMethod : null;
    }
}
