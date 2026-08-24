<?php

declare(strict_types=1);

namespace App\Modules\Finance\Enums;

use App\Supports\Contracts\StatusableEnum;
use App\Supports\Enums\Concerns\HasBadgePresenter;

enum FinancialEntryStatus: string implements StatusableEnum
{
    use HasBadgePresenter;

    case Open = 'open';
    case Overdue = 'overdue';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Canceled = 'canceled';
    case Reversed = 'reversed';
    case Renegotiated = 'renegotiated';

    private const array VALID_TRANSITIONS = [
        'open' => ['overdue', 'partially_paid', 'paid', 'canceled', 'renegotiated'],
        'overdue' => ['partially_paid', 'paid', 'canceled', 'renegotiated'],
        'partially_paid' => ['paid', 'reversed', 'renegotiated'],
        'paid' => ['reversed'],
        'canceled' => [],
        'reversed' => ['open', 'overdue'],
        'renegotiated' => [],
    ];

    /** @var array<string, array{label: string, color: string, icon: string}> */
    private const array PRESENTERS = [
        'open' => ['label' => 'Em aberto', 'color' => 'amber', 'icon' => 'Circle'],
        'overdue' => ['label' => 'Vencido', 'color' => 'red', 'icon' => 'AlertCircle'],
        'partially_paid' => ['label' => 'Pago parcial', 'color' => 'amber', 'icon' => 'CircleDollarSign'],
        'paid' => ['label' => 'Pago', 'color' => 'emerald', 'icon' => 'CheckCircle2'],
        'canceled' => ['label' => 'Cancelado', 'color' => 'zinc', 'icon' => 'XCircle'],
        'reversed' => ['label' => 'Estornado', 'color' => 'violet', 'icon' => 'RotateCcw'],
        'renegotiated' => ['label' => 'Renegociado', 'color' => 'indigo', 'icon' => 'RefreshCw'],
    ];

    /** @var array<string, array{label: string, permission: string, requires_reason: bool, requires_confirmation: bool}> */
    private const array ACTIONS = [
        'open' => [
            'label' => 'Reabrir',
            'permission' => 'finance.entries.update',
            'requires_reason' => false,
            'requires_confirmation' => false,
        ],
        'overdue' => [
            'label' => 'Marcar vencido',
            'permission' => 'finance.entries.update',
            'requires_reason' => false,
            'requires_confirmation' => false,
        ],
        'partially_paid' => [
            'label' => 'Baixar parcialmente',
            'permission' => 'finance.settlements.create',
            'requires_reason' => false,
            'requires_confirmation' => true,
        ],
        'paid' => [
            'label' => 'Liquidar',
            'permission' => 'finance.settlements.create',
            'requires_reason' => false,
            'requires_confirmation' => true,
        ],
        'canceled' => [
            'label' => 'Cancelar',
            'permission' => 'finance.entries.cancel',
            'requires_reason' => true,
            'requires_confirmation' => true,
        ],
        'reversed' => [
            'label' => 'Estornar',
            'permission' => 'finance.settlements.reverse',
            'requires_reason' => true,
            'requires_confirmation' => true,
        ],
        'renegotiated' => [
            'label' => 'Renegociar',
            'permission' => 'finance.entries.renegotiate',
            'requires_reason' => true,
            'requires_confirmation' => true,
        ],
    ];

    public function getActionLabel(): string
    {
        return self::ACTIONS[$this->value]['label'];
    }

    public function canTransitionTo(StatusableEnum $newStatus): bool
    {
        if (! $newStatus instanceof self) {
            return false;
        }

        return in_array($newStatus->value, self::VALID_TRANSITIONS[$this->value], true);
    }

    /** @return array<int, self> */
    public function getAvailableTransitions(): array
    {
        return array_map(
            fn (string $value): self => self::from($value),
            self::VALID_TRANSITIONS[$this->value],
        );
    }

    /** @return array<string, mixed> */
    public function getStatusFields(): array
    {
        return [];
    }

    /** @return array<string, mixed> */
    public function getLogMetadata(): array
    {
        return [
            'value' => $this->value,
            'label' => $this->getLabelText(),
            'color' => $this->getLabelColor(),
            'icon' => $this->getIcon(),
        ];
    }

    public function requiresReason(): bool
    {
        return self::ACTIONS[$this->value]['requires_reason'];
    }

    public function requiresConfirmation(): bool
    {
        return self::ACTIONS[$this->value]['requires_confirmation'];
    }

    /** @return array<string, mixed> */
    public function toTransitionArray(): array
    {
        return [
            ...$this->toPresenterArray(),
            'action_label' => $this->getActionLabel(),
            'permission' => self::ACTIONS[$this->value]['permission'],
            'requires_reason' => $this->requiresReason(),
            'requires_confirmation' => $this->requiresConfirmation(),
        ];
    }
}
