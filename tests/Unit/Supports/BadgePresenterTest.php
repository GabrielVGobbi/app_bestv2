<?php

declare(strict_types=1);

namespace Tests\Unit\Supports;

use App\Modules\Playlists\Enums\PlaylistSlotStatus;
use App\Modules\Playlists\Enums\PlaylistStatus;
use App\Modules\Sales\Enums\SaleItemStatus;
use App\Modules\Sales\Enums\SaleStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BadgePresenterTest extends TestCase
{
    #[DataProvider('presenterCases')]
    public function test_status_enums_expose_a_complete_badge_presenter(object $status, array $expected): void
    {
        $this->assertSame($expected, $status->toPresenterArray());
    }

    /** @return array<string, array{0: object, 1: array{value: string, label: string, color: string, icon: string}}> */
    public static function presenterCases(): array
    {
        return [
            'sale campaign started' => [
                SaleStatus::CampaignStarted,
                [
                    'value' => 'campaign_started',
                    'label' => 'Campanha iniciada',
                    'color' => 'emerald',
                    'icon' => 'PlayCircle',
                ],
            ],
            'sale item reserved' => [
                SaleItemStatus::Reserved,
                [
                    'value' => 'reserved',
                    'label' => 'Reservado',
                    'color' => 'amber',
                    'icon' => 'CalendarClock',
                ],
            ],
            'playlist inactive' => [
                PlaylistStatus::Inactive,
                [
                    'value' => 'inactive',
                    'label' => 'Inativa',
                    'color' => 'zinc',
                    'icon' => 'PauseCircle',
                ],
            ],
            'playlist slot pending schedule' => [
                PlaylistSlotStatus::PendingSchedule,
                [
                    'value' => 'pending_schedule',
                    'label' => 'Pendente de agenda',
                    'color' => 'amber',
                    'icon' => 'CalendarClock',
                ],
            ],
        ];
    }
}
