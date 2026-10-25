<?php

declare(strict_types=1);

namespace App\Modules\Email\Enums;

enum EmailStatus: string
{
    case Queued = 'queued';
    case Processing = 'processing';
    case Sent = 'sent';
    case Failed = 'failed';
    case Bounced = 'bounced';
    case Delivered = 'delivered';
    case DeliveryDelayed = 'delivery_delayed';
    case Complained = 'complained';
    case Opened = 'opened';
    case Clicked = 'clicked';
    case Received = 'received';
    case Scheduled = 'scheduled';
    case Suppressed = 'suppressed';

    public function acceptsProviderStatus(self $incoming): bool
    {
        if ($this === $incoming || in_array($incoming, [self::Queued, self::Processing], true)) {
            return false;
        }

        if ($this === self::Received || $this === self::Complained) {
            return false;
        }

        if ($this === self::Suppressed) {
            return $incoming === self::Complained;
        }

        if (in_array($incoming, [self::Suppressed, self::Complained], true)) {
            return true;
        }

        return match ($this) {
            self::Queued, self::Processing => true,
            self::Scheduled => in_array($incoming, [self::Sent, self::DeliveryDelayed, self::Failed, self::Bounced, self::Delivered, self::Opened, self::Clicked], true),
            self::Sent, self::DeliveryDelayed => in_array($incoming, [self::DeliveryDelayed, self::Failed, self::Bounced, self::Delivered, self::Opened, self::Clicked], true),
            self::Failed, self::Bounced => in_array($incoming, [self::Delivered, self::Opened, self::Clicked], true),
            self::Delivered => in_array($incoming, [self::Opened, self::Clicked], true),
            self::Opened => $incoming === self::Clicked,
            default => false,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Na fila',
            self::Processing => 'Processando',
            self::Sent => 'Enviado',
            self::Failed => 'Falhou',
            self::Bounced => 'Rejeitado',
            self::Delivered => 'Entregue',
            self::DeliveryDelayed => 'Entrega atrasada',
            self::Complained => 'Spam',
            self::Opened => 'Aberto',
            self::Clicked => 'Clicado',
            self::Received => 'Recebido',
            self::Scheduled => 'Agendado',
            self::Suppressed => 'Suprimido',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Queued => 'amber',
            self::Processing => 'blue',
            self::Sent => 'green',
            self::Failed => 'red',
            self::Bounced => 'orange',
            self::Delivered => 'emerald',
            self::DeliveryDelayed => 'yellow',
            self::Complained => 'rose',
            self::Opened => 'cyan',
            self::Clicked => 'violet',
            self::Received => 'sky',
            self::Scheduled => 'slate',
            self::Suppressed => 'stone',
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
