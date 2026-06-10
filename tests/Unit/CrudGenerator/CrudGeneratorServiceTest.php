<?php

declare(strict_types=1);

namespace Tests\Unit\CrudGenerator;

use App\Modules\CrudGenerator\DTOs\CrudBlueprint;
use App\Modules\CrudGenerator\DTOs\CrudField;
use App\Modules\CrudGenerator\Services\CrudGeneratorService;
use Tests\TestCase;

class CrudGeneratorServiceTest extends TestCase
{
    public function test_preview_builds_modular_crud_plan_without_writing_files(): void
    {
        $blueprint = new CrudBlueprint(
            model: 'SpecClient',
            module: 'SpecClients',
            fields: [
                new CrudField(name: 'name', searchable: true, filterable: true),
                new CrudField(name: 'email', nullable: true, unique: true, searchable: true),
            ],
            withExport: true,
            withObserver: true,
        );

        $result = app(CrudGeneratorService::class)->preview($blueprint);
        $paths = array_map(fn ($file): string => $file->path, $result->files);

        $this->assertFalse($result->hasConflicts());
        $this->assertContains(app_path('Modules/SpecClients/Models/SpecClient.php'), $paths);
        $this->assertContains(app_path('Modules/SpecClients/Http/Controllers/SpecClientController.php'), $paths);
        $this->assertContains(app_path('Modules/SpecClients/Http/Requests/SpecClient/StoreSpecClientRequest.php'), $paths);
        $this->assertContains(app_path('Modules/SpecClients/Database/Factories/SpecClientFactory.php'), $paths);
        $this->assertContains(app_path('Modules/SpecClients/Database/Seeders/SpecClientSeeder.php'), $paths);
        $this->assertContains(resource_path('js/pages/app/spec-clients/index.tsx'), $paths);
        $this->assertContains(resource_path('js/types/app/spec-client.ts'), $paths);
        $this->assertTrue(
            collect($paths)->contains(fn (string $path): bool => str_contains($path, 'app/Modules/SpecClients/Database/Migrations') || str_contains($path, 'app\\Modules/SpecClients/Database/Migrations')),
        );

        $files = collect($result->files)->keyBy(fn ($file): string => $file->path);
        $controller = $files->get(app_path('Modules/SpecClients/Http/Controllers/SpecClientController.php'))->content;
        $indexPage = $files->get(resource_path('js/pages/app/spec-clients/index.tsx'))->content;
        $createPage = $files->get(resource_path('js/pages/app/spec-clients/create.tsx'))->content;
        $showPage = $files->get(resource_path('js/pages/app/spec-clients/show.tsx'))->content;
        $fieldsPage = $files->get(resource_path('js/pages/app/spec-clients/partials/spec-client-form-fields.tsx'))->content;

        $this->assertStringContainsString("return to_route('spec-clients.index', [], 303);", $controller);
        $this->assertStringContainsString('const dateFilterColumns: DataTableDateColumn[]', $indexPage);
        $this->assertStringContainsString("void queryClient.invalidateQueries({ queryKey: ['spec-clients'] });", $indexPage);
        $this->assertStringContainsString('dateFilterColumns={dateFilterColumns}', $indexPage);
        $this->assertStringContainsString('className="page-wrapper-sm"', $createPage);
        $this->assertStringContainsString("toast.error('Verifique os campos obrigatorios.')", $createPage);
        $this->assertStringContainsString("import Header from '@/components/dashboard/header';", $showPage);
        $this->assertStringContainsString('<DeleteButton', $showPage);
        $this->assertStringContainsString('resourceName={String(specClient.name ?? specClient.uuid)}', $showPage);
        $this->assertStringContainsString('className="page-wrapper-lg flex flex-col gap-6"', $showPage);
        $this->assertStringContainsString("import FormField from '@/components/ui/forms/form-field';", $fieldsPage);
        $this->assertStringContainsString('id="field-name"', $fieldsPage);

        $this->assertContains('Allow App\Modules\SpecClients\Models\SpecClient in config/exports.php', $result->changes);
        $this->assertFileDoesNotExist(app_path('Modules/SpecClients/Models/SpecClient.php'));
    }

    public function test_preview_can_skip_provider_registration(): void
    {
        $blueprint = new CrudBlueprint(
            model: 'ManualClient',
            module: 'ManualClients',
            fields: [
                new CrudField(name: 'name', searchable: true),
            ],
            registerProvider: false,
        );

        $result = app(CrudGeneratorService::class)->preview($blueprint);

        $paths = array_map(fn ($file): string => $file->path, $result->files);

        $this->assertContains(
            'Skip ServiceProvider generation and bootstrap/providers.php registration for App\Modules\ManualClients\Providers\ManualClientsServiceProvider',
            $result->changes,
        );
        $this->assertNotContains(
            app_path('Modules/ManualClients/Providers/ManualClientsServiceProvider.php'),
            $paths,
        );
        $this->assertNotContains(
            'Generate and register App\Modules\ManualClients\Providers\ManualClientsServiceProvider in bootstrap/providers.php',
            $result->changes,
        );
    }

    public function test_preview_can_target_app_structure(): void
    {
        $blueprint = new CrudBlueprint(
            model: 'AppClient',
            module: 'AppClients',
            fields: [
                new CrudField(name: 'name', searchable: true),
            ],
            registerProvider: false,
            target: 'app',
        );

        $result = app(CrudGeneratorService::class)->preview($blueprint);
        $paths = array_map(fn ($file): string => $file->path, $result->files);

        $this->assertContains(app_path('Models/AppClient.php'), $paths);
        $this->assertContains(app_path('Http/Controllers/AppClientController.php'), $paths);
        $this->assertContains(app_path('Http/Controllers/Api/AppClientApiController.php'), $paths);
        $this->assertTrue(
            collect($paths)->contains(fn (string $path): bool => str_contains($path, 'app/Http/Requests/AppClient/StoreAppClientRequest.php') || str_contains($path, 'app\\Http/Requests/AppClient/StoreAppClientRequest.php')),
        );
        $this->assertContains(database_path('factories/AppClientFactory.php'), $paths);
        $this->assertContains(database_path('seeders/AppClientSeeder.php'), $paths);
        $this->assertTrue(
            collect($paths)->contains(fn (string $path): bool => str_contains($path, 'database/migrations') || str_contains($path, 'database\\migrations')),
        );
        $this->assertNotContains(app_path('Modules/AppClients/routes.php'), $paths);
        $this->assertContains('Register App\Http\Controllers\AppClientController in routes/web.php', $result->changes);
        $this->assertContains('Register App\Http\Controllers\Api\AppClientApiController in routes/api.php', $result->changes);
    }
}
