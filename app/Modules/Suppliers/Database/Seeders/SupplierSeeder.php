<?php

declare(strict_types=1);

namespace App\Modules\Suppliers\Database\Seeders;

use App\Modules\Companies\Database\Seeders\CompanySeeder;
use App\Modules\Companies\Models\Company;
use App\Modules\Suppliers\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(CompanySeeder::class);

        Company::query()
            ->orderBy('id')
            ->get()
            ->each(function (Company $company): void {
                foreach ($this->suppliers() as $supplierData) {
                    $supplier = Supplier::query()
                        ->withTrashed()
                        ->updateOrCreate(
                            [
                                'company_id' => $company->id,
                                'document' => $supplierData['document'],
                            ],
                            [
                                'name' => $supplierData['name'],
                                'company_name' => $supplierData['company_name'],
                                'email' => $supplierData['email'],
                                'phone' => $supplierData['phone'],
                                'notes' => $supplierData['notes'],
                                'status' => 'active',
                                'metadata' => [
                                    'segment' => $supplierData['segment'],
                                    'seed_key' => $supplierData['document'],
                                ],
                            ],
                        );

                    if ($supplier->trashed()) {
                        $supplier->restore();
                    }
                }
            });
    }

    /**
     * @return array<int, array{
     *     name: string,
     *     company_name: string,
     *     document: string,
     *     email: string,
     *     phone: ?string,
     *     notes: string,
     *     segment: string
     * }>
     */
    private function suppliers(): array
    {
        return [
            [
                'name' => 'Meta Ads',
                'company_name' => 'Meta Ads',
                'document' => 'BP-SUP-META-ADS',
                'email' => 'financeiro@meta-ads.example',
                'phone' => null,
                'notes' => 'Fornecedor base para custos de midia paga.',
                'segment' => 'paid_media',
            ],
            [
                'name' => 'Google Ads',
                'company_name' => 'Google Ads',
                'document' => 'BP-SUP-GOOGLE-ADS',
                'email' => 'financeiro@google-ads.example',
                'phone' => null,
                'notes' => 'Fornecedor base para custos de midia paga.',
                'segment' => 'paid_media',
            ],
            [
                'name' => 'TikTok Ads',
                'company_name' => 'TikTok Ads',
                'document' => 'BP-SUP-TIKTOK-ADS',
                'email' => 'financeiro@tiktok-ads.example',
                'phone' => null,
                'notes' => 'Fornecedor base para custos de midia paga.',
                'segment' => 'paid_media',
            ],
            [
                'name' => 'Spotify',
                'company_name' => 'Spotify',
                'document' => 'BP-SUP-SPOTIFY',
                'email' => 'financeiro@spotify.example',
                'phone' => null,
                'notes' => 'Fornecedor base para operacoes de musica e plataformas.',
                'segment' => 'platform',
            ],
            [
                'name' => 'Banco C6',
                'company_name' => 'Banco C6',
                'document' => 'BP-SUP-C6-BANK',
                'email' => 'financeiro@c6-bank.example',
                'phone' => null,
                'notes' => 'Fornecedor base para tarifas bancarias e servicos financeiros.',
                'segment' => 'banking',
            ],
            [
                'name' => 'Contabilidade',
                'company_name' => 'Contabilidade',
                'document' => 'BP-SUP-ACCOUNTING',
                'email' => 'financeiro@contabilidade.example',
                'phone' => null,
                'notes' => 'Fornecedor base para despesas contabeis e administrativas.',
                'segment' => 'administrative',
            ],
        ];
    }
}
