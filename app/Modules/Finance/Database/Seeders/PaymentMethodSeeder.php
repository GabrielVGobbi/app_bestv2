<?php

declare(strict_types=1);

namespace App\Modules\Finance\Database\Seeders;

use App\Modules\Finance\Enums\PaymentMethodType;
use App\Modules\Finance\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        $methods = [
            ['code' => 'cash', 'name' => 'Dinheiro', 'type' => PaymentMethodType::Cash, 'requires_reference' => false],
            ['code' => 'pix', 'name' => 'Pix', 'type' => PaymentMethodType::Pix, 'requires_reference' => true],
            ['code' => 'bank_transfer', 'name' => 'Transferencia bancaria', 'type' => PaymentMethodType::BankTransfer, 'requires_reference' => true],
            ['code' => 'boleto', 'name' => 'Boleto', 'type' => PaymentMethodType::Boleto, 'requires_reference' => true],
            ['code' => 'credit_card', 'name' => 'Cartao de credito', 'type' => PaymentMethodType::CreditCard, 'requires_reference' => true],
            ['code' => 'debit_card', 'name' => 'Cartao de debito', 'type' => PaymentMethodType::DebitCard, 'requires_reference' => true],
            ['code' => 'other', 'name' => 'Outro', 'type' => PaymentMethodType::Other, 'requires_reference' => false],
        ];

        foreach ($methods as $index => $method) {
            PaymentMethod::query()->updateOrCreate(
                [
                    'company_id' => null,
                    'code' => $method['code'],
                ],
                [
                    'name' => $method['name'],
                    'type' => $method['type']->value,
                    'requires_reference' => $method['requires_reference'],
                    'is_active' => true,
                    'sort_order' => $index,
                    'metadata' => null,
                ],
            );
        }
    }
}
