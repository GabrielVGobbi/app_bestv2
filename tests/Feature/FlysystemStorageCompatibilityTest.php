<?php

namespace Tests\Feature;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\CorruptedPathDetected;
use Tests\TestCase;

class FlysystemStorageCompatibilityTest extends TestCase
{
    public function test_local_disk_refuses_malformed_utf8_without_mutation_and_recovers_with_valid_utf8(): void
    {
        $disk = Storage::disk('local');
        $this->assertInstanceOf(FilesystemAdapter::class, $disk);
        $owned = realpath($this->testEnvironment->path);
        $root = realpath($disk->path(''));
        $this->assertIsString($owned);
        $this->assertIsString($root);
        $this->assertSame(realpath(storage_path('disks/local')), $root);
        $this->assertStringStartsWith(str_replace('\\', '/', $owned).'/', str_replace('\\', '/', $root));
        $this->assertTrue($disk->put('sentinel-one.txt', 'SENTINEL ONE'));
        $this->assertTrue($disk->put('sentinels/Café.txt', 'SENTINEL TWO ç'));
        $before = $this->snapshot($disk);
        $this->assertCount(2, $before);
        $refused = false;

        try {
            $disk->put("\xFFfixture.txt", 'REJECTED SYNTHETIC CONTENT');
        } catch (CorruptedPathDetected) {
            $refused = true;
        }

        $this->assertTrue($refused, 'The local disk must refuse malformed UTF8 with CorruptedPathDetected before writing.');
        $this->assertSame($before, $this->snapshot($disk));
        $this->assertTrue($disk->put('Recuperação ç.txt', 'VALID RECOVERY ç'));
        $this->assertTrue($disk->exists('Recuperação ç.txt'));
        $this->assertSame('VALID RECOVERY ç', $disk->get('Recuperação ç.txt'));
        $this->assertTrue($disk->delete('Recuperação ç.txt'));
        $this->assertFalse($disk->exists('Recuperação ç.txt'));
        $this->assertSame('SENTINEL ONE', $disk->get('sentinel-one.txt'));
        $this->assertSame('SENTINEL TWO ç', $disk->get('sentinels/Café.txt'));
        $this->assertSame($before, $this->snapshot($disk));
    }

    /** @return array<string, array{contents: string, sha256: string}> */
    private function snapshot(FilesystemAdapter $disk): array
    {
        $snapshot = [];

        foreach ($disk->allFiles() as $path) {
            $contents = $disk->get($path);
            $this->assertIsString($contents);
            $snapshot[$path] = ['contents' => $contents, 'sha256' => hash('sha256', $contents)];
        }

        ksort($snapshot, SORT_STRING);

        return $snapshot;
    }
}
