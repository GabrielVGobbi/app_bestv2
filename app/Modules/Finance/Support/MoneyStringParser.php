<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use InvalidArgumentException;

class MoneyStringParser
{
    public function toCents(string $value): int
    {
        $normalized = trim($value);

        if ($normalized === '') {
            throw new InvalidArgumentException('Money value cannot be empty.');
        }

        $negativeByParentheses = str_starts_with($normalized, '(') && str_ends_with($normalized, ')');

        if ($negativeByParentheses) {
            $normalized = substr($normalized, 1, -1);
        }

        $normalized = preg_replace('/[\p{L}\p{Sc}\s]/u', '', $normalized) ?? '';
        $negativeBySign = str_starts_with($normalized, '-');

        if ($negativeBySign || str_starts_with($normalized, '+')) {
            $normalized = substr($normalized, 1);
        }

        if ($negativeByParentheses && $negativeBySign) {
            throw new InvalidArgumentException('Money value has duplicate negative markers.');
        }

        if (str_contains($normalized, '+') || str_contains($normalized, '-')) {
            throw new InvalidArgumentException('Money value has an invalid sign.');
        }

        if (str_starts_with($normalized, '.') || str_starts_with($normalized, ',')) {
            $normalized = '0'.$normalized;
        }

        if (! preg_match('/^\d[\d.,]*$/', $normalized)) {
            throw new InvalidArgumentException('Money value contains invalid characters.');
        }

        [$whole, $fraction] = $this->splitDecimalParts($normalized);
        $wholeDigits = ltrim(str_replace([',', '.'], '', $whole), '0');
        $wholeDigits = $wholeDigits === '' ? '0' : $wholeDigits;
        $fractionDigits = str_pad($fraction, 2, '0');
        $cents = ltrim($wholeDigits.$fractionDigits, '0');
        $cents = $cents === '' ? '0' : $cents;

        $maximum = (string) PHP_INT_MAX;

        if (strlen($cents) > strlen($maximum)
            || (strlen($cents) === strlen($maximum) && strcmp($cents, $maximum) > 0)) {
            throw new InvalidArgumentException('Money value exceeds the supported range.');
        }

        $amount = (int) $cents;

        return ($negativeByParentheses || $negativeBySign) && $amount !== 0 ? -$amount : $amount;
    }

    /**
     * @return array{string, string}
     */
    private function splitDecimalParts(string $value): array
    {
        $commaPosition = strrpos($value, ',');
        $dotPosition = strrpos($value, '.');

        if ($commaPosition !== false && $dotPosition !== false) {
            $decimalPosition = max($commaPosition, $dotPosition);

            return $this->splitUsingDecimalPosition($value, $decimalPosition);
        }

        $separator = $commaPosition !== false ? ',' : ($dotPosition !== false ? '.' : null);

        if ($separator === null) {
            return [$value, ''];
        }

        $segments = explode($separator, $value);
        $lastSegment = end($segments);

        if ($lastSegment === false || $lastSegment === '') {
            throw new InvalidArgumentException('Money value has an empty decimal part.');
        }

        if (count($segments) === 2 && strlen($lastSegment) === 3) {
            $this->assertGrouping($segments);

            return [$value, ''];
        }

        if (count($segments) > 2 && strlen($lastSegment) === 3) {
            $this->assertGrouping($segments);

            return [$value, ''];
        }

        return $this->splitUsingDecimalPosition($value, (int) strrpos($value, $separator));
    }

    /**
     * @return array{string, string}
     */
    private function splitUsingDecimalPosition(string $value, int $decimalPosition): array
    {
        $whole = substr($value, 0, $decimalPosition);
        $fraction = substr($value, $decimalPosition + 1);

        if ($whole === '' || $fraction === '' || strlen($fraction) > 2) {
            throw new InvalidArgumentException('Money value must have at most two decimal places.');
        }

        if (! ctype_digit($fraction)) {
            throw new InvalidArgumentException('Money decimal part is invalid.');
        }

        $groupedWhole = preg_split('/[.,]/', $whole);

        if ($groupedWhole === false || in_array('', $groupedWhole, true)) {
            throw new InvalidArgumentException('Money grouping is invalid.');
        }

        if (count($groupedWhole) > 1) {
            $this->assertGrouping($groupedWhole);
        }

        return [$whole, $fraction];
    }

    /**
     * @param  list<string>  $segments
     */
    private function assertGrouping(array $segments): void
    {
        if ($segments === [] || strlen($segments[0]) < 1 || strlen($segments[0]) > 3) {
            throw new InvalidArgumentException('Money grouping is invalid.');
        }

        foreach (array_slice($segments, 1) as $segment) {
            if (strlen($segment) !== 3 || ! ctype_digit($segment)) {
                throw new InvalidArgumentException('Money grouping is invalid.');
            }
        }
    }
}
