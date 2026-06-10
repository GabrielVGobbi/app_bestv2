<?php

namespace Tests\Unit\Export;

use App\Models\User;
use PHPUnit\Framework\TestCase;

class ExportableTraitTest extends TestCase
{
    public function test_get_exportable_columns_returns_defined_columns(): void
    {
        $columns = User::getExportableColumns();

        $this->assertIsArray($columns);
        $this->assertArrayHasKey('name', $columns);
        $this->assertArrayHasKey('email', $columns);
        $this->assertEquals('Nome', $columns['name']);
        $this->assertEquals('Email', $columns['email']);
    }

    public function test_resolve_export_columns_returns_all_when_empty(): void
    {
        $resolved = User::resolveExportColumns([]);

        $this->assertEquals(User::getExportableColumns(), $resolved);
    }

    public function test_resolve_export_columns_returns_subset_for_valid_keys(): void
    {
        $resolved = User::resolveExportColumns(['name', 'email']);

        $this->assertCount(2, $resolved);
        $this->assertArrayHasKey('name', $resolved);
        $this->assertArrayHasKey('email', $resolved);
        $this->assertArrayNotHasKey('created_at', $resolved);
    }

    public function test_resolve_export_columns_ignores_invalid_keys(): void
    {
        $resolved = User::resolveExportColumns(['name', 'invalid_column', 'another_invalid']);

        $this->assertCount(1, $resolved);
        $this->assertArrayHasKey('name', $resolved);
    }

    public function test_resolve_export_columns_returns_empty_for_all_invalid_keys(): void
    {
        $resolved = User::resolveExportColumns(['invalid_one', 'invalid_two']);

        $this->assertEmpty($resolved);
    }
}
