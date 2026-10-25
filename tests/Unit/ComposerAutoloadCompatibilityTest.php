<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ComposerAutoloadCompatibilityTest extends TestCase
{
    public function test_autoload_files_use_exact_existing_php_paths_inside_the_project(): void
    {
        $root = realpath(dirname(__DIR__, 2));
        $this->assertIsString($root);
        $contents = file_get_contents($root.'/composer.json');
        $this->assertIsString($contents);
        $composer = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($composer);
        $files = $composer['autoload']['files'] ?? null;
        $this->assertIsArray($files);
        $this->assertNotEmpty($files);
        $this->assertTrue(array_is_list($files));

        foreach ($files as $file) {
            $this->assertIsString($file);
            $this->assertNotSame('', $file);
            $relativePath = str_replace('\\', '/', $file);
            $this->assertDoesNotMatchRegularExpression('/\A(?:[A-Za-z]:|\/)/', $relativePath, 'Autoload files must use project-relative paths.');
            $this->assertSame('php', pathinfo($relativePath, PATHINFO_EXTENSION), $file);
            $current = $root;

            foreach (explode('/', $relativePath) as $component) {
                $this->assertFalse(in_array($component, ['', '.', '..'], true), 'Autoload paths must not contain empty or traversal components: '.$file);
                $this->assertDirectoryExists($current);
                $entries = scandir($current);
                $this->assertIsArray($entries);
                $this->assertTrue(in_array($component, $entries, true), 'Autoload path component does not match the exact on-disk case: '.$file.' ('.$component.')');
                $current .= DIRECTORY_SEPARATOR.$component;
                $resolved = realpath($current);
                $this->assertIsString($resolved, 'Autoload path must exist: '.$file);
                $this->assertStringStartsWith($root.DIRECTORY_SEPARATOR, $resolved, 'Autoload path must remain inside the project: '.$file);
            }

            $this->assertFileExists($current);
            $this->assertTrue(is_file($current), 'Autoload entry must be a regular PHP file: '.$file);
        }
    }
}
