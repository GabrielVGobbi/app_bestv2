<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AppInitCommandTest extends TestCase
{
    public function test_it_runs_without_overwriting_existing_private_files(): void
    {
        $environmentPath = base_path('.env');
        $configPath = base_path('.config');

        $originalEnvironment = File::exists($environmentPath) ? File::get($environmentPath) : null;
        $originalConfig = File::exists($configPath) ? File::get($configPath) : null;

        $this->artisan('app:init', ['--skip-storage-link' => true])
            ->assertSuccessful();

        $this->assertFileExists($environmentPath);
        $this->assertFileExists($configPath);

        if ($originalEnvironment !== null) {
            $this->assertSame($originalEnvironment, File::get($environmentPath));
        }

        if ($originalConfig !== null) {
            $this->assertSame($originalConfig, File::get($configPath));
        }
    }
}
