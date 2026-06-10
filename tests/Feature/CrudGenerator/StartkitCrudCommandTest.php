<?php

declare(strict_types=1);

namespace Tests\Feature\CrudGenerator;

use Tests\TestCase;

class StartkitCrudCommandTest extends TestCase
{
    public function test_preview_command_does_not_write_generated_files(): void
    {
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
