<?php

declare(strict_types=1);

namespace App\Modules\Email\Enums;

enum EmailCategory: string
{
    case Auth = 'auth';
    case Export = 'export';
    case Notification = 'notification';
    case System = 'system';

    public function label(): string
    {
        return match ($this) {
            self::Auth => 'Autenticação',
            self::Export => 'Exportação',
            self::Notification => 'Notificação',
            self::System => 'Sistema',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }
}
