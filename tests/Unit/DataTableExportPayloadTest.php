<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class DataTableExportPayloadTest extends TestCase
{
    public function test_data_table_export_uses_structured_filter_payload(): void
    {
        $queryParams = file_get_contents(__DIR__.'/../../resources/js/components/data-table/core/query-params.ts');
        $toolbar = file_get_contents(__DIR__.'/../../resources/js/components/data-table/shell/data-table-toolbar.tsx');
        $dialog = file_get_contents(__DIR__.'/../../resources/js/components/data-table/shell/data-table-export-dialog.tsx');

        $this->assertIsString($queryParams);
        $this->assertIsString($toolbar);
        $this->assertIsString($dialog);

        $this->assertStringContainsString('export function filtersToApiPayload(', $queryParams);
        $this->assertStringContainsString('params[filter.id] = {', $queryParams);
        $this->assertStringContainsString('operator,', $queryParams);
        $this->assertStringContainsString('value: payloadValue,', $queryParams);

        $this->assertStringContainsString('exportFilterConfig', $toolbar);
        $this->assertStringContainsString('currentFilterConfig={exportFilterConfig}', $toolbar);

        $this->assertStringContainsString('currentFilterConfig?: Array<DataTableFilterConfig | DataTableQuickFilter>;', $dialog);
        $this->assertStringContainsString('filtersToApiPayload(currentFilters ?? {}, currentFilterConfig)', $dialog);
    }

    public function test_export_dialog_has_current_result_scope_and_summary(): void
    {
        $dialog = file_get_contents(__DIR__.'/../../resources/js/components/data-table/shell/data-table-export-dialog.tsx');

        $this->assertIsString($dialog);

        $this->assertMatchesRegularExpression("~const\\s+\\[exportScope,\\s*setExportScope\\]\\s*=\\s*useState<'current'\\s*\\|\\s*'all'>\\(\\s*'current',?\\s*\\);~", $dialog);
        $this->assertStringContainsString("exportScope === 'current'", $dialog);
        $this->assertStringContainsString('Resultado atual', $dialog);
        $this->assertStringContainsString('Todos os registros', $dialog);
        $this->assertStringContainsString('activeStateSummary', $dialog);
    }

    public function test_users_verification_quick_filter_declares_null_operators(): void
    {
        $usersIndex = file_get_contents(__DIR__.'/../../resources/js/pages/app/users/index.tsx');

        $this->assertIsString($usersIndex);

        $this->assertStringContainsString("{ label: 'Verificados', value: true, operator: 'not_null' }", $usersIndex);
        $this->assertStringContainsString("{ label: 'Não verificados', value: false, operator: 'null' }", $usersIndex);
    }
}
