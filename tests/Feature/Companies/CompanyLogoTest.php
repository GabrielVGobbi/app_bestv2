<?php

declare(strict_types=1);

namespace Tests\Feature\Companies;

use App\Models\User;
use App\Modules\Companies\Http\Resources\CompanyResource;
use App\Modules\Companies\Models\Company;
use App\Modules\Permissions\Database\Seeders\RolesSeeder;
use App\Modules\Permissions\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CompanyLogoTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_can_be_created_with_logo(): void
    {
        Storage::fake('public');

        $user = $this->userWithRole('admin');

        $response = $this->actingAs($user)->post(route('companies.store'), [
            'name' => 'Empresa Logo',
            'nickname' => 'EL',
            'cnpj' => '12345678000199',
            'logo' => UploadedFile::fake()->image('logo.png', 240, 240),
        ]);

        $company = Company::query()->where('cnpj', '12345678000199')->firstOrFail();

        $response->assertRedirect(route('companies.show', $company));

        $this->assertSame('public', $company->logo_disk);
        $this->assertNotNull($company->logo_path);
        $this->assertNotNull($company->logo_updated_at);
        Storage::disk('public')->assertExists($company->logo_path);

        $resource = (new CompanyResource($company))->resolve(request());

        $this->assertSame($company->logoUrl(), $resource['logo_url']);
        $this->assertSame('EL', $resource['logo_initials']);
        $this->assertNotEmpty($resource['logo_color']);
    }

    public function test_company_logo_can_be_removed_on_update(): void
    {
        Storage::fake('public');

        $user = $this->userWithRole('admin');
        $company = Company::factory()->create([
            'name' => 'Empresa Remocao',
            'nickname' => 'ER',
            'cnpj' => '22345678000199',
        ]);

        $this->actingAs($user)->post(route('companies.update', $company), [
            '_method' => 'put',
            'name' => $company->name,
            'nickname' => $company->nickname,
            'cnpj' => $company->cnpj,
            'logo' => UploadedFile::fake()->image('logo.png', 240, 240),
        ])->assertRedirect(route('companies.show', $company));

        $company->refresh();
        $previousLogoPath = $company->logo_path;

        $this->assertNotNull($previousLogoPath);
        Storage::disk('public')->assertExists($previousLogoPath);

        $this->actingAs($user)->post(route('companies.update', $company), [
            '_method' => 'put',
            'name' => $company->name,
            'nickname' => $company->nickname,
            'cnpj' => $company->cnpj,
            'remove_logo' => '1',
        ])->assertRedirect(route('companies.show', $company));

        $company->refresh();

        $this->assertNull($company->logo_disk);
        $this->assertNull($company->logo_path);
        $this->assertNull($company->logo_updated_at);
        Storage::disk('public')->assertMissing($previousLogoPath);
    }

    private function userWithRole(string $roleSlug): User
    {
        $this->seed(RolesSeeder::class);

        $role = Role::query()->where('slug', $roleSlug)->firstOrFail();
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $user->roles()->attach($role);

        return $user;
    }
}
