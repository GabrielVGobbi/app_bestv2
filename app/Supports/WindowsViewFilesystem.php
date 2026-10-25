<?php

namespace App\Supports;

use ErrorException;
use Illuminate\Filesystem\Filesystem;
use RuntimeException;

class WindowsViewFilesystem extends Filesystem
{
    public function __construct(private readonly string $compiledPath) {}

    /**
     * @param  string  $path
     * @param  string  $content
     * @param  int|null  $mode
     */
    public function replace(mixed $path, mixed $content, mixed $mode = null): void
    {
        $directory = realpath($this->compiledPath);
        if (PHP_OS_FAMILY !== 'Windows' || $directory === false || realpath(dirname($path)) !== $directory) {
            parent::replace($path, $content, $mode);

            return;
        }

        clearstatcache(true, $path);
        $path = realpath($path) ?: $path;
        $temporary = tempnam(dirname($path), basename($path));
        if ($temporary === false) {
            throw new RuntimeException('Cannot create a temporary compiled view.');
        }

        try {
            chmod($temporary, $mode ?? (0777 - umask()));
            if (file_put_contents($temporary, $content) !== strlen($content)) {
                throw new RuntimeException('Cannot write the complete compiled view.');
            }

            // Windows readers deny replacement while a compiled PHP file is open.
            $deadline = hrtime(true) + 1_000_000_000;
            do {
                $failure = new ErrorException('Cannot replace the compiled view.');
                set_error_handler(function (int $severity, string $message, string $file, int $line) use (&$failure): bool {
                    $failure = new ErrorException($message, 0, $severity, $file, $line);

                    return true;
                }, E_WARNING);
                try {
                    if (rename($temporary, $path)) {
                        return;
                    }
                } finally {
                    restore_error_handler();
                }
                $message = $failure->getMessage();
                $sharingFailure = str_contains($message, '(code: 5)') || str_contains($message, '(code: 32)');
                if (! $sharingFailure || ! is_file($path) || ! is_writable($path) || hrtime(true) >= $deadline) {
                    throw $failure;
                }
                usleep(25_000);
            } while (true);
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }
}
