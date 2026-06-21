<?php

declare(strict_types=1);

namespace App\Modules\Sales\Enums;

use App\Modules\Sales\Actions\Status\CancelSale;
use App\Modules\Sales\Actions\Status\ConfirmSale;
use App\Modules\Sales\Actions\Status\FinishSaleCampaign;
use App\Modules\Sales\Actions\Status\QuoteSale;
use App\Supports\Contracts\StatusableEnum;
use App\Supports\Enums\Concerns\HasBadgePresenter;

enum SaleStatus: string implements StatusableEnum
{
    use HasBadgePresenter;

    case Draft = 'draft';
    case Quoted = 'quoted';
    case CampaignStarted = 'campaign_started';
    case CampaignFinished = 'campaign_finished';
    case Canceled = 'canceled';

    private const array VALID_TRANSITIONS = [
        'draft' => ['quoted', 'canceled'],
        'quoted' => ['draft', 'campaign_started', 'canceled'],
        'campaign_started' => ['campaign_finished', 'canceled'],
        'campaign_finished' => [],
        'canceled' => [],
    ];

    /** @var array<string, array{label: string, color: string, icon: string}> */
    private const array PRESENTERS = [
        'draft' => ['label' => 'Rascunho', 'color' => 'zinc', 'icon' => 'FilePenLine'],
        'quoted' => ['label' => 'Orçamento', 'color' => 'blue', 'icon' => 'Send'],
        'campaign_started' => ['label' => 'Campanha iniciada', 'color' => 'emerald', 'icon' => 'PlayCircle'],
        'campaign_finished' => ['label' => 'Campanha finalizada', 'color' => 'violet', 'icon' => 'CheckCircle2'],
        'canceled' => ['label' => 'Cancelada', 'color' => 'red', 'icon' => 'XCircle'],
    ];

    /** @var array<string, array{label: string, permission: string, requires_reason: bool, requires_confirmation: bool, action: class-string}> */
    private const array ACTIONS = [
        'draft' => [
            'label' => 'Voltar para rascunho',
            'permission' => 'sales.update',
            'requires_reason' => false,
            'requires_confirmation' => false,
            'action' => QuoteSale::class,
        ],
        'quoted' => [
            'label' => 'Enviar orçamento',
            'permission' => 'sales.quote',
            'requires_reason' => false,
            'requires_confirmation' => false,
            'action' => QuoteSale::class,
        ],
        'campaign_started' => [
            'label' => 'Confirmar venda',
            'permission' => 'sales.confirm',
            'requires_reason' => false,
            'requires_confirmation' => true,
            'action' => ConfirmSale::class,
        ],
        'campaign_finished' => [
            'label' => 'Finalizar campanha',
            'permission' => 'sales.finish',
            'requires_reason' => false,
            'requires_confirmation' => true,
            'action' => FinishSaleCampaign::class,
        ],
        'canceled' => [
            'label' => 'Cancelar venda',
            'permission' => 'sales.cancel',
            'requires_reason' => true,
            'requires_confirmation' => true,
            'action' => CancelSale::class,
        ],
    ];

    public function getActionLabel(): string
    {
        return self::ACTIONS[$this->value]['label'];
    }

    public function getPermission(): string
    {
        return self::ACTIONS[$this->value]['permission'];
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
        return match ($this) {
            self::CampaignStarted => [
                'confirmed_at' => 'now()',
                'confirmed_by_id' => 'user_id',
            ],
            self::CampaignFinished => [
                'finished_at' => 'now()',
                'finished_by_id' => 'user_id',
            ],
            self::Canceled => [
                'canceled_at' => 'now()',
                'canceled_by_id' => 'user_id',
            ],
            default => [],
        };
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
            'permission' => $this->getPermission(),
            'requires_reason' => $this->requiresReason(),
            'requires_confirmation' => $this->requiresConfirmation(),
        ];
    }
}
