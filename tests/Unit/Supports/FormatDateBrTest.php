<?php

declare(strict_types=1);

namespace Tests\Unit\Supports;

use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FormatDateBrTest extends TestCase
{
    #[DataProvider('dates')]
    public function test_format_date_br_returns_brazilian_date(mixed $value, string $expected): void
    {
        $this->assertSame($expected, format_date_br($value));
    }

    public function test_format_date_br_uses_fallback_for_invalid_values(): void
    {
        $this->assertSame('-', format_date_br(null));
        $this->assertSame('-', format_date_br(''));
        $this->assertSame('n/d', format_date_br('not-a-date', 'n/d'));
    }

    /**
     * @return array<string, array{0: mixed, 1: string}>
     */
    public static function dates(): array
    {
        return [
            'iso date' => ['2026-08-20', '20/08/2026'],
            'carbon' => [Carbon::parse('2026-08-20'), '20/08/2026'],
        ];
    }
}
