<?php

declare(strict_types=1);

namespace App\Supports;

use DateTimeImmutable;
use Illuminate\Filesystem\FilesystemAdapter;
use RuntimeException;
use Spatie\Backup\BackupDestination\Backup;
use Spatie\Backup\BackupDestination\BackupCollection;
use Spatie\Backup\Tasks\Backup\BackupJob;
use Spatie\Backup\Tasks\Cleanup\Strategies\DefaultStrategy;

class CheckedBackupCleanupStrategy extends DefaultStrategy
{
    public function deleteOldBackups(BackupCollection $backups): void
    {
        $directory = rtrim($this->backupDestination()->backupName(), '/').'/';
        $prefix = $this->config->backup->destination->filenamePrefix;
        $eligible = $backups->filter(static function (Backup $backup) use ($directory, $prefix): bool {
            if (! str_starts_with($backup->path(), $directory.$prefix)) {
                return false;
            }
            $filename = substr($backup->path(), strlen($directory.$prefix));
            if (preg_match('/\A[0-9]{4}(?:-[0-9]{2}){5}\.zip\z/', $filename) !== 1) {
                return false;
            }
            $date = DateTimeImmutable::createFromFormat('!'.BackupJob::FILENAME_FORMAT, $filename);

            return $date !== false && $date->format(BackupJob::FILENAME_FORMAT) === $filename;
        });
        $checked = new BackupCollection($eligible->map(static function (Backup $backup): Backup {
            return new class($backup->disk(), $backup->path()) extends Backup
            {
                public function delete(): void
                {
                    $disk = $this->disk();
                    if (! $disk instanceof FilesystemAdapter) {
                        throw new RuntimeException('Backup cleanup requires an adapter with a physical existence check.');
                    }
                    parent::delete();
                    if ($this->exists() || $disk->getDriver()->fileExists($this->path())) {
                        throw new RuntimeException('Backup cleanup could not confirm physical deletion.');
                    }
                }
            };
        })->all());

        parent::deleteOldBackups($checked);
    }
}
