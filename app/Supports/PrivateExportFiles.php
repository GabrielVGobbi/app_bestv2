<?php

declare(strict_types=1);

namespace App\Supports;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use League\Flysystem\Local\LocalFilesystemAdapter;
use RuntimeException;
use Throwable;

class PrivateExportFiles
{
    /** @param list<string> $extensions */
    public function __construct(
        private readonly string $configPrefix,
        private readonly array $extensions,
        private readonly bool $allowRemote = false,
    ) {}

    public function directory(): string
    {
        $directory = config($this->configPrefix.'.directory', $this->configPrefix);

        if (! is_string($directory) || preg_match('~\A[a-zA-Z0-9_-]+(?:/[a-zA-Z0-9_-]+)*\z~', $directory) !== 1) {
            throw new InvalidArgumentException('Invalid private export directory.');
        }

        return $directory;
    }

    public function usesLocalStorage(): bool
    {
        return $this->isLocal($this->disk());
    }

    /** @return array{uuid: string, token: ?string, temporary: bool}|null */
    public function parse(string $path): ?array
    {
        $prefix = $this->directory().'/';

        if (! str_starts_with($path, $prefix)) {
            return null;
        }

        $relative = substr($path, strlen($prefix));
        $temporary = str_ends_with($relative, '.part');
        $relative = $temporary ? substr($relative, 0, -5) : $relative;
        $parts = explode('/', $relative);

        if (count($parts) > 2) {
            return null;
        }

        $file = array_pop($parts);
        $dot = strrpos($file, '.');

        if ($dot === false || ! in_array(substr($file, $dot + 1), $this->extensions, true)) {
            return null;
        }

        $identifier = substr($file, 0, $dot);
        $uuid = $parts[0] ?? $identifier;

        if (! Str::isUuid($uuid) || ! Str::isUuid($identifier)) {
            return null;
        }

        return ['uuid' => $uuid, 'token' => $parts === [] ? null : $identifier, 'temporary' => $temporary];
    }

    /** @phpstan-impure */
    public function resolveLocal(string $uuid, string $path): ?string
    {
        try {
            $parsed = $this->parse($path);

            if ($parsed === null || strcasecmp($parsed['uuid'], $uuid) !== 0) {
                return null;
            }

            $disk = $this->disk();

            return $this->isLocal($disk) ? $this->localLocation($disk, $path, false) : null;
        } catch (Throwable $exception) {
            $this->logFailure('Private export path resolution failed.', $path, $exception);

            return null;
        }
    }

    /** @return list<string> */
    public function knownPaths(string $uuid): array
    {
        if (! Str::isUuid($uuid)) {
            throw new InvalidArgumentException('Invalid private export identifier.');
        }

        $disk = $this->disk();

        if (! $this->isLocal($disk)) {
            return [];
        }

        $paths = [];

        foreach ($this->extensions as $extension) {
            foreach (['', '.part'] as $suffix) {
                $path = $this->directory().'/'.$uuid.'.'.$extension.$suffix;
                $absolute = $this->localLocation($disk, $path, false);

                if ($this->existsLocally($absolute)) {
                    $paths[] = $path;
                }
            }
        }

        $relative = $this->directory().'/'.$uuid;
        $absolute = $this->localLocation($disk, $relative, true);

        if (! $this->existsLocally($absolute)) {
            return $paths;
        }

        foreach ($this->entries($absolute) as $entry) {
            $path = $relative.'/'.$entry;

            if ($this->parse($path) === null) {
                continue;
            }

            $this->localLocation($disk, $path, false);
            $paths[] = $path;
        }

        return $paths;
    }

    /** @param list<string> $paths */
    public function deletePaths(string $uuid, array $paths): bool
    {
        try {
            if (! Str::isUuid($uuid)) {
                throw new InvalidArgumentException('Invalid private export identifier.');
            }

            $disk = $this->disk();

            foreach ($paths as $path) {
                $parsed = $this->parse($path);

                if ($parsed === null || strcasecmp($parsed['uuid'], $uuid) !== 0) {
                    throw new RuntimeException('Private export artifact does not belong to this namespace.');
                }

                if ($this->isLocal($disk)) {
                    $this->localLocation($disk, $path, false);
                }
            }

            foreach (array_unique($paths) as $path) {
                if ($this->isLocal($disk)) {
                    $absolute = $this->localLocation($disk, $path, false);

                    if (! $this->existsLocally($absolute)) {
                        continue;
                    }
                } elseif (! $disk->exists($path)) {
                    continue;
                } elseif ($disk->getVisibility($path) === 'public') {
                    throw new RuntimeException('Public remote export artifacts cannot be pruned.');
                }

                if (! $disk->delete($path)) {
                    throw new RuntimeException('Private export artifact deletion was refused.');
                }

                if ($this->isLocal($disk)) {
                    if ($this->existsLocally($this->localLocation($disk, $path, false))) {
                        throw new RuntimeException('Private export artifact deletion left a residue.');
                    }
                } elseif ($disk->exists($path)) {
                    throw new RuntimeException('Private remote export artifact deletion left a residue.');
                }
            }

            return true;
        } catch (Throwable $exception) {
            $this->logFailure('Private export artifact cleanup failed.', null, $exception);

            return false;
        }
    }

    /** @param callable(string, string): bool $inUse */
    public function pruneOrphans(callable $inUse, CarbonInterface $before): int
    {
        try {
            $disk = $this->disk();

            if (! $this->isLocal($disk)) {
                return 0;
            }

            $directory = $this->localLocation($disk, $this->directory(), true);

            if (! $this->existsLocally($directory)) {
                return 0;
            }

            $uuids = [];

            foreach ($this->entries($directory) as $entry) {
                $parsed = $this->parse($this->directory().'/'.$entry);

                if ($parsed !== null) {
                    $uuids[$parsed['uuid']] = true;
                } elseif (Str::isUuid($entry)) {
                    $uuids[$entry] = true;
                }
            }
        } catch (Throwable $exception) {
            $this->logFailure('Private export orphan listing failed.', null, $exception);

            return 1;
        }

        $failures = 0;

        foreach (array_keys($uuids) as $uuid) {
            try {
                foreach ($this->knownPaths($uuid) as $path) {
                    $absolute = $this->resolveLocal($uuid, $path);

                    if ($absolute === null) {
                        throw new RuntimeException('Private export orphan path became unsafe.');
                    }

                    $stat = $this->fileIdentity($absolute);

                    if ($stat === null || $stat['mtime'] > $before->getTimestamp() || $this->checkReferences($inUse, $uuid, $path)) {
                        continue;
                    }

                    if ($this->checkReferences($inUse, $uuid, $path)) {
                        continue;
                    }

                    $revalidated = $this->resolveLocal($uuid, $path);

                    if ($revalidated === null) {
                        throw new RuntimeException('Private export orphan path became unsafe before deletion.');
                    }

                    if ($revalidated !== $absolute || $this->fileIdentity($absolute) !== $stat) {
                        continue;
                    }

                    if (! $this->deletePaths($uuid, [$path])) {
                        $failures++;
                    }
                }
            } catch (Throwable $exception) {
                $this->logFailure('Private export orphan cleanup failed.', null, $exception);
                $failures++;
            }
        }

        return $failures;
    }

    /**
     * @param  callable(string, string): bool  $inUse
     *
     * @phpstan-impure
     */
    private function checkReferences(callable $inUse, string $uuid, string $path): bool
    {
        return $inUse($uuid, $path);
    }

    private function disk(): FilesystemAdapter
    {
        $name = config($this->configPrefix.'.disk', 'local');

        if (! is_string($name) || $name === 'public') {
            throw new RuntimeException('Private export disk is invalid.');
        }

        $disk = app(FilesystemFactory::class)->disk($name);

        if (! $disk instanceof FilesystemAdapter) {
            throw new RuntimeException('Private export filesystem adapter is unsupported.');
        }

        $configuration = $disk->getConfig();

        if (($configuration['visibility'] ?? config('filesystems.disks.'.$name.'.visibility')) === 'public'
            || (! $this->isLocal($disk) && (! $this->allowRemote || $disk->getAdapter() instanceof LocalFilesystemAdapter))) {
            throw new RuntimeException('Private export disk is not private or supported.');
        }

        return $disk;
    }

    private function isLocal(FilesystemAdapter $disk): bool
    {
        $name = config($this->configPrefix.'.disk', 'local');

        return $disk->getAdapter() instanceof LocalFilesystemAdapter
            && ($disk->getConfig()['driver'] ?? config('filesystems.disks.'.$name.'.driver', 'local')) === 'local';
    }

    private function localLocation(FilesystemAdapter $disk, string $relative, bool $directory): string
    {
        $root = $this->canonicalLocation($disk->path(''), true);

        if ($root === null) {
            throw new RuntimeException('Private export root is unsafe.');
        }

        foreach ([public_path(), config('filesystems.disks.public.root')] as $publicRoot) {
            if (! is_string($publicRoot) || $publicRoot === '') {
                continue;
            }

            $canonicalPublic = $this->canonicalLocation($publicRoot, false);

            if ($canonicalPublic === null || $this->isWithin($root, $canonicalPublic)
                || $this->isWithin($root.'/'.$relative, $canonicalPublic)) {
                throw new RuntimeException('Private export path overlaps public storage.');
            }
        }

        $current = $root;
        $parts = explode('/', $relative);

        foreach ($parts as $index => $part) {
            $current .= '/'.$part;

            if ($this->existsLocally($current) && ! $this->isCanonicalEntry($current, $directory || $index < count($parts) - 1)) {
                throw new RuntimeException('Private export namespace contains an unsafe entry.');
            }
        }

        return $current;
    }

    /** @return list<string> */
    private function entries(string $directory): array
    {
        if (! $this->isCanonicalEntry($directory, true)) {
            throw new RuntimeException('Private export directory became unsafe before listing.');
        }

        $entries = scandir($directory);

        if ($entries === false) {
            throw new RuntimeException('Private export directory could not be listed.');
        }

        return array_values(array_diff($entries, ['.', '..']));
    }

    private function existsLocally(string $path): bool
    {
        clearstatcache(true, $path);

        return file_exists($path) || is_link($path);
    }

    /** @return array{dev: int, ino: int, mode: int, nlink: int, size: int, mtime: int, ctime: int}|null */
    private function fileIdentity(string $path): ?array
    {
        if (! $this->existsLocally($path) || ! $this->isCanonicalEntry($path, false)) {
            return null;
        }

        $stat = lstat($path);

        return $stat === false ? null : [
            'dev' => $stat['dev'], 'ino' => $stat['ino'], 'mode' => $stat['mode'], 'nlink' => $stat['nlink'],
            'size' => $stat['size'], 'mtime' => $stat['mtime'], 'ctime' => $stat['ctime'],
        ];
    }

    private function canonicalLocation(string $path, bool $requireSafeAncestor): ?string
    {
        $path = rtrim(str_replace('\\', '/', $path), '/');

        if ($path === '' || str_contains($path, "\0") || preg_match('~(?:^|/)\.\.?(/|$)~', $path) === 1
            || (! str_starts_with($path, '/') && preg_match('~^[a-z]:/~i', $path) !== 1)) {
            return null;
        }

        $ancestor = $path;

        while (! file_exists($ancestor) && ! is_link($ancestor)) {
            $parent = dirname($ancestor);

            if ($parent === $ancestor) {
                return null;
            }

            $ancestor = $parent;
        }

        $resolved = realpath($ancestor);

        if ($resolved === false || ! is_dir($resolved)
            || ($requireSafeAncestor && ! $this->isCanonicalEntry($ancestor, true))) {
            return null;
        }

        return rtrim(str_replace('\\', '/', $resolved), '/')
            .substr($path, strlen(rtrim(str_replace('\\', '/', $ancestor), '/')));
    }

    private function isCanonicalEntry(string $path, bool $directory): bool
    {
        clearstatcache(true, $path);
        $stat = lstat($path);
        $resolved = realpath($path);
        $parent = realpath(dirname($path));
        $expected = $parent === false ? null : rtrim($parent, '/\\').'/'.basename($path);

        return $stat !== false && $resolved !== false && $expected !== null && ! is_link($path)
            && $this->normalizePath($resolved) === $this->normalizePath($expected)
            && ($stat['mode'] & 0170000) === ($directory ? 0040000 : 0100000)
            && ($directory || $stat['nlink'] === 1);
    }

    private function normalizePath(string $path): string
    {
        $normalized = rtrim(str_replace('\\', '/', $path), '/');

        return PHP_OS_FAMILY === 'Windows' ? strtolower($normalized) : $normalized;
    }

    private function isWithin(string $path, string $root): bool
    {
        $normalized = $this->normalizePath($path);
        $base = $this->normalizePath($root);

        return $normalized === $base || str_starts_with($normalized, $base.'/');
    }

    private function logFailure(string $message, ?string $path = null, ?Throwable $exception = null): void
    {
        Log::warning($message, ['config' => $this->configPrefix, 'file_path' => $path, 'exception' => $exception]);
    }
}
