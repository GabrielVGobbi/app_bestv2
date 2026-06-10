<?php

declare(strict_types=1);

namespace Tests\Feature\CrudGenerator;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrudGeneratorControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_preview_crud_from_admin_screen(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/admin/dev/crud-generator/preview', [
                'model' => 'ScreenClient',
                'target' => 'module',
                'module' => 'ScreenClients',
                'icon' => 'TableProperties',
                'with_export' => true,
                'with_observer' => false,
                'with_policy' => true,
                'with_factory' => true,
                'with_seeder' => true,
                'with_tests' => true,
                'register_provider' => false,
                'fields' => [
                    [
                        'name' => 'name',
                        'type' => 'string',
                        'nullable' => false,
                        'unique' => false,
                        'searchable' => true,
                        'filterable' => true,
                        'sortable' => true,
                        'exportable' => true,
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.model', 'ScreenClient')
            ->assertJsonPath('data.target', 'module')
            ->assertJsonPath('data.module', 'ScreenClients')
            ->assertJsonFragment([
                'Skip ServiceProvider generation and bootstrap/providers.php registration for App\Modules\ScreenClients\Providers\ScreenClientsServiceProvider',
            ]);

        $this->assertFileDoesNotExist(app_path('Modules/ScreenClients/Models/ScreenClient.php'));
    }
}
