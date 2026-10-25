<?php

declare(strict_types=1);

namespace Tests\Feature\CrudGenerator;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class StartkitCrudCommandTest extends TestCase
{
    public function test_protected_identifier_cannot_be_generated_by_cli_with_force_and_policy_disabled(): void
    {
        $this->isolateProjectFiles();
        foreach ([
            'config/navigation.php' => "<?php\nreturn [\n    'prefixes' => [\n                '/exportacoes',\n    ],\n    'items' => [\n                [\n                    'slug' => 'exportacoes',\n                ],\n    ],\n];\n",
            'resources/js/lib/api/api-routes.ts' => "export const apiRoutes = {\n    exports: {\n    },\n};\n",
        ] as $path => $contents) {
            File::ensureDirectoryExists(dirname(base_path($path)));
            File::put(base_path($path), $contents);
        }
        $before = [];
        foreach (File::allFiles(base_path(), true) as $file) {
            $before[$file->getRelativePathname()] = hash_file('sha256', $file->getPathname());
        }

        $this->artisan('startkit:crud', [
            'model' => 'User', '--module' => 'AuthorityLookalikes', '--field' => ['name:string:required'],
            '--force' => true, '--without-policy' => true, '--without-export' => true,
            '--without-tests' => true,
            '--without-register-provider' => true, '--no-interaction' => true,
        ])->assertFailed();

        $after = [];
        foreach (File::allFiles(base_path(), true) as $file) {
            if ($file->getRelativePathname() !== '.start-kit-generator.lock') {
                $after[$file->getRelativePathname()] = hash_file('sha256', $file->getPathname());
            }
        }
        ksort($before);
        ksort($after);
        $this->assertSame($before, $after);
        $this->assertDirectoryDoesNotExist(app_path('Modules/AuthorityLookalikes'));
    }

    public function test_preview_command_does_not_write_generated_files(): void
    {
        $this->isolateProjectFiles();
        $modelPath = app_path('Modules/PreviewClients/Models/PreviewClient.php');

        $this->artisan('startkit:crud', [
            'model' => 'PreviewClient',
            '--module' => 'PreviewClients',
            '--field' => [
                'name:string:required:searchable',
                'email:string:nullable:unique',
            ],
            '--preview' => true,
            '--no-interaction' => true,
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('Preview generated.')
            ->expectsOutputToContain('PreviewClient');

        $this->assertFileDoesNotExist($modelPath);
    }
}
