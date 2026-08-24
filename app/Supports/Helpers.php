<?php

use Illuminate\Contracts\Translation\Translator;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

if (! function_exists('to_boolean')) {

    /**
     * Convert to boolean
     *
     * @return bool
     */
    function to_boolean($booleable)
    {
        return filter_var($booleable, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }
}

/**
 * Makes translation fall back to specified value if definition does not exist
 *
 * @param  string  $key
 * @return array|Translator|null|string
 */
function __trans($key = '', ?array $replace = [], ?string $fallback = null, ?string $locale = 'pt-BR')
{
    $keyM = minusculo($key);
    if (Lang::has($keyM, $locale) && ! empty($keyM)) {
        if (gettype(trans($keyM, $replace, $locale)) == 'array') {
            return trans($keyM, $replace, $locale)[0];
        }

        return trans($keyM, $replace, $locale);
    }

    return ltrim($key);
}

function uuid()
{
    return (string) Str::uuid();
}

function slug($value, $separator = '_')
{
    return Str::slug(mb_strtolower($value, 'UTF-8'), $separator);
}

function valorToDec($valor)
{
    if (empty($valor)) {
        return 0;
    }

    $money = preg_replace('/[\x{00A0}\x{202F}\s]/u', '', $valor); // remove espaços invisíveis
    $money = str_replace(['R$', '%'], '', $money);

    if (strpos($money, ',') !== false && strpos($money, '.') !== false) {
        if (strrpos($money, ',') > strrpos($money, '.')) {
            $money = str_replace('.', '', $money); // remove milhar
            $money = str_replace(',', '.', $money); // decimal vira ponto
        } else {
            $money = str_replace(',', '', $money); // remove milhar
        }
    } elseif (strpos($money, ',') !== false) {
        $money = str_replace('.', '', $money); // só por segurança
        $money = str_replace(',', '.', $money);
    } else {
        $money = str_replace(',', '', $money);
    }

    if (! is_numeric($money)) {
        throw ValidationException::withMessages(['Valor Numérico Informado é Inválido : '.$money]);
    }

    return floatval($money);
}

function toCents($value): int
{
    $dec = valorToDec($value); // ex: "253.50" ou 253.50

    return (int) round(((float) $dec) * 100);
}

function fromCents(int $cents): string
{
    return number_format($cents / 100, 2, '.', '');
}

/**
 * Formats a date for Brazilian display without changing API payloads.
 */
function format_date_br(mixed $value, string $fallback = '-'): string
{
    if ($value === null || $value === '') {
        return $fallback;
    }

    try {
        $date = $value instanceof DateTimeInterface
            ? Carbon\Carbon::parse($value)
            : Carbon\Carbon::parse((string) $value);

        return $date->format('d/m/Y');
    } catch (Throwable) {
        return $fallback;
    }
}
