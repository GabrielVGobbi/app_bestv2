<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Enums;

enum NotificationCategory: string
{
    case GENERAL = 'general';
    case EXPORT = 'export';
    case IMPORT = 'import';
    case PAYMENT = 'payment';
    case COMMENT = 'comment';
    case MENTION = 'mention';
    case TASK = 'task';
    case POST = 'post';
    case FOLLOW = 'follow';
    case LIKE = 'like';
    case MESSAGE = 'message';
    case SECURITY = 'security';
    case ACCOUNT = 'account';
    case SYSTEM = 'system';

    public function label(): string
    {
        return match ($this) {
            self::GENERAL => 'Geral',
            self::EXPORT => 'Exportação',
            self::IMPORT => 'Importação',
            self::PAYMENT => 'Pagamento',
            self::COMMENT => 'Comentário',
            self::MENTION => 'Menção',
            self::TASK => 'Tarefa',
            self::POST => 'Publicação',
            self::FOLLOW => 'Seguidor',
            self::LIKE => 'Curtida',
            self::MESSAGE => 'Mensagem',
            self::SECURITY => 'Segurança',
            self::ACCOUNT => 'Conta',
            self::SYSTEM => 'Sistema',
        };
    }
}
