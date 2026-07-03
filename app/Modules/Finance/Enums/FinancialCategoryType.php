<?php

declare(strict_types=1);

namespace App\Modules\Finance\Enums;

enum FinancialCategoryType: string
{
    case Income = 'income';
    case Expense = 'expense';
    case Transfer = 'transfer';
}
