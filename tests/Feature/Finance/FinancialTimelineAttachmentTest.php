<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\User;
use App\Modules\Finance\Models\FinancialEntry;
use App\Modules\Permissions\Database\Seeders\PermissionsSeeder;
use App\Modules\Permissions\Database\Seeders\RolesSeeder;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Permissions\Models\Role;
use App\Modules\Uploads\Models\Uploaded;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FinancialTimelineAttachmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_financial_entry_is_registered_as_timeline_subject(): void
    {
        $user = $this->userWithRole('finance');
        $entry = FinancialEntry::factory()->create([
            'description' => 'Recebivel com timeline',
        ]);

        Sanctum::actingAs($user);

        $this->getJson(route('api.timeline.show', [
            'type' => 'financial-entry',
            'id' => $entry->uuid,
        ]))
            ->assertOk()
            ->assertJsonPath('subject.type', 'financial-entry')
            ->assertJsonPath('subject.uuid', $entry->uuid);
    }

    public function test_financial_entry_accepts_protected_attachments_by_alias(): void
    {
        Storage::fake('local');

        $user = $this->userWithRole('finance');
        $entry = FinancialEntry::factory()->create([
            'settled_amount' => 0,
        ]);

        Sanctum::actingAs($user);

        $this->postJson(route('api.uploads.store'), [
            'parentable_type' => 'financial-entry',
            'parentable_id' => $entry->uuid,
            'files' => [
                UploadedFile::fake()->createWithContent('recibo.txt', 'comprovante'),
            ],
            'tag' => 'receipt',
            'collection' => 'documents',
        ])
            ->assertCreated()
            ->assertJsonPath('data.0.tag', 'receipt')
            ->assertJsonPath('data.0.collection', 'documents');

        $upload = Uploaded::query()->sole();

        $this->assertSame(FinancialEntry::class, $upload->parentable_type);
        $this->assertSame($entry->id, $upload->parentable_id);
        Storage::disk('local')->assertExists($upload->path);
    }

    public function test_v3_finance_permissions_are_seeded_for_specialized_surfaces(): void
    {
        $this->seed([RolesSeeder::class, PermissionsSeeder::class]);

        $this->assertTrue(Permission::query()->where('slug', 'finance.reports.view')->exists());
        $this->assertTrue(Permission::query()->where('slug', 'finance.reconciliation.manage')->exists());
        $this->assertTrue(Permission::query()->where('slug', 'finance.attachments.manage')->exists());

        $financeRole = Role::query()->where('slug', 'finance')->firstOrFail();

        $this->assertTrue($financeRole->permissions()->where('slug', 'finance.reports.view')->exists());
        $this->assertTrue($financeRole->permissions()->where('slug', 'finance.reconciliation.manage')->exists());
        $this->assertTrue($financeRole->permissions()->where('slug', 'finance.attachments.manage')->exists());
    }

    private function userWithRole(string $roleSlug): User
    {
        $this->seed([RolesSeeder::class, PermissionsSeeder::class]);

        $role = Role::query()->where('slug', $roleSlug)->firstOrFail();
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $user->roles()->attach($role);

        return $user;
    }
}
