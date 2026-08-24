<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class NavigationModulesTest extends TestCase
{
    public function test_header_modules_are_ordered_comercial_gestao_financeiro_admin_dev(): void
    {
        $this->assertSame(
            ['comercial', 'gestao', 'financeiro', 'admin', 'desenvolvimento'],
            collect(config('navigation.modules'))->pluck('slug')->all(),
        );
    }

    public function test_comercial_sidebar_groups_cadastros_and_operacao(): void
    {
        $comercial = collect(config('navigation.modules'))->firstWhere('slug', 'comercial');

        $this->assertTrue($comercial['default']);
        $this->assertSame('Dashboard', $comercial['items'][0]['label']);

        $cadastros = collect($comercial['items'])->firstWhere('slug', 'comercial_cadastros_section');
        $operacao = collect($comercial['items'])->firstWhere('slug', 'comercial_operacao_section');

        $this->assertSame(
            ['Clientes', 'Playlists', 'Serviços', 'Vendas'],
            collect($cadastros['items'])->sortBy('order')->pluck('label')->values()->all(),
        );
        $this->assertSame(
            ['Fila de playlists', 'TOPs'],
            collect($operacao['items'])->sortBy('order')->pluck('label')->values()->all(),
        );
    }

    public function test_gestao_sidebar_contains_suppliers_companies_and_music_catalog(): void
    {
        $gestao = collect(config('navigation.modules'))->firstWhere('slug', 'gestao');
        $cadastros = collect($gestao['items'])->firstWhere('slug', 'gestao_cadastros_section');

        $this->assertFalse($gestao['default']);
        $this->assertSame(
            ['Fornecedores', 'Empresas', 'Catálogo musical'],
            collect($cadastros['items'])->sortBy('order')->pluck('label')->values()->all(),
        );
    }
}
