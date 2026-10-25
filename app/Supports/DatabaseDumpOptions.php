<?php

declare(strict_types=1);

namespace App\Supports;

use App\Modules\Backup\Models\DatabaseExport;
use Illuminate\Database\Connection;
use RuntimeException;

class DatabaseDumpOptions
{
    /** @return list<string> */
    public static function supportedTypes(string $driver): array
    {
        return match ($driver) {
            'sqlite' => [DatabaseExport::TYPE_FULL],
            'mysql', 'mariadb', 'pgsql' => [DatabaseExport::TYPE_SCHEMA, DatabaseExport::TYPE_DATA, DatabaseExport::TYPE_FULL],
            default => [],
        };
    }

    public static function assertSupportedType(string $driver, string $type): void
    {
        if (! in_array($type, self::supportedTypes($driver), true)) {
            throw new RuntimeException('Database dump mode is not supported by this driver.');
        }
    }

    public static function tablePattern(string $driver): string
    {
        return match ($driver) {
            'sqlite' => '/\A[A-Za-z][A-Za-z0-9]*\z/',
            'pgsql' => '/\A[a-z_][a-z0-9_]*\z/',
            default => '/\A[A-Za-z_][A-Za-z0-9_]*\z/',
        };
    }

    /** @return list<string> */
    public static function tables(Connection $connection): array
    {
        $driver = $connection->getDriverName();
        if (self::supportedTypes($driver) === []) {
            throw new RuntimeException('Unsupported database dump driver.');
        }

        $tables = $connection->getSchemaBuilder()->getTableListing($driver === 'pgsql' ? 'public' : null, false);
        sort($tables, SORT_STRING);

        return $tables;
    }

    /** @return list<string> */
    public static function validateTables(string $driver, mixed $tables): array
    {
        if (! is_array($tables)) {
            throw new RuntimeException('Database dump table filters must be an array.');
        }
        foreach ($tables as $table) {
            if (! is_string($table) || preg_match(self::tablePattern($driver), $table) !== 1) {
                throw new RuntimeException('Database dump table filters must be unambiguous literal identifiers.');
            }
        }

        return array_values(array_unique($tables));
    }
}
