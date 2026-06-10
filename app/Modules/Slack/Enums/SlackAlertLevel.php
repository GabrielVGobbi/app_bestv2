<?php

declare(strict_types=1);

namespace App\Modules\Slack\Enums;

enum SlackAlertLevel: string
{
    case Info = 'info';
    case Warning = 'warning';
    case Error = 'error';
    case Critical = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::Info => 'Info',
            self::Warning => 'Aviso',
            self::Error => 'Erro',
            self::Critical => 'Crítico',
        };
    }

    /** Hex color used in Slack attachment sidebar. */
    public function color(): string
    {
        return match ($this) {
            self::Info => '#36a64f',
            self::Warning => '#f0a500',
            self::Error => '#e01e5a',
            self::Critical => '#8e0000',
        };
    }

    public function emoji(): string
    {
        return match ($this) {
            self::Info => ':information_source:',
            self::Warning => ':warning:',
            self::Error => ':x:',
            self::Critical => ':rotating_light:',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_column(
            array_map(fn (self $case) => ['value' => $case->value, 'label' => $case->label()], self::cases()),
            'label',
            'value',
        );
    }
}
