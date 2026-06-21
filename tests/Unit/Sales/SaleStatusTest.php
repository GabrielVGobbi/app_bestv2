<?php

declare(strict_types=1);

namespace Tests\Unit\Sales;

use App\Modules\Sales\Enums\SaleStatus;
use PHPUnit\Framework\TestCase;

class SaleStatusTest extends TestCase
{
    public function test_sale_status_exposes_the_expected_transition_graph(): void
    {
        $this->assertSame(['quoted', 'canceled'], $this->values(SaleStatus::Draft->getAvailableTransitions()));
        $this->assertSame(['draft', 'campaign_started', 'canceled'], $this->values(SaleStatus::Quoted->getAvailableTransitions()));
        $this->assertSame(['campaign_finished', 'canceled'], $this->values(SaleStatus::CampaignStarted->getAvailableTransitions()));
        $this->assertSame([], SaleStatus::CampaignFinished->getAvailableTransitions());
        $this->assertSame([], SaleStatus::Canceled->getAvailableTransitions());
    }

    public function test_sale_status_validates_individual_transitions(): void
    {
        $this->assertTrue(SaleStatus::Draft->canTransitionTo(SaleStatus::Quoted));
        $this->assertTrue(SaleStatus::Quoted->canTransitionTo(SaleStatus::CampaignStarted));
        $this->assertTrue(SaleStatus::CampaignStarted->canTransitionTo(SaleStatus::CampaignFinished));

        $this->assertFalse(SaleStatus::Draft->canTransitionTo(SaleStatus::CampaignStarted));
        $this->assertFalse(SaleStatus::Canceled->canTransitionTo(SaleStatus::Draft));
        $this->assertFalse(SaleStatus::CampaignFinished->canTransitionTo(SaleStatus::Canceled));
    }

    public function test_sale_status_exposes_presenter_and_transition_payloads(): void
    {
        $presenter = SaleStatus::CampaignStarted->toPresenterArray();
        $transition = SaleStatus::CampaignStarted->toTransitionArray();

        $this->assertSame('campaign_started', $presenter['value']);
        $this->assertSame('Campanha iniciada', $presenter['label']);
        $this->assertSame('Confirmar venda', $transition['action_label']);
        $this->assertSame('sales.confirm', $transition['permission']);
        $this->assertTrue($transition['requires_confirmation']);
        $this->assertFalse($transition['requires_reason']);
    }

    public function test_sale_status_declares_reason_and_confirmation_requirements(): void
    {
        $this->assertTrue(SaleStatus::Canceled->requiresReason());
        $this->assertFalse(SaleStatus::Quoted->requiresReason());

        $this->assertFalse(SaleStatus::Quoted->requiresConfirmation());
        $this->assertTrue(SaleStatus::CampaignStarted->requiresConfirmation());
        $this->assertTrue(SaleStatus::CampaignFinished->requiresConfirmation());
        $this->assertTrue(SaleStatus::Canceled->requiresConfirmation());
    }

    /**
     * @param  array<int, SaleStatus>  $statuses
     * @return array<int, string>
     */
    private function values(array $statuses): array
    {
        return array_map(fn (SaleStatus $status): string => $status->value, $statuses);
    }
}
