<?php

declare(strict_types=1);

namespace Tests\Unit\Finance;

use App\Modules\Finance\Support\MoneyStringParser;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MoneyStringParserTest extends TestCase
{
    #[DataProvider('validMoneyValues')]
    public function test_parses_money_strings_without_floating_point(string $value, int $expected): void
    {
        $this->assertSame($expected, (new MoneyStringParser)->toCents($value));
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function validMoneyValues(): array
    {
        return [
            'integer' => ['1234', 123400],
            'dot decimal' => ['100.50', 10050],
            'comma decimal' => ['100,5', 10050],
            'brazilian grouping' => ['1.234,56', 123456],
            'international grouping' => ['1,234.56', 123456],
            'grouping only dot' => ['1.234', 123400],
            'grouping only comma' => ['1,234', 123400],
            'currency and spaces' => ['R$ 1.234,56', 123456],
            'negative sign' => ['-4.50', -450],
            'negative parentheses' => ['(4,50)', -450],
            'leading decimal' => [',50', 50],
            'zero' => ['0,00', 0],
        ];
    }

    #[DataProvider('invalidMoneyValues')]
    public function test_rejects_ambiguous_or_invalid_precision(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new MoneyStringParser)->toCents($value);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidMoneyValues(): array
    {
        return [
            'empty' => [''],
            'missing digits' => ['R$'],
            'too many decimals' => ['10,1234'],
            'invalid grouping' => ['1,23,45'],
            'double sign' => ['--10'],
            'duplicate negative' => ['(-10)'],
        ];
    }
}
