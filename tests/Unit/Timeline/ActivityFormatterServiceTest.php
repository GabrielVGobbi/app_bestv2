<?php

declare(strict_types=1);

namespace Tests\Unit\Timeline;

use App\Models\User;
use App\Modules\Clients\Models\Client;
use App\Modules\Timeline\Services\ActivityFormatterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ActivityFormatterServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_formatter_uses_action_catalog_and_friendly_changes(): void
    {
        $user = User::factory()->create(['name' => 'Admin Timeline']);
        $client = Client::factory()->create();
        Activity::query()->delete();

        $activity = Activity::query()->create([
            'log_name' => 'clients',
            'description' => 'Descricao original.',
            'subject_type' => Client::class,
            'subject_id' => $client->id,
            'causer_type' => User::class,
            'causer_id' => $user->id,
            'event' => 'updated',
            'properties' => [
                'attributes' => [
                    'email' => 'novo@example.com',
                    'password' => 'secret',
                ],
                'old' => [
                    'email' => 'antigo@example.com',
                    'password' => 'old-secret',
                ],
            ],
        ])->load(['subject', 'causer']);

        $formatted = app(ActivityFormatterService::class)->format($activity);

        $this->assertSame('activity', $formatted['type']);
        $this->assertSame('activity:'.$activity->id, $formatted['id']);
        $this->assertSame('Editou cliente', $formatted['label']);
        $this->assertSame('Pencil', $formatted['icon']);
        $this->assertSame('warning', $formatted['severity']);
        $this->assertSame('Admin Timeline', $formatted['causer']['name']);
        $this->assertSame('Email alterado de antigo@example.com para novo@example.com.', $formatted['description']);
        $this->assertSame([
            [
                'field' => 'email',
                'label' => 'Email',
                'old' => 'antigo@example.com',
                'new' => 'novo@example.com',
            ],
        ], $formatted['changes']);
    }

    public function test_formatter_keeps_useful_description_when_activity_has_no_changes(): void
    {
        $user = User::factory()->create(['name' => 'Admin Timeline']);
        $client = Client::factory()->create();
        Activity::query()->delete();

        $activity = Activity::query()->create([
            'log_name' => 'clients',
            'description' => 'Admin Timeline visualizou o cliente.',
            'subject_type' => Client::class,
            'subject_id' => $client->id,
            'causer_type' => User::class,
            'causer_id' => $user->id,
            'event' => 'viewed',
            'properties' => [],
        ])->load(['subject', 'causer']);

        $formatted = app(ActivityFormatterService::class)->format($activity);

        $this->assertSame('Visualizou cliente', $formatted['label']);
        $this->assertSame('Eye', $formatted['icon']);
        $this->assertSame('info', $formatted['severity']);
        $this->assertSame('Admin Timeline visualizou o cliente.', $formatted['description']);
        $this->assertSame([], $formatted['changes']);
    }
}
