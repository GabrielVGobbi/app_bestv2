<?php

declare(strict_types=1);

namespace App\Modules\Companies\Database\Seeders;

use App\Modules\Companies\Models\Company;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->companies() as $companyData) {
            $company = Company::query()
                ->withTrashed()
                ->updateOrCreate(
                    ['cnpj' => $companyData['cnpj']],
                    [
                        'name' => $companyData['name'],
                        'nickname' => $companyData['nickname'],
                        'fiscal_metadata' => $companyData['fiscal_metadata'],
                    ],
                );

            if ($company->trashed()) {
                $company->restore();
            }
        }
    }

    /**
     * @return array<int, array{
     *     name: string,
     *     nickname: string,
     *     cnpj: string,
     *     fiscal_metadata: array<string, mixed>
     * }>
     */
    private function companies(): array
    {
        return [
            [
                'name' => 'Best Play',
                'nickname' => 'Best Play',
                'cnpj' => '36569969000125',
                'fiscal_metadata' => [
                    'cpfCnpj' => '36569969000125',
                    'razaoSocial' => 'BEST PLAY MARKETING DIRETO LTDA',
                    'nomeFantasia' => 'Best Play',
                    'inscricaoMunicipal' => '4971244',
                    'endereco' => [
                        'logradouro' => 'Alameda Rio Negro',
                        'numero' => '1084',
                        'codigoCidade' => '3505708',
                        'cep' => '06454000',
                    ],
                    'telefone' => [
                        'ddd' => '48',
                        'numero' => '988241320',
                    ],
                    'description' => 'Servico de marketing digital realizado durante o mes de ? (sem especializacao tecnica).|Pago em ?|BEST PLAY MARKETING DIRETO|CNPJ: 36.569.969/0001-25 (CHAVE PIX)|Banco: 336 - Banco C6 S.A.|Ag.: 0001CC: 2602517-5',
                ],
            ],
            [
                'name' => 'Best Branch',
                'nickname' => 'Best Branch',
                'cnpj' => '43358044000109',
                'fiscal_metadata' => [
                    'cpfCnpj' => '43358044000109',
                    'razaoSocial' => 'BEST BRANCH MARKETING DIRETO',
                    'nomeFantasia' => 'Best Branch',
                    'inscricaoMunicipal' => '4AG0638',
                    'endereco' => [
                        'logradouro' => 'Alameda Rio Negro',
                        'numero' => '1084',
                        'codigoCidade' => '3505708',
                        'cep' => '06454000',
                    ],
                    'telefone' => [
                        'ddd' => '48',
                        'numero' => '988241320',
                    ],
                    'description' => 'Servico de marketing digital realizado durante o mes de ? (sem especializacao tecnica).|Pago em ?|BEST BRANCH MARKETING DIRETO|CNPJ: 43.358.044/0001-09|Banco: 336 - Banco C6 S.A.|Ag.: 0001CC: 10782242-3',
                ],
            ],
            [
                'name' => 'Bean Music',
                'nickname' => 'Bean Music',
                'cnpj' => '48120660000132',
                'fiscal_metadata' => [
                    'cpfCnpj' => '48120660000132',
                    'razaoSocial' => 'BEAN MUSIC PROMOTION MARKETING DIRETO',
                    'nomeFantasia' => 'BEAN MUSIC',
                    'inscricaoMunicipal' => '5AQ4397',
                    'endereco' => [
                        'logradouro' => 'Alameda Rio Negro',
                        'numero' => '1030',
                        'codigoCidade' => '3505708',
                        'cep' => '06454000',
                    ],
                    'telefone' => [
                        'ddd' => '11',
                        'numero' => '996964644',
                    ],
                    'description' => 'Servico de marketing digital realizado durante o mes de ? (sem especializacao tecnica).|Pago em ?|BEAN MUSIC PROMOTION MARKETING DIRETO|CNPJ: 48.120.660/0001-32|Banco: 336 - Banco C6 S.A.|Ag.: 0001CC: 21872558-2',
                ],
            ],
        ];
    }
}
