<?php

namespace App\Jobs;

use App\Models\Export;
use App\Notifications\ExportReadyNotification;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\CSV\Options as CsvOptions;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Throwable;

class ProcessExport implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Export $export) {}

    public function uniqueId(): string
    {
        return $this->export->uuid;
    }

    public function handle(): void
    {
        $export = $this->export->fresh();

        if (! $export || ! in_array($export->status, [Export::STATUS_PENDING, Export::STATUS_PROCESSING], true)) {
            return;
        }

        // Retrying after an interrupted processing attempt — reset progress so counts stay accurate
        if ($export->status === Export::STATUS_PROCESSING) {
            $export->update(['processed_records' => 0]);
        }

        $export->markAsProcessing();

        $modelClass = $export->model_class;

        $resolvedColumns = $modelClass::resolveExportColumns($export->columns ?? []);

        $query = $modelClass::buildExportQuery(
            filters: $export->filters ?? [],
            search: $export->search ?? '',
            sort: $export->sort_column ?? '',
            direction: $export->sort_direction ?? '',
        );

        $total = $query->count();
        $export->update(['total_records' => $total]);

        $tempPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.$export->uuid.'.'.$export->format;

        try {
            $writer = $this->createWriter($export->format, $tempPath);
            $writer->openToFile($tempPath);

            $writer->addRow(Row::fromValues(array_values($resolvedColumns)));

            $columnKeys = array_keys($resolvedColumns);
            $processed = 0;

            $query->chunkById(config('exports.chunk_size', 500), function ($records) use ($writer, $columnKeys, &$processed, $export): void {
                foreach ($records as $record) {
                    $row = [];

                    foreach ($columnKeys as $key) {
                        $value = $record->{$key};

                        if ($value instanceof \DateTimeInterface) {
                            $value = $value->format('Y-m-d H:i:s');
                        }

                        $row[] = $value ?? '';
                    }

                    $writer->addRow(Row::fromValues($row));
                }

                $processed += count($records);
                $export->incrementProcessed(count($records));
            });

            $writer->close();

            $disk = config('exports.disk', 'local');
            $directory = config('exports.directory', 'exports');
            $storagePath = "{$directory}/{$export->uuid}.{$export->format}";

            $stream = fopen($tempPath, 'r');
            Storage::disk($disk)->put($storagePath, $stream);
            fclose($stream);

            $fileSize = Storage::disk($disk)->size($storagePath);
            $fileName = "export-{$export->uuid}.{$export->format}";

            $export->markAsCompleted($storagePath, $fileName, $fileSize, $total);
            $export->user->notify(new ExportReadyNotification($export));
        } finally {
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
        }
    }

    public function failed(Throwable $exception): void
    {
        $this->export->markAsFailed($exception->getMessage());
    }

    private function createWriter(string $format, string $path): CsvWriter|XlsxWriter
    {
        if ($format === 'xlsx') {
            return new XlsxWriter;
        }

        $options = $format === 'tsv'
            ? new CsvOptions(FIELD_DELIMITER: "\t", FIELD_ENCLOSURE: chr(0))
            : new CsvOptions;

        return new CsvWriter($options);
    }
}
