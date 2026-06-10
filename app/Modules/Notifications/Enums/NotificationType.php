<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Enums;

enum NotificationType: string
{
    case SYSTEM = 'system';
    case USER = 'user';
    case MENTION = 'mention';
    case SOCIAL = 'social';
    case ACTION = 'action';
    case WARNING = 'warning';
    case ERROR = 'error';
    case SUCCESS = 'success';
    case INFO = 'info';

    public function label(): string
    {
        return match ($this) {
            self::SYSTEM => 'Sistema',
            self::USER => 'Usuário',
            self::MENTION => 'Menção',
            self::SOCIAL => 'Social',
            self::ACTION => 'Ação',
            self::WARNING => 'Aviso',
            self::ERROR => 'Erro',
            self::SUCCESS => 'Sucesso',
            self::INFO => 'Informação',
        };
    }
}
