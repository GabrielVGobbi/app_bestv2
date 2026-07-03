<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\PaymentMethod;

use App\Modules\Finance\Models\PaymentMethod;

class UpdatePaymentMethodRequest extends StorePaymentMethodRequest
{
    public function authorize(): bool
    {
        $paymentMethod = $this->route('payment_method');

        return $paymentMethod instanceof PaymentMethod
            && ($this->user()?->can('update', $paymentMethod) ?? false);
    }

    protected function paymentMethodBeingUpdated(): ?PaymentMethod
    {
        $paymentMethod = $this->route('payment_method');

        return $paymentMethod instanceof PaymentMethod ? $paymentMethod : null;
    }
}
