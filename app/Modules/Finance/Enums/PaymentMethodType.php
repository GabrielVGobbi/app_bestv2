<?php

declare(strict_types=1);

namespace App\Modules\Finance\Enums;

enum PaymentMethodType: string
{
    case Cash = 'cash';
    case Pix = 'pix';
    case BankTransfer = 'bank_transfer';
    case Boleto = 'boleto';
    case CreditCard = 'credit_card';
    case DebitCard = 'debit_card';
    case Other = 'other';
}
