<?php

namespace Tests\Support;

use App\Models\User;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

final class SelectionUiLedger
{
    /** @var array<string, string> */
    private const TABLES = ['users' => 'id', 'roles' => 'id', 'permissions' => 'id', 'users_roles' => 'user_id, role_id',
        'users_permissions' => 'user_id, permission_id', 'roles_permissions' => 'role_id, permission_id', 'activity_log' => 'id',
        'clients' => 'id', 'timeline_comments' => 'id', 'uploadeds' => 'id', 'zip_requests' => 'id', 'exports' => 'id',
        'notifications' => 'id', 'app_notifications' => 'id', 'personal_access_tokens' => 'id', 'jobs' => 'id', 'failed_jobs' => 'id',
        'app_settings' => 'id', 'password_reset_tokens' => 'email'];

    /** @return array<string, list<array<string, mixed>>> */
    public static function rows(PDO $pdo): array
    {
        $rows = [];
        foreach (self::TABLES as $table => $order) {
            $rows[$table] = $pdo->query('SELECT * FROM '.$table.' ORDER BY '.$order)->fetchAll(PDO::FETCH_ASSOC);
        }

        return $rows;
    }

    /** @param array<string, mixed> $settings */
    public static function initialize(array $settings, PDO $pdo, string $timezone): string
    {
        $created = time();
        $baseline = ['owner' => $settings['token'], 'created_at' => $created, 'expires_at' => $created + 1800, 'timezone' => $timezone, 'rows' => self::rows($pdo)];
        $bytes = json_encode($baseline, JSON_THROW_ON_ERROR);
        self::create($settings['path'].'/selection-baseline.json', $bytes);
        self::create($settings['path'].'/selection-ledger.json', self::signed(['created_at' => $baseline['created_at'], 'receipts' => [], 'logs' => []], $settings['token']));

        return hash('sha256', $bytes);
    }

    /** @param array<string, mixed> $manifest
     * @return array<string, mixed>
     */
    public static function current(array $manifest): array
    {
        $baseline = self::baseline($manifest);
        $wrapper = json_decode(self::read($manifest['settings']['path'].'/selection-ledger.json', 131072), true, 32, JSON_THROW_ON_ERROR);
        if (! is_array($wrapper) || array_keys($wrapper) !== ['data', 'mac'] || ! is_array($wrapper['data']) || ! is_string($wrapper['mac'])
            || ! hash_equals(hash_hmac('sha256', json_encode($wrapper['data'], JSON_THROW_ON_ERROR), $manifest['settings']['token']), $wrapper['mac'])) {
            throw new RuntimeException('Selection UI ledger signature or shape changed.');
        }
        self::assertLedger($baseline, $wrapper['data'], $manifest['selection_ui']);

        return $wrapper['data'];
    }

    /** @param array<string, mixed> $manifest */
    public static function assertCurrent(array $manifest, PDO $pdo): void
    {
        self::assertSnapshot(self::baseline($manifest), self::current($manifest), self::rows($pdo), $manifest['selection_ui']);
    }

    /** @param array<string, mixed> $manifest
     * @param  array<string, mixed>  $before
     * @param  list<string>  $announced
     */
    public static function finish(array $manifest, PDO $pdo, array $before, array $announced, Response $response, ?int $actor): void
    {
        if (self::current($manifest) !== $before) {
            throw new RuntimeException('Selection UI ledger changed during a request.');
        }
        if ($announced === [] || $response->getStatusCode() !== 200) {
            self::assertCurrent($manifest, $pdo);

            return;
        }
        $fixture = $manifest['selection_ui'];
        if ($actor !== $fixture['owner_id']) {
            throw new RuntimeException('Only the fixed selection UI owner may commit a deletion receipt.');
        }
        $expected = array_values(array_filter(array_column($fixture['targets'], 'uuid'), fn (string $uuid): bool => in_array($uuid, $announced, true)));
        if (count($expected) !== count($announced) || count(array_unique($announced)) !== count($announced)) {
            throw new RuntimeException('Selection UI announced an unowned or duplicate target.');
        }
        $body = json_decode((string) $response->getContent(), true, 16, JSON_THROW_ON_ERROR);
        if ($body !== ['data' => ['deleted_ids' => $expected, 'deleted_count' => count($expected)]]) {
            throw new RuntimeException('Selection UI deletion receipt differs from the exact announced set.');
        }
        $rows = self::rows($pdo);
        $next = $before;
        $next['receipts'][] = ['ids' => $expected, 'at' => time()];
        $next['logs'] = $rows['activity_log'];
        self::assertSnapshot(self::baseline($manifest), $next, $rows, $fixture, $before);
        $path = $manifest['settings']['path'].'/selection-ledger.json';
        self::read($path, 131072);
        $bytes = self::signed($next, $manifest['settings']['token']);
        if (file_put_contents($path, $bytes, LOCK_EX) !== strlen($bytes)) {
            throw new RuntimeException('Selection UI could not persist its verified bounded ledger.');
        }
    }

    /** @param array<string, mixed> $baseline
     * @param  array<string, mixed>  $ledger
     * @param  array<string, list<array<string, mixed>>>  $current
     * @param  array<string, mixed>  $fixture
     * @param  array<string, mixed>|null  $previous
     */
    public static function assertSnapshot(array $baseline, array $ledger, array $current, array $fixture, ?array $previous = null): void
    {
        if ($previous !== null && (array_slice($ledger['logs'], 0, count($previous['logs'])) !== $previous['logs']
            || array_slice($ledger['receipts'], 0, count($previous['receipts'])) !== $previous['receipts'])) {
            throw new RuntimeException('Selection UI must preserve every previously committed receipt and audit row exactly.');
        }
        $deleted = self::assertLedger($baseline, $ledger, $fixture);
        $targetIds = [];
        foreach ($fixture['targets'] as $target) {
            if (in_array($target['uuid'], $deleted, true)) {
                $targetIds[] = $target['id'];
            }
        }
        $expected = $baseline['rows'];
        $expected['users'] = array_values(array_filter($expected['users'], fn (array $row): bool => ! in_array((int) $row['id'], $targetIds, true)));
        foreach (['users_roles', 'users_permissions'] as $table) {
            $expected[$table] = array_values(array_filter($expected[$table], fn (array $row): bool => ! in_array((int) $row['user_id'], $targetIds, true)));
        }
        $expected['activity_log'] = $ledger['logs'];
        if ($expected !== $current) {
            throw new RuntimeException('Selection UI changed rows outside its exact committed deletion ledger.');
        }
    }

    /** @param array<string, mixed> $manifest
     * @return array<string, mixed>
     */
    private static function baseline(array $manifest): array
    {
        $bytes = self::read($manifest['settings']['path'].'/selection-baseline.json', 262144);
        if (! hash_equals($manifest['selection_ui']['baseline_hash'], hash('sha256', $bytes))) {
            throw new RuntimeException('Selection UI immutable baseline hash changed.');
        }
        $baseline = json_decode($bytes, true, 32, JSON_THROW_ON_ERROR);
        if (! is_array($baseline) || array_keys($baseline) !== ['owner', 'created_at', 'expires_at', 'timezone', 'rows']
            || $baseline['owner'] !== $manifest['settings']['token'] || ! is_int($baseline['created_at']) || ! is_int($baseline['expires_at'])
            || $baseline['created_at'] > time() || $baseline['expires_at'] < time() || $baseline['expires_at'] - $baseline['created_at'] !== 1800
            || ! is_string($baseline['timezone']) || ! in_array($baseline['timezone'], timezone_identifiers_list(), true)
            || ! is_array($baseline['rows']) || array_keys($baseline['rows']) !== array_keys(self::TABLES)
            || count($baseline['rows']['users']) !== 64 || $baseline['rows']['activity_log'] !== []) {
            throw new RuntimeException('Selection UI baseline owner, lifetime or fixed source shape changed.');
        }
        $fixture = $manifest['selection_ui'];
        $users = array_column($baseline['rows']['users'], null, 'id');
        foreach (['owner_id' => 'admin', 'observer_id' => 'ordinary', 'maintainer_id' => 'developer'] as $key => $identity) {
            if (($users[$fixture[$key]]['email'] ?? null) !== $identity.'@authority.example.test') {
                throw new RuntimeException('Selection UI actor escaped its fixed synthetic identity.');
            }
        }
        foreach ($fixture['targets'] as $index => $target) {
            $row = $users[$target['id']] ?? null;
            if ($row === null || $row['uuid'] !== $target['uuid'] || $row['name'] !== 'UI004 target '.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT)) {
                throw new RuntimeException('Selection UI target UUID must belong to its fixed primary key and synthetic label.');
            }
        }
        foreach (array_diff(array_keys(self::TABLES), ['users', 'roles', 'permissions', 'users_roles', 'users_permissions', 'roles_permissions']) as $table) {
            if ($baseline['rows'][$table] !== []) {
                throw new RuntimeException('Selection UI baseline must have empty business and integration sources.');
            }
        }

        return $baseline;
    }

    /** @param array<string, mixed> $baseline
     * @param  array<string, mixed>  $ledger
     * @param  array<string, mixed>  $fixture
     * @return list<string>
     */
    private static function assertLedger(array $baseline, array $ledger, array $fixture): array
    {
        if (array_keys($ledger) !== ['created_at', 'receipts', 'logs'] || $ledger['created_at'] !== $baseline['created_at']
            || ! is_array($ledger['receipts']) || ! array_is_list($ledger['receipts']) || count($ledger['receipts']) > 61
            || ! is_array($ledger['logs']) || ! array_is_list($ledger['logs']) || count($ledger['logs']) > 61) {
            throw new RuntimeException('Selection UI ledger has an invalid finite shape.');
        }
        $deleted = [];
        $last = $baseline['created_at'];
        foreach ($ledger['receipts'] as $receipt) {
            if (! is_array($receipt) || array_keys($receipt) !== ['ids', 'at'] || ! is_array($receipt['ids']) || ! array_is_list($receipt['ids'])
                || $receipt['ids'] === [] || count($receipt['ids']) > 100 || count(array_filter($receipt['ids'], 'is_string')) !== count($receipt['ids'])
                || ! is_int($receipt['at']) || $receipt['at'] < $last || $receipt['at'] > time()
                || $receipt['at'] > $baseline['expires_at'] || array_diff($receipt['ids'], array_column($fixture['targets'], 'uuid')) !== []
                || array_intersect($deleted, $receipt['ids']) !== [] || count(array_unique($receipt['ids'], SORT_REGULAR)) !== count($receipt['ids'])) {
                throw new RuntimeException('Selection UI ledger contains an unowned, duplicate or untimed deletion receipt.');
            }
            $canonical = array_values(array_filter(array_column($fixture['targets'], 'uuid'), fn (string $uuid): bool => in_array($uuid, $receipt['ids'], true)));
            if ($receipt['ids'] !== $canonical) {
                throw new RuntimeException('Selection UI ledger receipts must preserve canonical primary-key order.');
            }
            $deleted = [...$deleted, ...$receipt['ids']];
            $last = $receipt['at'];
        }
        if (count($deleted) !== count($ledger['logs'])) {
            throw new RuntimeException('Selection UI requires exactly one real deletion audit per committed target.');
        }
        $users = array_column($baseline['rows']['users'], null, 'id');
        $logged = [];
        foreach ($ledger['logs'] as $log) {
            if (! is_array($log)) {
                throw new RuntimeException('Selection UI deletion audit must be a bounded row.');
            }
            $columns = array_keys($log);
            sort($columns);
            $required = ['id', 'log_name', 'description', 'subject_type', 'subject_id', 'causer_type', 'causer_id', 'properties', 'created_at', 'updated_at', 'event', 'batch_uuid'];
            sort($required);
            $user = $users[$log['subject_id'] ?? 0] ?? null;
            $date = is_string($log['created_at'] ?? null) ? DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $log['created_at'], new DateTimeZone($baseline['timezone'])) : false;
            $at = $date === false ? false : $date->getTimestamp();
            if ($columns !== $required || $user === null || ! in_array($user['uuid'], $deleted, true) || in_array($user['uuid'], $logged, true)
                || $log['event'] !== 'deleted' || $log['description'] !== 'deleted' || $log['log_name'] !== 'default'
                || $log['subject_type'] !== User::class || $log['causer_type'] !== User::class || (int) $log['causer_id'] !== $fixture['owner_id']
                || $log['batch_uuid'] !== null || (int) $log['id'] < 1 || $at === false || $at < $baseline['created_at'] - 1 || $at > time() + 1
                || $log['updated_at'] !== $log['created_at'] || ! is_string($log['properties'])) {
                throw new RuntimeException('Selection UI deletion audit escaped its exact owner, target, event or lifetime.');
            }
            $properties = json_decode($log['properties'], true, 8, JSON_THROW_ON_ERROR);
            $expectedAttributes = ['uuid' => $user['uuid'], 'name' => $user['name'], 'email' => $user['email'], 'must_change_password' => (bool) $user['must_change_password']];
            if (! is_array($properties) || array_keys($properties) !== ['old'] || ! is_array($properties['old'])) {
                throw new RuntimeException('Selection UI deletion audit properties have an unexpected shape.');
            }
            ksort($properties['old']);
            ksort($expectedAttributes);
            if ($properties['old'] !== $expectedAttributes) {
                throw new RuntimeException('Selection UI deletion audit properties differ from its fixed safe target attributes.');
            }
            $logged[] = $user['uuid'];
        }

        return $deleted;
    }

    private static function read(string $path, int $limit): string
    {
        clearstatcache(true, $path);
        $stat = lstat($path);
        if ($stat === false || is_link($path) || ($stat['mode'] & 0170000) !== 0100000 || $stat['nlink'] !== 1 || $stat['size'] < 2 || $stat['size'] > $limit) {
            throw new RuntimeException('Selection UI requires a bounded owned regular ledger file.');
        }
        $bytes = file_get_contents($path);
        if (! is_string($bytes) || strlen($bytes) !== $stat['size']) {
            throw new RuntimeException('Selection UI ledger file could not be read completely.');
        }

        return $bytes;
    }

    private static function create(string $path, string $bytes): void
    {
        $stream = fopen($path, 'x+b');
        if ($stream === false) {
            throw new RuntimeException('Selection UI ledger paths must be new owned files.');
        }
        try {
            if (fwrite($stream, $bytes) !== strlen($bytes) || ! fflush($stream)) {
                throw new RuntimeException('Selection UI could not initialize its finite ledger.');
            }
        } finally {
            fclose($stream);
        }
    }

    /** @param array<string, mixed> $data */
    private static function signed(array $data, string $token): string
    {
        return json_encode(['data' => $data, 'mac' => hash_hmac('sha256', json_encode($data, JSON_THROW_ON_ERROR), $token)], JSON_THROW_ON_ERROR);
    }
}
