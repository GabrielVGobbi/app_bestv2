<?php

namespace App\Jobs;

use App\Concerns\Exportable;
use App\Models\Export;
use App\Models\User;
use App\Notifications\ExportReadyNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\CSV\Options as CsvOptions;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use RuntimeException;
use Throwable;

class ProcessExport implements ShouldQueue
{
    use Queueable;

    private ?string $dispatchToken = null;

    private ?string $executionToken = null;

    public function __construct(public readonly Export $export)
    {
        $this->dispatchToken = $export->dispatch_token;
    }

    public int $timeout = 600;

    public int $tries = 3;

    public bool $deleteWhenMissingModels = true;

    /** @var list<int> */
    public array $backoff = [30, 120, 300];

    public function handle(): void
    {
        $export = $this->export->fresh();

        if (! $export || $export->dispatch_token !== $this->dispatchToken || $export->hasActiveProcessingLease() || ! in_array($export->status, [Export::STATUS_PENDING, Export::STATUS_PROCESSING], true)) {
            return;
        }

        try {
            $actor = User::query()->find($export->user_id);
            $modelClass = $export->getRawOriginal('model_class');
            $allowedModels = config('exports.allowed_models', []);
            if (! $actor instanceof User || ! $actor->hasVerifiedEmail() || $actor->must_change_password
                || ! is_string($modelClass) || ! is_array($allowedModels) || ! in_array($modelClass, $allowedModels, true)
                || ! is_subclass_of($modelClass, Model::class)
                || ! in_array(Exportable::class, class_uses_recursive($modelClass), true)
                || ! method_exists($modelClass, 'buildExportQuery')
                || ! method_exists($modelClass, 'getExportableColumns')
                || ! method_exists($modelClass, 'resolveExportColumns')) {
                throw new AuthorizationException;
            }

            $query = $modelClass::buildExportQuery(
                actor: $actor,
                filters: $export->filters ?? [],
                search: $export->search ?? '',
                sort: $export->sort_column ?? '',
                direction: $export->sort_direction ?? '',
            );
            $columns = $export->columns ?? [];
            $validator = Validator::make(['columns' => $columns], [
                'columns' => ['nullable', 'array', 'list'],
                'columns.*' => ['string', 'distinct:strict', Rule::in(array_keys($modelClass::getExportableColumns()))],
            ]);
            if ($validator->fails()) {
                throw new AuthorizationException;
            }

            $resolvedColumns = $modelClass::resolveExportColumns($columns);
        } catch (AuthorizationException) {
            Export::query()->whereKey($export->id)->where('status', $export->status)
                ->where('dispatch_token', $this->dispatchToken)
                ->where('processing_token', $export->processing_token)->where('updated_at', $export->getRawOriginal('updated_at'))
                ->update(['status' => Export::STATUS_FAILED, 'error_message' => __('Export access is no longer permitted.')]);

            return;
        }

        if (! in_array($export->format, ['csv', 'tsv', 'xlsx'], true)) {
            throw new RuntimeException('Unsupported export format.');
        }
        $disk = config('exports.disk', 'local');
        if ($disk === 'public' || config("filesystems.disks.{$disk}.visibility") === 'public') {
            throw new RuntimeException('Export storage must be private.');
        }
        $token = (string) Str::uuid();
        if (! $export->claimProcessing($token, $this->timeout, $this->job?->attempts() ?? 0, $this->queueJobKey(), $this->dispatchToken, function (Export $current): void {
            $oldToken = $current->processing_token;
            if (is_string($oldToken) && Str::isUuid($oldToken)) {
                $this->removeCandidate($current, $oldToken);
            }
        })) {
            return;
        }
        $this->executionToken = $token;
        $storagePath = $this->candidatePath($export, $token);
        $tempPath = $this->temporaryPath($export, $token);
        $published = false;

        try {
            $total = $query->count();
            $export->updateProcessing($token, ['total_records' => $total]);
            $writer = $this->createWriter($export->format, $tempPath);
            $writer->openToFile($tempPath);
            try {
                $writer->addRow($this->createRow(array_values($resolvedColumns), $export->format));
                $columnKeys = array_keys($resolvedColumns);
                $processed = 0;
                $query->chunk(config('exports.chunk_size', 500), function ($records) use ($writer, $columnKeys, &$processed, $export, $token): void {
                    $export->updateProcessing($token, []);
                    $written = 0;
                    try {
                        foreach ($records as $record) {
                            $row = [];
                            foreach ($columnKeys as $key) {
                                $value = $record->{$key};
                                if ($value instanceof \DateTimeInterface) {
                                    $value = $value->format('Y-m-d H:i:s');
                                }
                                $row[] = $value ?? '';
                            }
                            $writer->addRow($this->createRow($row, $export->format));
                            $written++;
                        }
                    } finally {
                        $processed += $written;
                        if ($written > 0) {
                            $export->updateProcessing($token, ['processed_records' => $processed]);
                        }
                    }
                });
            } finally {
                $writer->close();
            }
            $export->updateProcessing($token, []);
            $stream = fopen($tempPath, 'rb');
            if (! is_resource($stream)) {
                throw new RuntimeException('Export temporary stream is unavailable.');
            }
            try {
                if (! Storage::disk($disk)->put($storagePath, $stream)) {
                    throw new RuntimeException('Export publication was refused.');
                }
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
            $fileSize = Storage::disk($disk)->size($storagePath);
            if ($fileSize !== filesize($tempPath)) {
                throw new RuntimeException('Export publication is incomplete.');
            }
            $export->completeProcessing($token, $storagePath, "export-{$export->uuid}.{$export->format}", $fileSize, $processed,
                fn (Export $completed) => $actor->notify(new ExportReadyNotification($completed)));
            $published = true;
        } catch (Throwable $exception) {
            $current = $export->fresh();
            if ($current === null || $current->dispatch_token !== $this->dispatchToken
                || $current->processing_token !== $token
                || $current->status !== Export::STATUS_PROCESSING) {
                return;
            }
            Export::query()->whereKey($export->id)->where('status', Export::STATUS_PROCESSING)
                ->where('processing_token', $token)->update(['processing_expires_at' => now()]);
            throw $exception;
        } finally {
            $current = $export->fresh();
            $committed = $current !== null && $current->status === Export::STATUS_COMPLETED && $current->file_path === $storagePath;
            if (! $published && ! $committed) {
                try {
                    $this->removeCandidate($export, $token);
                } catch (Throwable $exception) {
                    report($exception);
                }
            }
            if (is_file($tempPath) && ! unlink($tempPath)) {
                report(new RuntimeException('Export temporary file remains at '.$tempPath));
            }
        }
    }

    public function failed(Throwable $exception): void
    {
        $export = $this->export->fresh();
        if ($export === null || $export->dispatch_token !== $this->dispatchToken || $export->hasActiveProcessingLease()
            || ! in_array($export->status, [Export::STATUS_PENDING, Export::STATUS_PROCESSING], true)) {
            return;
        }
        if ($export->status === Export::STATUS_PROCESSING) {
            if ($this->executionToken !== null && $export->processing_token !== $this->executionToken) {
                return;
            }
            if ($this->executionToken === null && ($this->queueJobKey() === null || $export->processing_job_key !== $this->queueJobKey()
                || $export->processing_attempt > $this->job->attempts())) {
                return;
            }
        } elseif ($this->executionToken !== null || $export->processing_token !== null || $this->job !== null) {
            return;
        }
        $changed = Export::query()->whereKey($export->id)->where('status', $export->status)
            ->where('dispatch_token', $this->dispatchToken)
            ->where('processing_token', $export->processing_token)
            ->where('processing_expires_at', $export->getRawOriginal('processing_expires_at'))
            ->update(['status' => Export::STATUS_FAILED, 'error_message' => __('Export could not be completed. Please request a new export.')]);
        if ($changed === 1) {
            report($exception);
            if (is_string($export->processing_token) && Str::isUuid($export->processing_token)) {
                $this->removeCandidate($export, $export->processing_token);
            }
        }
    }

    private function queueJobKey(): ?string
    {
        if ($this->job === null) {
            return null;
        }
        $id = (string) $this->job->getJobId();
        if ($id === '') {
            return null;
        }

        return hash('sha256', $this->job->getConnectionName()."\0".$this->job->getQueue()."\0".$id);
    }

    private function candidatePath(Export $export, string $token): string
    {
        $directory = trim((string) config('exports.directory', 'exports'), '/');
        if (preg_match('~\A[a-zA-Z0-9][a-zA-Z0-9/_-]*\z~', $directory) !== 1
            || ! Str::isUuid($export->uuid) || ! Str::isUuid($token)
            || ! in_array($export->format, ['csv', 'tsv', 'xlsx'], true)) {
            throw new RuntimeException('Invalid private export candidate namespace.');
        }

        return "{$directory}/{$export->uuid}/{$token}.{$export->format}";
    }

    private function temporaryPath(Export $export, string $token): string
    {
        $this->candidatePath($export, $token);

        return sys_get_temp_dir().DIRECTORY_SEPARATOR.'export-'.$export->uuid.'-'.$token.'.'.$export->format;
    }

    private function removeCandidate(Export $export, string $token): void
    {
        $path = $this->candidatePath($export, $token);
        $disk = Storage::disk(config('exports.disk', 'local'));
        if ($this->candidateExists($disk, $path) && (! $disk->delete($path) || $this->candidateExists($disk, $path))) {
            throw new RuntimeException('Export candidate cleanup left a residue at '.$path);
        }
        $temporary = $this->temporaryPath($export, $token);
        if (is_file($temporary) && ! unlink($temporary)) {
            throw new RuntimeException('Export staging cleanup left a residue at '.$temporary);
        }
    }

    /** @phpstan-impure */
    private function candidateExists(Filesystem $disk, string $path): bool
    {
        return $disk->exists($path);
    }

    /** @param array<array-key, mixed> $values */
    private function createRow(array $values, string $format): Row
    {
        $cells = [];

        foreach ($values as $value) {
            if (is_string($value)) {
                if ($format !== 'xlsx' && preg_match('/\A(?:[=+\-@\t\r\n]|＝|＋|－|＠|[\x09-\x0D\x20]+(?:[=+\-@]|＝|＋|－|＠))/', $value) === 1) {
                    $value = "'".$value;
                }

                $cells[] = new StringCell($value);

                continue;
            }

            $cells[] = Cell::fromValue($value);
        }

        return new Row($cells);
    }

    private function createWriter(string $format, string $path): CsvWriter|XlsxWriter
    {
        if ($format === 'xlsx') {
            return new XlsxWriter;
        }

        $options = $format === 'tsv'
            ? new CsvOptions(FIELD_DELIMITER: "\t")
            : new CsvOptions;

        return new CsvWriter($options);
    }
}
