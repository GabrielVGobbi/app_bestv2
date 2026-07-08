<?php

declare(strict_types=1);

namespace App\Modules\Finance\Enums;

enum FinancialAccountType: string
{
    case BankAccount = 'bank_account';
    case Cash = 'cash';
    case Wallet = 'wallet';
    case PaymentGateway = 'payment_gateway';
}
