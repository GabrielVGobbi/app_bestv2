<?php

namespace Tests;

use App\Models\User;
use App\Modules\Clients\Models\Client;
use App\Services\DevToolsService;
use Dotenv\Dotenv;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Laravel\Pulse\Pulse;
use Livewire\Mechanisms\HandleRequests\EndpointResolver;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\SelectionUiLedger;

/**
 * @phpstan-type ExportNotificationFixture array{owner_id: positive-int, owner_uuid: string, observer_id: positive-int, observer_uuid: string, client_id: positive-int, client_uuid: string, export_uuid: string, notification_uuid: string, file_name: string, file_sha256: string, file_size: positive-int, snapshots: array{users: string, clients: string, exports: string, notifications: string}}
 */
final class AuthorityHttpEnvironment
{
    public static function selectionUiEnabled(): bool
    {
        if (getenv('AUTHORITY_HTTP_SELECTION_UI_TESTS') !== '1' || getenv('AUTHORITY_HTTP_TESTS') !== '1' || getenv('AUTHORITY_MYSQL_TESTS') !== '1') {
            return false;
        }
        foreach (['TIMELINE_UI', 'UPLOADS_UI', 'NOTIFICATION_UI', 'EXPORT_NOTIFICATION', 'VERIFICATION', 'PASSWORD', 'INTEGRATIONS', 'ACTIVITY', 'USERS', 'EDITOR', 'PULSE', 'TIMELINE', 'UPLOADS', 'UPLOADS_TRANSPORT', 'DEVTOOLS'] as $profile) {
            if (getenv('AUTHORITY_HTTP_'.$profile.'_TESTS') === '1') {
                return false;
            }
        }

        return true;
    }

    public static function scopeSelectionUiResponseCookies(Response $response): void
    {
        if (! self::selectionUiEnabled()) {
            return;
        }
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === 'XSRF-TOKEN' && $cookie->getPath() === '/') {
                $response->headers->removeCookie($cookie->getName(), $cookie->getPath(), $cookie->getDomain());
                $response->headers->setCookie($cookie->withPath('/__ui_selection'));
            }
        }
    }

    /** @param array<string, mixed> $fixture */
    public static function validSelectionUiFixture(array $fixture): bool
    {
        $keys = ['owner_id', 'observer_id', 'maintainer_id', 'targets', 'baseline_hash', 'assets_hash', 'entry'];
        if (count($fixture) !== count($keys) || array_diff($keys, array_keys($fixture)) !== []
            || ! is_array($fixture['targets']) || ! array_is_list($fixture['targets']) || count($fixture['targets']) !== 61
            || ! is_string($fixture['entry']) || preg_match('~\Aassets/ui-selection-http-probe-[A-Za-z0-9_-]+\.js\z~', $fixture['entry']) !== 1) {
            return false;
        }
        $ids = $uuids = [];
        foreach (['owner_id', 'observer_id', 'maintainer_id'] as $key) {
            if (! is_int($fixture[$key]) || $fixture[$key] < 1) {
                return false;
            }
            $ids[] = $fixture[$key];
        }
        $lastTarget = 0;
        foreach ($fixture['targets'] as $target) {
            if (! is_array($target) || count($target) !== 2 || ! isset($target['id'], $target['uuid'])
                || ! is_int($target['id']) || $target['id'] <= $lastTarget || ! is_string($target['uuid'])
                || preg_match('/\A[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}\z/', $target['uuid']) !== 1) {
                return false;
            }
            $ids[] = $target['id'];
            $uuids[] = $target['uuid'];
            $lastTarget = $target['id'];
        }
        foreach (['baseline_hash', 'assets_hash'] as $key) {
            if (! is_string($fixture[$key]) || preg_match('/\A[a-f0-9]{64}\z/', $fixture[$key]) !== 1) {
                return false;
            }
        }

        return count(array_unique($ids)) === 64 && count(array_unique($uuids)) === 61;
    }

    /** @param array<string, mixed> $manifest */
    public static function assertSelectionUiFixture(array $manifest, \PDO $pdo): void
    {
        $fixture = $manifest['selection_ui'] ?? null;
        if (! self::selectionUiEnabled() || ! is_array($fixture) || ! self::validSelectionUiFixture($fixture)) {
            throw new RuntimeException('Selection UI requires its exclusive finite authority profile.');
        }
        $root = $manifest['settings']['path'].'/http-selection-public';
        if (! is_dir($root) || is_link($root) || realpath(dirname($root)) !== realpath($manifest['settings']['path'])
            || ! is_file($root.'/owner') || is_link($root.'/owner') || file_get_contents($root.'/owner') !== $manifest['settings']['token']
            || ! hash_equals($fixture['assets_hash'], self::uploadsUiAssetsHash($root.'/build'))) {
            throw new RuntimeException('Selection UI browser assets escaped their owned immutable build.');
        }
        SelectionUiLedger::assertCurrent($manifest, $pdo);
    }

    /** @param array<string, mixed> $fixture
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $body
     */
    private static function allowsSelectionUiRequest(string $path, string $method, array $fixture, array $query, ?array $body): bool
    {
        if (! self::selectionUiEnabled() || ! self::validSelectionUiFixture($fixture)) {
            return false;
        }
        if ($method === 'GET' && $body === null) {
            if ($path !== '/api/v1/users') {
                return $query === [] && in_array($path, ['/', '/login', '/__ui_selection', '/__ui_selection/login', '/sanctum/csrf-cookie', '/api/v1/auth/me', '/api/v1/notifications/unread', '/api/v1/notifications/unread-count'], true);
            }
            if (count($query) === 2 && isset($query['page'], $query['per_page'])) {
                return in_array($query['page'], ['1', '2', '3'], true) && $query['per_page'] === '30';
            }
            if (count($query) !== 3 || ($query['page'] ?? null) !== '1' || ($query['per_page'] ?? null) !== '100'
                || ! is_array($query['filter'] ?? null) || array_keys($query['filter']) !== ['uuid']
                || ! is_array($query['filter']['uuid']) || count($query['filter']['uuid']) !== 2
                || ($query['filter']['uuid']['operator'] ?? null) !== 'in') {
                return false;
            }

            return self::selectionUiTargetIds($query['filter']['uuid']['value'] ?? null, $fixture);
        }
        if ($query !== []) {
            return false;
        }
        if ($method === 'POST' && $path === '/login' && $body !== null) {
            return count($body) === 2 && in_array($body['email'] ?? null, ['admin@authority.example.test', 'ordinary@authority.example.test'], true)
                && ($body['password'] ?? null) === 'BrowserFixture-123!';
        }
        if ($method === 'POST' && $path === '/logout') {
            return $body === [];
        }

        return $method === 'DELETE' && $path === '/api/v1/users/bulk' && $body !== null && array_keys($body) === ['ids']
            && self::selectionUiTargetIds($body['ids'], $fixture);
    }

    /** @param array<string, mixed> $fixture */
    private static function selectionUiTargetIds(mixed $ids, array $fixture): bool
    {
        return is_array($ids) && array_is_list($ids) && count($ids) >= 1 && count($ids) <= 100
            && count(array_filter($ids, 'is_string')) === count($ids) && count(array_unique($ids, SORT_REGULAR)) === count($ids)
            && array_diff($ids, array_column($fixture['targets'], 'uuid')) === [];
    }

    public static function timelineUiEnabled(): bool
    {
        if (getenv('AUTHORITY_HTTP_SELECTION_UI_TESTS') === '1') {
            return false;
        }
        if (getenv('AUTHORITY_HTTP_TIMELINE_UI_TESTS') !== '1' || getenv('AUTHORITY_HTTP_TESTS') !== '1' || getenv('AUTHORITY_MYSQL_TESTS') !== '1') {
            return false;
        }
        foreach (['UPLOADS_UI', 'NOTIFICATION_UI', 'EXPORT_NOTIFICATION', 'VERIFICATION', 'PASSWORD', 'INTEGRATIONS', 'ACTIVITY', 'USERS', 'EDITOR', 'PULSE', 'TIMELINE', 'UPLOADS', 'UPLOADS_TRANSPORT', 'DEVTOOLS'] as $profile) {
            if (getenv('AUTHORITY_HTTP_'.$profile.'_TESTS') === '1') {
                return false;
            }
        }

        return true;
    }

    public static function scopeTimelineUiResponseCookies(Response $response): void
    {
        if (! self::timelineUiEnabled()) {
            return;
        }
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === 'XSRF-TOKEN' && $cookie->getPath() === '/') {
                $response->headers->removeCookie($cookie->getName(), $cookie->getPath(), $cookie->getDomain());
                $response->headers->setCookie($cookie->withPath('/__ui_timeline'));
            }
        }
    }

    /** @param array<string, mixed> $fixture */
    public static function validTimelineUiFixture(array $fixture): bool
    {
        $keys = ['owner_id', 'observer_id', 'client_id', 'client_uuid', 'comments', 'activities', 'snapshots', 'assets_hash', 'entry'];
        if (count($fixture) !== count($keys) || array_diff($keys, array_keys($fixture)) !== []
            || ! is_int($fixture['owner_id']) || $fixture['owner_id'] < 1 || ! is_int($fixture['observer_id']) || $fixture['observer_id'] < 1
            || $fixture['owner_id'] === $fixture['observer_id'] || ! is_int($fixture['client_id']) || $fixture['client_id'] < 1
            || ! is_string($fixture['client_uuid']) || preg_match('/\A[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}\z/', $fixture['client_uuid']) !== 1
            || ! is_array($fixture['snapshots']) || array_keys($fixture['snapshots']) !== ['users', 'clients', 'comments', 'activities', 'permissions', 'user_permissions', 'roles', 'user_roles', 'role_permissions']
            || ! is_string($fixture['entry']) || preg_match('~\Aassets/ui-timeline-http-probe-[A-Za-z0-9_-]+\.js\z~', $fixture['entry']) !== 1) {
            return false;
        }
        foreach (['comments' => 53, 'activities' => 54] as $key => $count) {
            if (! is_array($fixture[$key]) || ! array_is_list($fixture[$key]) || count($fixture[$key]) !== $count || count(array_unique($fixture[$key], SORT_REGULAR)) !== $count) {
                return false;
            }
            foreach ($fixture[$key] as $id) {
                if (! is_int($id) || $id < 1) {
                    return false;
                }
            }
        }
        foreach ([$fixture['assets_hash'], ...array_values($fixture['snapshots'])] as $hash) {
            if (! is_string($hash) || preg_match('/\A[a-f0-9]{64}\z/', $hash) !== 1) {
                return false;
            }
        }

        return true;
    }

    public static function timelineUiAssetsHash(string $root): string
    {
        return self::uploadsUiAssetsHash($root);
    }

    /** @return array<string, string> */
    public static function timelineUiSnapshots(\PDO $pdo): array
    {
        $snapshots = [];
        foreach (['users' => ['users', 'id'], 'clients' => ['clients', 'id'], 'comments' => ['timeline_comments', 'id'], 'activities' => ['activity_log', 'id'],
            'permissions' => ['permissions', 'id'], 'user_permissions' => ['users_permissions', 'user_id, permission_id'], 'roles' => ['roles', 'id'],
            'user_roles' => ['users_roles', 'user_id, role_id'], 'role_permissions' => ['roles_permissions', 'role_id, permission_id']] as $key => [$table, $order]) {
            $snapshots[$key] = hash('sha256', json_encode($pdo->query('SELECT * FROM '.$table.' ORDER BY '.$order)->fetchAll(\PDO::FETCH_ASSOC), JSON_THROW_ON_ERROR));
        }

        return $snapshots;
    }

    /** @param array<string, mixed> $manifest */
    public static function assertTimelineUiFixture(array $manifest, \PDO $pdo): void
    {
        $fixture = $manifest['timeline_ui'] ?? null;
        if (! self::timelineUiEnabled() || ! is_array($fixture) || ! self::validTimelineUiFixture($fixture)
            || self::timelineUiSnapshots($pdo) !== $fixture['snapshots']) {
            throw new RuntimeException('Timeline UI escaped its immutable finite source fixture.');
        }
        $root = $manifest['settings']['path'].'/http-timeline-public';
        if (! is_dir($root) || is_link($root) || realpath(dirname($root)) !== realpath($manifest['settings']['path'])
            || ! is_file($root.'/owner') || is_link($root.'/owner') || file_get_contents($root.'/owner') !== $manifest['settings']['token']
            || ! hash_equals($fixture['assets_hash'], self::timelineUiAssetsHash($root.'/build'))) {
            throw new RuntimeException('Timeline UI browser assets escaped their owned immutable build.');
        }
        foreach (['timeline_comments' => ['commentable_type', 'commentable_id', 'comments'], 'activity_log' => ['subject_type', 'subject_id', 'activities']] as $table => [$type, $id, $list]) {
            $rootFields = $table === 'timeline_comments' ? ', parent_id AS reply_to, deleted_at' : '';
            $rows = $pdo->query('SELECT id, '.$type.' AS parent_type, '.$id.' AS parent_id, created_at'.$rootFields.' FROM '.$table.' ORDER BY id')->fetchAll(\PDO::FETCH_ASSOC);
            if (array_map(fn (array $row): int => (int) $row['id'], $rows) !== $fixture[$list]) {
                throw new RuntimeException('Timeline UI requires exactly its 107 owned mixed roots.');
            }
            foreach ($rows as $row) {
                if ($row['parent_type'] !== Client::class || (int) $row['parent_id'] !== $fixture['client_id'] || $row['created_at'] !== '2026-06-09 10:00:00') {
                    throw new RuntimeException('Timeline UI rows escaped their fixed parent or tied timestamp.');
                }
                if ($table === 'timeline_comments' && ($row['reply_to'] !== null || $row['deleted_at'] !== null)) {
                    throw new RuntimeException('Timeline UI requires live root comments, never replies or soft-deleted rows.');
                }
            }
        }
        $parent = $pdo->prepare('SELECT uuid FROM clients WHERE id = ?');
        $parent->execute([$fixture['client_id']]);
        if ($parent->fetchColumn() !== $fixture['client_uuid']) {
            throw new RuntimeException('Timeline UI parent UUID must belong to its fixed primary key.');
        }
        foreach (['uploadeds', 'zip_requests', 'exports', 'notifications', 'app_notifications', 'personal_access_tokens', 'jobs', 'failed_jobs'] as $table) {
            if ((int) $pdo->query('SELECT COUNT(*) FROM '.$table)->fetchColumn() !== 0) {
                throw new RuntimeException('Timeline UI must remain read-only without integration artifacts.');
            }
        }
    }

    /** @param array<string, mixed> $fixture @param array<string, mixed> $query @param array<string, mixed>|null $body */
    private static function allowsTimelineUiRequest(string $path, string $method, array $fixture, array $query, ?array $body): bool
    {
        if (! self::timelineUiEnabled() || ! self::validTimelineUiFixture($fixture)) {
            return false;
        }
        if ($method === 'GET' && $body === null) {
            if ($path !== '/api/v1/timeline/client/'.$fixture['client_id']) {
                return $query === [] && in_array($path, ['/', '/login', '/__ui_timeline', '/__ui_timeline/login', '/sanctum/csrf-cookie', '/api/v1/auth/me', '/api/v1/notifications/unread', '/api/v1/notifications/unread-count'], true);
            }
            foreach ($query as $key => $value) {
                if (! is_string($value) || ! match ($key) {
                    'limit' => $value === '50',
                    'cursor' => strlen($value) <= 2048 && preg_match('/\A[A-Za-z0-9+\/=]+\z/', $value) === 1,
                    default => false,
                }) {
                    return false;
                }
            }

            return true;
        }
        if ($method !== 'POST' || $query !== []) {
            return false;
        }
        if ($path === '/logout') {
            return $body === [];
        }

        return $path === '/login' && $body !== null && count($body) === 2
            && in_array($body['email'] ?? null, ['admin@authority.example.test', 'ordinary@authority.example.test'], true)
            && ($body['password'] ?? null) === 'BrowserFixture-123!';
    }

    public static function scopeUploadsUiResponseCookies(Response $response): void
    {
        if (! self::uploadsUiEnabled()) {
            return;
        }
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() !== 'XSRF-TOKEN' || $cookie->getPath() !== '/') {
                continue;
            }
            $response->headers->removeCookie($cookie->getName(), $cookie->getPath(), $cookie->getDomain());
            $response->headers->setCookie($cookie->withPath('/__ui_uploads'));
        }
    }

    public static function uploadsUiEnabled(): bool
    {
        if (getenv('AUTHORITY_HTTP_SELECTION_UI_TESTS') === '1') {
            return false;
        }
        if (getenv('AUTHORITY_HTTP_TIMELINE_UI_TESTS') === '1') {
            return false;
        }
        if (getenv('AUTHORITY_HTTP_UPLOADS_UI_TESTS') !== '1' || getenv('AUTHORITY_HTTP_TESTS') !== '1' || getenv('AUTHORITY_MYSQL_TESTS') !== '1') {
            return false;
        }
        foreach (['NOTIFICATION_UI', 'EXPORT_NOTIFICATION', 'VERIFICATION', 'PASSWORD', 'INTEGRATIONS', 'ACTIVITY', 'USERS', 'EDITOR', 'PULSE', 'TIMELINE', 'UPLOADS', 'UPLOADS_TRANSPORT', 'DEVTOOLS'] as $profile) {
            if (getenv('AUTHORITY_HTTP_'.$profile.'_TESTS') === '1') {
                return false;
            }
        }

        return true;
    }

    /** @param array<string, mixed> $fixture */
    public static function validUploadsUiFixture(array $fixture): bool
    {
        $keys = ['owner_id', 'observer_id', 'client_id', 'documents', 'receipts', 'snapshots', 'assets_hash', 'entry'];
        if (count($fixture) !== count($keys) || array_diff($keys, array_keys($fixture)) !== []
            || ! is_int($fixture['owner_id']) || $fixture['owner_id'] < 1 || ! is_int($fixture['observer_id']) || $fixture['observer_id'] < 1
            || $fixture['owner_id'] === $fixture['observer_id'] || ! is_int($fixture['client_id']) || $fixture['client_id'] < 1
            || ! is_array($fixture['snapshots']) || array_keys($fixture['snapshots']) !== ['users', 'clients', 'uploadeds', 'notifications', 'exports']
            || ! is_string($fixture['entry']) || preg_match('~\Aassets/ui-uploads-http-probe-[A-Za-z0-9_-]+\.js\z~', $fixture['entry']) !== 1) {
            return false;
        }
        foreach (['documents' => 63, 'receipts' => 7] as $key => $count) {
            if (! is_array($fixture[$key]) || ! array_is_list($fixture[$key]) || count($fixture[$key]) !== $count) {
                return false;
            }
            foreach ($fixture[$key] as $id) {
                if (! is_int($id) || $id < 1) {
                    return false;
                }
            }
        }
        if (count(array_unique([...$fixture['documents'], ...$fixture['receipts']])) !== 70) {
            return false;
        }
        foreach ([$fixture['assets_hash'], ...array_values($fixture['snapshots'])] as $hash) {
            if (! is_string($hash) || preg_match('/\A[a-f0-9]{64}\z/', $hash) !== 1) {
                return false;
            }
        }

        return true;
    }

    /** Hash only finite, regular, non-linked owned build files; no HTML or arbitrary entry points. */
    public static function uploadsUiAssetsHash(string $root): string
    {
        $canonical = realpath($root);
        if ($canonical === false || is_link($root) || ! is_dir($root)) {
            throw new RuntimeException('Uploads UI requires a regular owned build directory.');
        }
        $hashes = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isLink() || ! $file->isFile() || $file->getSize() > 20000000 || count($hashes) >= 400
                || ! in_array(strtolower($file->getExtension()), ['js', 'css', 'json', 'svg', 'png', 'woff', 'woff2', 'ttf'], true)
                || ! str_starts_with(str_replace('\\', '/', $file->getRealPath()), str_replace('\\', '/', $canonical).'/')) {
                throw new RuntimeException('An uploads UI build file escaped its finite regular assets.');
            }
            $relative = substr(str_replace('\\', '/', $file->getPathname()), strlen(str_replace('\\', '/', $root)) + 1);
            $hashes[$relative] = hash_file('sha256', $file->getPathname());
        }
        if ($hashes === []) {
            throw new RuntimeException('Uploads UI assets must be built before starting the profile.');
        }
        ksort($hashes);

        return hash('sha256', json_encode($hashes, JSON_THROW_ON_ERROR));
    }

    /** @return array<string, string> */
    public static function uploadsUiSnapshots(\PDO $pdo): array
    {
        $snapshots = ['users' => self::usersReadSnapshot($pdo)];
        foreach (['clients', 'uploadeds', 'notifications', 'exports'] as $table) {
            $snapshots[$table] = hash('sha256', json_encode($pdo->query('SELECT * FROM '.$table.' ORDER BY id')->fetchAll(\PDO::FETCH_ASSOC), JSON_THROW_ON_ERROR));
        }

        return $snapshots;
    }

    /** @param array<string, mixed> $manifest */
    public static function assertUploadsUiFixture(array $manifest, \PDO $pdo): void
    {
        $fixture = $manifest['uploads_ui'] ?? null;
        if (! self::uploadsUiEnabled() || ! is_array($fixture) || ! self::validUploadsUiFixture($fixture)
            || self::uploadsUiSnapshots($pdo) !== $fixture['snapshots']) {
            throw new RuntimeException('Uploads UI escaped its immutable finite source fixture.');
        }
        $root = $manifest['settings']['path'];
        foreach (['http-ui-public', 'http-ui-files', 'http-ui-zips'] as $directory) {
            if (is_link($root.'/'.$directory) || ! is_dir($root.'/'.$directory)
                || is_link($root.'/'.$directory.'/owner') || file_get_contents($root.'/'.$directory.'/owner') !== $manifest['settings']['token']) {
                throw new RuntimeException('Uploads UI directories must retain their ownership marker.');
            }
        }
        if (! hash_equals($fixture['assets_hash'], self::uploadsUiAssetsHash($root.'/http-ui-public/build'))) {
            throw new RuntimeException('Uploads UI browser assets changed after startup.');
        }
        $rows = $pdo->query('SELECT * FROM uploadeds ORDER BY id')->fetchAll(\PDO::FETCH_ASSOC);
        if (count($rows) !== 71) {
            throw new RuntimeException('Uploads UI requires exactly seventy owned files and one foreign-parent control.');
        }
        $paths = [];
        foreach ($rows as $row) {
            $path = $root.'/http-ui-files/'.$row['path'];
            if ($row['disk'] !== 'authority_ui_sources' || preg_match('~\Aui002/(?:documents|receipts|foreign)-[0-9]+\.txt\z~', $row['path']) !== 1
                || is_link($root.'/http-ui-files/ui002') || is_link($path) || ! is_file($path)
                || ! str_starts_with(str_replace('\\', '/', realpath($path)), str_replace('\\', '/', realpath($root.'/http-ui-files')).'/')
                || hash_file('sha256', $path) !== $row['file_hash'] || filesize($path) !== (int) $row['size']) {
                throw new RuntimeException('Uploads UI private bytes no longer match their immutable rows.');
            }
            $id = (int) $row['id'];
            if ((int) $row['user_id'] !== $fixture['owner_id'] || $row['parentable_type'] !== Client::class || $row['tag'] !== 'evidence'
                || (in_array($id, $fixture['documents'], true) && ($row['collection'] !== 'documents' || (int) $row['parentable_id'] !== $fixture['client_id']))
                || (in_array($id, $fixture['receipts'], true) && ($row['collection'] !== 'receipts' || (int) $row['parentable_id'] !== $fixture['client_id']))
                || (! in_array($id, [...$fixture['documents'], ...$fixture['receipts']], true) && ($row['collection'] !== 'foreign' || (int) $row['parentable_id'] === $fixture['client_id']))) {
                throw new RuntimeException('Uploads UI row identities escaped their fixed parent and collections.');
            }
            $paths[] = $row['path'];
        }
        $actual = (new Filesystem)->allFiles($root.'/http-ui-files');
        if (count($actual) !== count($paths) + 1) {
            throw new RuntimeException('Uploads UI source files escaped their finite paths.');
        }
        if ((int) $pdo->query('SELECT COUNT(*) FROM app_notifications')->fetchColumn() !== 0
            || (int) $pdo->query('SELECT COUNT(*) FROM personal_access_tokens')->fetchColumn() !== 0) {
            throw new RuntimeException('Uploads UI must not create notifications or API tokens.');
        }
        $zips = $pdo->query('SELECT user_id, file_ids, file_count, label, status FROM zip_requests ORDER BY id')->fetchAll(\PDO::FETCH_ASSOC);
        if (count($zips) > 3) {
            throw new RuntimeException('Uploads UI permits at most three finite archive requests.');
        }
        foreach ($zips as $zip) {
            $ids = json_decode($zip['file_ids'], true, 512, JSON_THROW_ON_ERROR);
            if ((int) $zip['user_id'] !== $fixture['owner_id'] || ! is_array($ids) || ! array_is_list($ids) || $ids === [] || count($ids) > 200
                || count(array_unique($ids)) !== count($ids) || count($ids) !== (int) $zip['file_count']
                || array_diff($ids, [...$fixture['documents'], ...$fixture['receipts']]) !== []
                || ! in_array($zip['label'], ['Documentos', 'Recibos', 'UI002 HTTP'], true)
                || ! in_array($zip['status'], ['pending', 'processing', 'ready', 'failed', 'expired'], true)) {
                throw new RuntimeException('A ZIP request escaped the uploads UI owner or bounded sources.');
            }
        }
    }

    /** @param array<string, mixed> $fixture @param array<string, mixed> $query @param array<string, mixed>|null $body */
    public static function allowsUploadsUiRequest(string $path, string $method, array $fixture, array $query = [], ?array $body = null): bool
    {
        if (! self::uploadsUiEnabled() || ! self::validUploadsUiFixture($fixture)) {
            return false;
        }
        if ($method === 'GET' && $body === null) {
            if ($path === '/api/v1/uploads') {
                if (($query['parentable_type'] ?? null) !== 'client' || ($query['parentable_id'] ?? null) !== (string) $fixture['client_id']) {
                    return false;
                }
                foreach ($query as $key => $value) {
                    if (! is_string($value) || ! match ($key) {
                        'parentable_type', 'parentable_id' => true,
                        'collection' => in_array($value, ['documents', 'receipts'], true),
                        'tag' => $value === 'evidence',
                        'search' => in_array($value, ['', 'needle', 'documents-1.txt', '100% a_b'], true),
                        'per_page' => $value === '50',
                        'page' => in_array($value, ['1', '2'], true),
                        default => false,
                    }) {
                        return false;
                    }
                }

                return true;
            }
            if ($path === '/api/v1/uploads/tags') {
                return $query === ['parentable_type' => 'client'];
            }
            if ($query !== []) {
                return false;
            }
            if (in_array($path, ['/', '/login', '/sanctum/csrf-cookie', '/__ui_uploads', '/__ui_uploads/login', '/api/v1/auth/me', '/api/v1/notifications/unread', '/api/v1/notifications/unread-count'], true)) {
                return true;
            }
            if (preg_match('~\A/api/v1/uploads/zip-requests/[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}(?:/download)?\z~', $path) === 1) {
                return true;
            }
            foreach ([...$fixture['documents'], ...$fixture['receipts']] as $id) {
                if (in_array($path, ['/uploads/'.$id.'/preview', '/uploads/'.$id.'/download'], true)) {
                    return true;
                }
            }

            return false;
        }
        if ($method !== 'POST' || $query !== [] || $body === null) {
            return false;
        }
        if ($path === '/login') {
            if (isset($body['remember']) && in_array($body['remember'], [false, '0'], true)) {
                unset($body['remember']);
            }

            return count($body) === 2 && in_array($body['email'] ?? null, ['admin@authority.example.test', 'ordinary@authority.example.test'], true) && ($body['password'] ?? null) === 'BrowserFixture-123!';
        }
        if ($path === '/logout') {
            return $body === [];
        }
        if ($path !== '/api/v1/uploads/zip-requests' || array_diff(array_keys($body), ['ids', 'all_matching', 'label']) !== []
            || ! in_array($body['label'] ?? 'UI002 HTTP', ['Documentos', 'Recibos', 'UI002 HTTP'], true)
            || array_key_exists('ids', $body) === array_key_exists('all_matching', $body)) {
            return false;
        }
        if (array_key_exists('ids', $body)) {
            $ids = $body['ids'];

            return is_array($ids) && array_is_list($ids) && $ids !== [] && count($ids) <= 200 && count(array_unique($ids)) === count($ids)
                && count(array_filter($ids, 'is_int')) === count($ids) && array_diff($ids, [...$fixture['documents'], ...$fixture['receipts']]) === [];
        }
        $filters = $body['all_matching'];
        if (! is_array($filters) || ($filters['parentable_id'] ?? null) !== $fixture['client_id']
            || ! in_array($filters['collection'] ?? null, ['documents', 'receipts'], true)) {
            return false;
        }
        $filters['parentable_id'] = (string) $filters['parentable_id'];

        return self::allowsUploadsUiRequest('/api/v1/uploads', 'GET', $fixture, $filters)
            && array_intersect(array_keys($filters), ['page', 'per_page']) === [];
    }

    public static function notificationUiEnabled(): bool
    {
        if (getenv('AUTHORITY_HTTP_SELECTION_UI_TESTS') === '1') {
            return false;
        }
        if (getenv('AUTHORITY_HTTP_TIMELINE_UI_TESTS') === '1') {
            return false;
        }
        if (getenv('AUTHORITY_HTTP_UPLOADS_UI_TESTS') === '1') {
            return false;
        }
        if (getenv('AUTHORITY_HTTP_NOTIFICATION_UI_TESTS') !== '1' || getenv('AUTHORITY_HTTP_TESTS') !== '1' || getenv('AUTHORITY_MYSQL_TESTS') !== '1') {
            return false;
        }
        foreach (['EXPORT_NOTIFICATION', 'VERIFICATION', 'PASSWORD', 'INTEGRATIONS', 'ACTIVITY', 'USERS', 'EDITOR', 'PULSE', 'TIMELINE', 'UPLOADS', 'UPLOADS_TRANSPORT', 'DEVTOOLS'] as $profile) {
            if (getenv('AUTHORITY_HTTP_'.$profile.'_TESTS') === '1') {
                return false;
            }
        }

        return true;
    }

    /** @param array<string, mixed> $fixture */
    public static function validNotificationUiFixture(array $fixture): bool
    {
        $keys = ['owner_id', 'owner_uuid', 'observer_id', 'observer_uuid', 'notification_uuids', 'observer_uuids', 'event_uuid', 'published', 'snapshots'];
        if (count($fixture) !== count($keys) || array_diff($keys, array_keys($fixture)) !== []
            || ! is_int($fixture['owner_id']) || $fixture['owner_id'] < 1 || ! is_int($fixture['observer_id']) || $fixture['observer_id'] < 1
            || $fixture['owner_id'] === $fixture['observer_id'] || ! is_bool($fixture['published'])
            || ! is_array($fixture['notification_uuids']) || ! array_is_list($fixture['notification_uuids']) || count($fixture['notification_uuids']) !== 32
            || ! is_array($fixture['observer_uuids']) || ! array_is_list($fixture['observer_uuids']) || count($fixture['observer_uuids']) !== 2
            || ! is_array($fixture['snapshots']) || array_keys($fixture['snapshots']) !== ['users', 'clients', 'exports', 'legacy', 'rows']) {
            return false;
        }
        $notificationUuids = [...$fixture['notification_uuids'], ...$fixture['observer_uuids'], $fixture['event_uuid']];
        foreach ([$fixture['owner_uuid'], $fixture['observer_uuid'], ...$notificationUuids] as $uuid) {
            if (! is_string($uuid) || preg_match('/\A[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}\z/', $uuid) !== 1) {
                return false;
            }
        }
        if ($fixture['owner_uuid'] === $fixture['observer_uuid'] || count(array_unique($notificationUuids)) !== 35
            || ! is_array($fixture['snapshots']['rows']) || count($fixture['snapshots']['rows']) !== 35
            || array_diff($notificationUuids, array_keys($fixture['snapshots']['rows'])) !== []) {
            return false;
        }
        foreach ([...array_intersect_key($fixture['snapshots'], array_flip(['users', 'clients', 'exports', 'legacy'])), ...array_values($fixture['snapshots']['rows'])] as $digest) {
            if (! is_string($digest) || preg_match('/\A[a-f0-9]{64}\z/', $digest) !== 1) {
                return false;
            }
        }

        return true;
    }

    /** @return array{users: string, clients: string, exports: string, legacy: string, rows: array<string, string>} */
    public static function notificationUiSnapshots(\PDO $pdo): array
    {
        $rows = [];
        foreach ($pdo->query('SELECT * FROM app_notifications ORDER BY id')->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            unset($row['read_at'], $row['updated_at']);
            $rows[$row['uuid']] = hash('sha256', json_encode($row, JSON_THROW_ON_ERROR));
        }

        return [
            'users' => self::usersReadSnapshot($pdo),
            'clients' => hash('sha256', json_encode($pdo->query('SELECT * FROM clients ORDER BY id')->fetchAll(\PDO::FETCH_ASSOC), JSON_THROW_ON_ERROR)),
            'exports' => hash('sha256', json_encode($pdo->query('SELECT * FROM exports ORDER BY id')->fetchAll(\PDO::FETCH_ASSOC), JSON_THROW_ON_ERROR)),
            'legacy' => hash('sha256', json_encode($pdo->query('SELECT * FROM notifications ORDER BY id')->fetchAll(\PDO::FETCH_ASSOC), JSON_THROW_ON_ERROR)),
            'rows' => $rows,
        ];
    }

    /** @param array<string, mixed> $manifest */
    public static function assertNotificationUiFixture(array $manifest, \PDO $pdo): void
    {
        $fixture = $manifest['notification_ui'] ?? null;
        if (! self::notificationUiEnabled() || ! is_array($fixture) || ! self::validNotificationUiFixture($fixture)) {
            throw new RuntimeException('The notification UI profile requires its finite owned fixture.');
        }
        $current = self::notificationUiSnapshots($pdo);
        if ((int) $pdo->query('SELECT COUNT(*) FROM exports')->fetchColumn() !== 0
            || (int) $pdo->query('SELECT COUNT(*) FROM clients')->fetchColumn() !== 0
            || (int) $pdo->query('SELECT COUNT(*) FROM notifications')->fetchColumn() !== 0) {
            throw new RuntimeException('Notification UI must not create exports, clients or legacy notifications.');
        }
        foreach (['users', 'clients', 'exports', 'legacy'] as $table) {
            if (! hash_equals($fixture['snapshots'][$table], $current[$table])) {
                throw new RuntimeException('An immutable notification UI source changed.');
            }
        }
        foreach ($current['rows'] as $uuid => $digest) {
            if (! isset($fixture['snapshots']['rows'][$uuid]) || ! hash_equals($fixture['snapshots']['rows'][$uuid], $digest)) {
                throw new RuntimeException('Notification UI writes escaped their known UUIDs or mutable columns.');
            }
        }
        $event = $pdo->prepare('SELECT read_at FROM app_notifications WHERE uuid = ?');
        $event->execute([$fixture['event_uuid']]);
        $eventRow = $event->fetch(\PDO::FETCH_ASSOC);
        if (! $fixture['published'] && ($eventRow === false || $eventRow['read_at'] === null)) {
            throw new RuntimeException('The reserved UI event cannot be published or deleted before its stdin command.');
        }
        foreach (['owner' => 'admin', 'observer' => 'ordinary'] as $name => $email) {
            $actor = $pdo->prepare('SELECT id FROM users WHERE id = ? AND uuid = ? AND email = ? AND email_verified_at IS NOT NULL AND must_change_password = 0');
            $actor->execute([$fixture[$name.'_id'], $fixture[$name.'_uuid'], $email.'@authority.example.test']);
            if ($actor->fetchColumn() === false) {
                throw new RuntimeException('Notification UI requires its verified synthetic actors.');
            }
        }
    }

    private const INTEGRATION_WEBHOOKS = [
        'environment' => 'https://hooks.slack.test/services/SEC004/environment',
        'database' => 'https://hooks.slack.test/services/SEC004/database',
        'replacement' => 'https://hooks.slack.test/services/SEC004/replacement',
    ];

    private const TRANSPORT_UPLOAD_FILES = [
        'Café transporte.png' => ['sha256' => 'a24ee14097e9e0e191f75a1df7450dbcf5cab9466a1fab5f5a8d068f0e8216ae', 'size' => 136, 'extension' => 'png', 'mime' => 'image/png'],
        'Recuperação transporte.pdf' => ['sha256' => '605f64a75a8b224606c223ed7487ecd100a67662fa22fd9fcf9e5957b8d77bcd', 'size' => 597, 'extension' => 'pdf', 'mime' => 'application/pdf'],
    ];

    public static function exportNotificationEnabled(): bool
    {
        if (getenv('AUTHORITY_HTTP_SELECTION_UI_TESTS') === '1') {
            return false;
        }
        if (getenv('AUTHORITY_HTTP_TIMELINE_UI_TESTS') === '1') {
            return false;
        }
        if (getenv('AUTHORITY_HTTP_UPLOADS_UI_TESTS') === '1' || getenv('AUTHORITY_HTTP_NOTIFICATION_UI_TESTS') === '1') {
            return false;
        }
        if (getenv('AUTHORITY_HTTP_EXPORT_NOTIFICATION_TESTS') !== '1' || getenv('AUTHORITY_HTTP_TESTS') !== '1' || getenv('AUTHORITY_MYSQL_TESTS') !== '1') {
            return false;
        }
        foreach (['VERIFICATION', 'PASSWORD', 'INTEGRATIONS', 'ACTIVITY', 'USERS', 'EDITOR', 'PULSE', 'TIMELINE', 'UPLOADS', 'UPLOADS_TRANSPORT', 'DEVTOOLS'] as $profile) {
            if (getenv('AUTHORITY_HTTP_'.$profile.'_TESTS') === '1') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $fixture
     *
     * @phpstan-assert-if-true ExportNotificationFixture $fixture
     */
    public static function validExportNotificationFixture(array $fixture): bool
    {
        $keys = ['owner_id', 'owner_uuid', 'observer_id', 'observer_uuid', 'client_id', 'client_uuid', 'export_uuid', 'notification_uuid', 'file_name', 'file_sha256', 'file_size', 'snapshots'];
        if (count($fixture) !== count($keys) || array_diff($keys, array_keys($fixture)) !== []) {
            return false;
        }
        foreach (['owner_id', 'observer_id', 'client_id', 'file_size'] as $key) {
            if (! is_int($fixture[$key]) || $fixture[$key] < 1) {
                return false;
            }
        }
        foreach (['owner_uuid', 'observer_uuid', 'client_uuid', 'export_uuid', 'notification_uuid'] as $key) {
            if (! is_string($fixture[$key]) || preg_match('/\A[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}\z/', $fixture[$key]) !== 1) {
                return false;
            }
        }
        if ($fixture['owner_id'] === $fixture['observer_id'] || $fixture['owner_uuid'] === $fixture['observer_uuid']
            || $fixture['file_name'] !== 'export-'.$fixture['export_uuid'].'.csv' || $fixture['file_size'] > 2048
            || ! is_string($fixture['file_sha256']) || preg_match('/\A[a-f0-9]{64}\z/', $fixture['file_sha256']) !== 1
            || ! is_array($fixture['snapshots']) || array_keys($fixture['snapshots']) !== ['users', 'clients', 'exports', 'notifications']) {
            return false;
        }
        foreach ($fixture['snapshots'] as $snapshot) {
            if (! is_string($snapshot) || preg_match('/\A[a-f0-9]{64}\z/', $snapshot) !== 1) {
                return false;
            }
        }

        return true;
    }

    /** @return array{users: string, clients: string, exports: string, notifications: string} */
    public static function exportNotificationSnapshots(\PDO $pdo): array
    {
        $notifications = $pdo->query('SELECT * FROM app_notifications ORDER BY id')->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($notifications as &$notification) {
            unset($notification['read_at'], $notification['updated_at']);
        }
        unset($notification);

        return [
            'users' => self::usersReadSnapshot($pdo),
            'clients' => hash('sha256', json_encode($pdo->query('SELECT * FROM clients ORDER BY id')->fetchAll(\PDO::FETCH_ASSOC), JSON_THROW_ON_ERROR)),
            'exports' => hash('sha256', json_encode($pdo->query('SELECT * FROM exports ORDER BY id')->fetchAll(\PDO::FETCH_ASSOC), JSON_THROW_ON_ERROR)),
            'notifications' => hash('sha256', json_encode($notifications, JSON_THROW_ON_ERROR)),
        ];
    }

    /** @param array<string, mixed> $settings */
    private static function assertExportNotificationDirectory(array $settings): string
    {
        self::assertOwnedDirectories($settings);
        $root = $settings['path'].'/http-export-files';
        if (! is_dir($root) || is_link($root) || ! is_file($root.'/owner') || is_link($root.'/owner')
            || file_get_contents($root.'/owner') !== $settings['token']) {
            throw new RuntimeException('Export notification storage must remain inside its owned private directory.');
        }

        return $root;
    }

    /** @param array<string, mixed> $manifest */
    public static function assertExportNotificationFixture(array $manifest, \PDO $pdo): void
    {
        $fixture = $manifest['export_notification'] ?? null;
        if (! self::exportNotificationEnabled() || ! is_array($fixture) || ! self::validExportNotificationFixture($fixture)
            || $fixture['snapshots'] !== self::exportNotificationSnapshots($pdo)) {
            throw new RuntimeException('The finite export notification fixture changed.');
        }
        $root = self::assertExportNotificationDirectory($manifest['settings']);
        $path = $root.'/exports/'.$fixture['export_uuid'].'.csv';
        if (! is_dir($root.'/exports') || is_link($root.'/exports') || ! is_file($path) || is_link($path)
            || realpath(dirname($path)) !== realpath($root.'/exports') || filesize($path) !== $fixture['file_size']
            || ! hash_equals($fixture['file_sha256'], (string) hash_file('sha256', $path))
            || count((new Filesystem)->allFiles($root)) !== 2) {
            throw new RuntimeException('The finite private export bytes changed.');
        }
        $exports = $pdo->query('SELECT * FROM exports ORDER BY id')->fetchAll(\PDO::FETCH_ASSOC);
        $notifications = $pdo->query('SELECT * FROM app_notifications ORDER BY id')->fetchAll(\PDO::FETCH_ASSOC);
        $clients = $pdo->query('SELECT id, uuid FROM clients ORDER BY id')->fetchAll(\PDO::FETCH_ASSOC);
        if (count($exports) !== 1 || count($notifications) !== 1 || count($clients) !== 1
            || (int) $clients[0]['id'] !== $fixture['client_id'] || $clients[0]['uuid'] !== $fixture['client_uuid']
            || $exports[0]['uuid'] !== $fixture['export_uuid'] || (int) $exports[0]['user_id'] !== $fixture['owner_id']
            || $exports[0]['status'] !== 'completed' || $exports[0]['model_class'] !== Client::class
            || $exports[0]['file_path'] !== 'exports/'.$fixture['export_uuid'].'.csv' || $exports[0]['file_name'] !== $fixture['file_name']
            || (int) $exports[0]['file_size'] !== $fixture['file_size'] || (int) $exports[0]['processed_records'] !== 1
            || $notifications[0]['uuid'] !== $fixture['notification_uuid'] || (int) $notifications[0]['recipient_id'] !== $fixture['owner_id']
            || $notifications[0]['category'] !== 'export' || $notifications[0]['type'] !== 'success'
            || $notifications[0]['title'] !== 'Exportação concluída' || $notifications[0]['body'] !== 'Sua exportação está pronta para download.'
            || $notifications[0]['actions'] !== null || $notifications[0]['link'] !== 'http://'.$manifest['host'].':'.$manifest['port'].'/exports/'.$fixture['export_uuid'].'/download') {
            throw new RuntimeException('The export completion must produce exactly one owner notification and one private file.');
        }
        foreach (['owner' => 'admin', 'observer' => 'ordinary'] as $name => $identity) {
            $actor = $pdo->prepare('SELECT id FROM users WHERE id = ? AND uuid = ? AND email = ? AND email_verified_at IS NOT NULL AND must_change_password = 0');
            $actor->execute([$fixture[$name.'_id'], $fixture[$name.'_uuid'], $identity.'@authority.example.test']);
            if ($actor->fetchColumn() === false) {
                throw new RuntimeException('Export notification actors must remain the verified synthetic identities.');
            }
        }
    }

    public static function verificationEnabled(): bool
    {
        if (getenv('AUTHORITY_HTTP_SELECTION_UI_TESTS') === '1') {
            return false;
        }
        if (getenv('AUTHORITY_HTTP_TIMELINE_UI_TESTS') === '1') {
            return false;
        }
        if (getenv('AUTHORITY_HTTP_UPLOADS_UI_TESTS') === '1' || getenv('AUTHORITY_HTTP_NOTIFICATION_UI_TESTS') === '1') {
            return false;
        }
        if (getenv('AUTHORITY_HTTP_VERIFICATION_TESTS') !== '1' || getenv('AUTHORITY_HTTP_TESTS') !== '1' || getenv('AUTHORITY_MYSQL_TESTS') !== '1') {
            return false;
        }
        foreach (['AUTHORITY_HTTP_EXPORT_NOTIFICATION_TESTS', 'AUTHORITY_HTTP_PASSWORD_TESTS', 'AUTHORITY_HTTP_INTEGRATIONS_TESTS', 'AUTHORITY_HTTP_ACTIVITY_TESTS', 'AUTHORITY_HTTP_USERS_TESTS', 'AUTHORITY_HTTP_EDITOR_TESTS', 'AUTHORITY_HTTP_PULSE_TESTS', 'AUTHORITY_HTTP_TIMELINE_TESTS', 'AUTHORITY_HTTP_UPLOADS_TESTS', 'AUTHORITY_HTTP_UPLOADS_TRANSPORT_TESTS', 'AUTHORITY_HTTP_DEVTOOLS_TESTS'] as $flag) {
            if (getenv($flag) === '1') {
                return false;
            }
        }

        return true;
    }

    /** @param array<string, mixed> $fixture */
    private static function validVerificationFixture(array $fixture): bool
    {
        if (count($fixture) !== 7 || ! isset($fixture['identities'], $fixture['users_snapshot'], $fixture['token_snapshots'], $fixture['notifications_snapshot'], $fixture['verified_at'], $fixture['signed_links'], $fixture['started_at'])
            || ! self::validPasswordFixture(array_intersect_key($fixture, array_flip(['identities', 'users_snapshot', 'token_snapshots', 'notifications_snapshot'])))
            || ! is_int($fixture['started_at']) || ! is_array($fixture['verified_at']) || ! is_array($fixture['signed_links'])
            || array_keys($fixture['verified_at']) !== ['developer', 'admin', 'ordinary', 'target']
            || array_keys($fixture['signed_links']) !== ['ordinary', 'target']) {
            return false;
        }
        foreach ($fixture['verified_at'] as $name => $timestamp) {
            if (in_array($name, ['ordinary', 'target'], true) ? $timestamp !== null : (! is_string($timestamp) || strtotime($timestamp) === false)) {
                return false;
            }
        }
        foreach ($fixture['signed_links'] as $name => $links) {
            if (! is_array($links) || array_keys($links) !== ($name === 'ordinary' ? ['valid', 'expired', 'invalid_hash', 'invalid_signature', 'changed_email'] : ['valid', 'expired', 'invalid_hash', 'invalid_signature'])) {
                return false;
            }
            foreach ($links as $kind => $link) {
                $hash = sha1(match ($kind) {
                    'invalid_hash' => 'old-'.$name.'@authority.example.test',
                    'changed_email' => 'ordinary.changed@authority.example.test',
                    default => $name.'@authority.example.test',
                });
                if (! is_array($link) || array_keys($link) !== ['path', 'query']
                    || $link['path'] !== '/email/verify/'.$fixture['identities'][$name]['id'].'/'.$hash
                    || ! is_array($link['query']) || array_keys($link['query']) !== ['expires', 'signature']
                    || ! is_string($link['query']['expires']) || ! ctype_digit($link['query']['expires'])
                    || ! is_string($link['query']['signature']) || preg_match('/\A[a-f0-9]{64}\z/', $link['query']['signature']) !== 1) {
                    return false;
                }
            }
        }

        return true;
    }

    public static function verificationUsersSnapshot(\PDO $pdo): string
    {
        $rows = [];
        foreach (['users' => 'id', 'roles' => 'id', 'permissions' => 'id', 'users_roles' => 'user_id, role_id', 'users_permissions' => 'user_id, permission_id', 'roles_permissions' => 'role_id, permission_id'] as $table => $order) {
            $rows[$table] = $pdo->query('SELECT * FROM '.$table.' ORDER BY '.$order)->fetchAll(\PDO::FETCH_ASSOC);
        }
        foreach ($rows['users'] as &$row) {
            unset($row['email'], $row['email_verified_at'], $row['password'], $row['must_change_password'], $row['updated_at'], $row['remember_token']);
        }
        unset($row);

        return hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR));
    }

    /** @param array<string, mixed> $manifest */
    public static function assertVerificationFixture(array $manifest, \PDO $pdo): void
    {
        $fixture = $manifest['verification_flow'] ?? null;
        if (! self::verificationEnabled() || ! is_array($fixture) || ! self::validVerificationFixture($fixture)
            || ! hash_equals($fixture['users_snapshot'], self::verificationUsersSnapshot($pdo))
            || ! hash_equals($fixture['notifications_snapshot'], self::passwordNotificationsSnapshot($pdo))) {
            throw new RuntimeException('The finite verification fixture or immutable snapshot changed.');
        }
        $users = $pdo->query('SELECT * FROM users ORDER BY id')->fetchAll(\PDO::FETCH_UNIQUE | \PDO::FETCH_ASSOC);
        $tokens = array_column($pdo->query('SELECT * FROM personal_access_tokens ORDER BY id')->fetchAll(\PDO::FETCH_ASSOC), null, 'id');
        if (count($users) !== 4) {
            throw new RuntimeException('Verification requires exactly four owned actors.');
        }
        foreach ($fixture['identities'] as $name => $identity) {
            $user = $users[$identity['id']] ?? null;
            if ($user === null || $user['uuid'] !== $identity['uuid']
                || ! in_array($user['email'], $name === 'ordinary' ? ['ordinary@authority.example.test', 'ordinary.changed@authority.example.test'] : [$name.'@authority.example.test'], true)
                || ($user['remember_token'] !== null && (! is_string($user['remember_token']) || preg_match('/\A[A-Za-z0-9]{60}\z/', $user['remember_token']) !== 1))) {
                throw new RuntimeException('Verification lost its fixed synthetic identity.');
            }
            $verified = $user['email_verified_at'];
            $verifiedTimestamp = is_string($verified) ? (new \DateTimeImmutable($verified, new \DateTimeZone('America/Sao_Paulo')))->getTimestamp() : null;
            if (in_array($name, ['developer', 'admin'], true) ? $verified !== $fixture['verified_at'][$name]
                : ($verified !== null && ($verifiedTimestamp === null || $verifiedTimestamp < $fixture['started_at'] - 1 || $verifiedTimestamp > time() + 1))) {
                throw new RuntimeException('Verification timestamp escaped its finite states.');
            }
            $initial = password_verify('BrowserFixture-123!', $user['password']);
            $completed = $name === 'target' && $verified !== null && password_verify('PasswordFixture-456!', $user['password']);
            if ((! $initial && ! $completed) || (int) $user['must_change_password'] !== ($name === 'target' && $initial ? 1 : 0)) {
                throw new RuntimeException('Verification changed an unrelated password or pending flag.');
            }
            $tokenFixture = $fixture['token_snapshots'][$name];
            $token = $tokens[$tokenFixture['id']] ?? null;
            if ($completed) {
                if ($token !== null) {
                    throw new RuntimeException('Forced completion must revoke the owned target token.');
                }
            } elseif ($token === null || $token['tokenable_type'] !== User::class || (int) $token['tokenable_id'] !== $identity['id']
                || ! hash_equals($tokenFixture['snapshot'], self::passwordTokenSnapshot($token))) {
                throw new RuntimeException('Verification changed an owned token unexpectedly.');
            }
            unset($tokens[$tokenFixture['id']]);
        }
        if ($tokens !== []) {
            throw new RuntimeException('An extra token escaped the verification fixture.');
        }
    }

    /** @param array<string, mixed> $body */
    private static function allowsVerificationBody(string $path, array $body): bool
    {
        if ($path === '/settings/profile') {
            return count($body) === 2 && ($body['name'] ?? null) === 'Browser Fixture Ordinary' && ($body['email'] ?? null) === 'ordinary.changed@authority.example.test';
        }
        if ($path === '/login' && ($body['email'] ?? null) === 'ordinary.changed@authority.example.test') {
            $body['email'] = 'ordinary@authority.example.test';
        }
        if (in_array($path, ['/logout', '/email/verification-notification'], true)) {
            return $body === [];
        }
        if ($path === '/forgot-password') {
            return array_keys($body) === ['email'] && in_array($body['email'], ['ordinary@authority.example.test', 'target@authority.example.test'], true);
        }

        return self::allowsPasswordBody($path, $body);
    }

    public static function passwordEnabled(): bool
    {
        if (getenv('AUTHORITY_HTTP_SELECTION_UI_TESTS') === '1') {
            return false;
        }
        if (getenv('AUTHORITY_HTTP_TIMELINE_UI_TESTS') === '1') {
            return false;
        }
        if (getenv('AUTHORITY_HTTP_UPLOADS_UI_TESTS') === '1' || getenv('AUTHORITY_HTTP_NOTIFICATION_UI_TESTS') === '1') {
            return false;
        }
        if (getenv('AUTHORITY_HTTP_PASSWORD_TESTS') !== '1' || getenv('AUTHORITY_HTTP_VERIFICATION_TESTS') === '1' || getenv('AUTHORITY_HTTP_TESTS') !== '1' || getenv('AUTHORITY_MYSQL_TESTS') !== '1') {
            return false;
        }
        foreach (['AUTHORITY_HTTP_EXPORT_NOTIFICATION_TESTS', 'AUTHORITY_HTTP_INTEGRATIONS_TESTS', 'AUTHORITY_HTTP_ACTIVITY_TESTS', 'AUTHORITY_HTTP_USERS_TESTS', 'AUTHORITY_HTTP_EDITOR_TESTS', 'AUTHORITY_HTTP_PULSE_TESTS', 'AUTHORITY_HTTP_TIMELINE_TESTS', 'AUTHORITY_HTTP_UPLOADS_TESTS', 'AUTHORITY_HTTP_UPLOADS_TRANSPORT_TESTS', 'AUTHORITY_HTTP_DEVTOOLS_TESTS'] as $flag) {
            if (getenv($flag) === '1') {
                return false;
            }
        }

        return true;
    }

    /** @param array<string, mixed> $fixture */
    private static function validPasswordFixture(array $fixture): bool
    {
        if (count($fixture) !== 4 || ! isset($fixture['identities'], $fixture['users_snapshot'], $fixture['token_snapshots'], $fixture['notifications_snapshot'])
            || ! is_array($fixture['identities']) || ! self::validUsersReadFixture(['identities' => $fixture['identities'], 'snapshot' => $fixture['users_snapshot']])
            || ! is_string($fixture['notifications_snapshot']) || preg_match('/\A[a-f0-9]{64}\z/', $fixture['notifications_snapshot']) !== 1
            || ! is_array($fixture['token_snapshots']) || count($fixture['token_snapshots']) !== 4
            || array_diff(array_keys($fixture['token_snapshots']), ['developer', 'admin', 'ordinary', 'target']) !== []) {
            return false;
        }
        $ids = [];
        foreach ($fixture['token_snapshots'] as $token) {
            if (! is_array($token) || count($token) !== 2 || ! isset($token['id'], $token['snapshot']) || ! is_int($token['id']) || $token['id'] < 1
                || ! is_string($token['snapshot']) || preg_match('/\A[a-f0-9]{64}\z/', $token['snapshot']) !== 1) {
                return false;
            }
            $ids[] = $token['id'];
        }

        return count(array_unique($ids)) === 4;
    }

    public static function passwordUsersSnapshot(\PDO $pdo): string
    {
        $rows = [];
        foreach (['users' => 'id', 'roles' => 'id', 'permissions' => 'id', 'users_roles' => 'user_id, role_id', 'users_permissions' => 'user_id, permission_id', 'roles_permissions' => 'role_id, permission_id'] as $table => $order) {
            $rows[$table] = $pdo->query('SELECT * FROM '.$table.' ORDER BY '.$order)->fetchAll(\PDO::FETCH_ASSOC);
        }
        foreach ($rows['users'] as &$row) {
            unset($row['password'], $row['must_change_password'], $row['updated_at'], $row['remember_token']);
        }
        unset($row);

        return hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR));
    }

    /** @param array<string, mixed> $row */
    public static function passwordTokenSnapshot(array $row): string
    {
        unset($row['last_used_at'], $row['updated_at']);

        return hash('sha256', json_encode($row, JSON_THROW_ON_ERROR));
    }

    public static function passwordNotificationsSnapshot(\PDO $pdo): string
    {
        return hash('sha256', json_encode($pdo->query('SELECT * FROM app_notifications ORDER BY id')->fetchAll(\PDO::FETCH_ASSOC), JSON_THROW_ON_ERROR));
    }

    /** @param array<string, mixed> $manifest */
    public static function assertPasswordFixture(array $manifest, \PDO $pdo): void
    {
        $fixture = $manifest['password_flow'] ?? null;
        if (! self::passwordEnabled() || ! is_array($fixture) || ! self::validPasswordFixture($fixture)
            || ! hash_equals($fixture['users_snapshot'], self::passwordUsersSnapshot($pdo))
            || ! hash_equals($fixture['notifications_snapshot'], self::passwordNotificationsSnapshot($pdo))) {
            throw new RuntimeException('The finite password fixture or its immutable snapshot changed.');
        }
        $users = $pdo->query('SELECT * FROM users ORDER BY id')->fetchAll(\PDO::FETCH_UNIQUE | \PDO::FETCH_ASSOC);
        $tokens = $pdo->query('SELECT * FROM personal_access_tokens ORDER BY id')->fetchAll(\PDO::FETCH_ASSOC);
        $remaining = array_column($tokens, null, 'id');
        $expectedIds = [];
        if (count($users) !== 4) {
            throw new RuntimeException('Password scope requires exactly four synthetic identities.');
        }
        foreach ($fixture['identities'] as $name => $identity) {
            $user = $users[$identity['id']] ?? null;
            if ($user === null || $user['uuid'] !== $identity['uuid'] || $user['email'] !== $name.'@authority.example.test'
                || ($user['remember_token'] !== null && (! is_string($user['remember_token']) || preg_match('/\A[A-Za-z0-9]{60}\z/', $user['remember_token']) !== 1))) {
                throw new RuntimeException('The password fixture lost its exact synthetic identity.');
            }
            $forced = in_array($name, ['ordinary', 'target'], true);
            $initial = password_verify('BrowserFixture-123!', $user['password']);
            $completed = $forced && password_verify('PasswordFixture-456!', $user['password']);
            if ((! $initial && ! $completed) || (int) $user['must_change_password'] !== ($forced && $initial ? 1 : 0)) {
                throw new RuntimeException('A password or pending flag escaped its finite owned states.');
            }
            $tokenFixture = $fixture['token_snapshots'][$name];
            $token = $remaining[$tokenFixture['id']] ?? null;
            if ($completed) {
                if ($token !== null) {
                    throw new RuntimeException('Completed password changes must revoke their legacy token.');
                }
            } elseif ($token === null || $token['tokenable_type'] !== User::class || (int) $token['tokenable_id'] !== $identity['id']
                || ! hash_equals($tokenFixture['snapshot'], self::passwordTokenSnapshot($token))) {
                throw new RuntimeException('An unchanged actor lost or modified its legacy token.');
            } else {
                $expectedIds[] = $tokenFixture['id'];
            }
        }
        if (array_diff(array_keys($remaining), $expectedIds) !== []) {
            throw new RuntimeException('An unexpected personal token escaped the password fixture.');
        }
    }

    /** @param array<string, mixed> $body */
    private static function allowsPasswordBody(string $path, array $body): bool
    {
        if ($path === '/logout') {
            return $body === [];
        }
        if ($path === '/login') {
            return array_diff(array_keys($body), ['email', 'password', 'remember']) === []
                && isset($body['email'], $body['password'])
                && in_array($body['email'], ['developer@authority.example.test', 'admin@authority.example.test', 'ordinary@authority.example.test', 'target@authority.example.test'], true)
                && ($body['password'] === 'BrowserFixture-123!'
                    || ($body['password'] === 'PasswordFixture-456!' && in_array($body['email'], ['ordinary@authority.example.test', 'target@authority.example.test'], true)))
                && (! array_key_exists('remember', $body) || $body['remember'] === false);
        }

        return $path === '/settings/force-password-change' && count($body) === 3
            && isset($body['current_password'], $body['password'], $body['password_confirmation'])
            && in_array($body['current_password'], ['BrowserFixture-123!', 'WrongFixture-123!'], true)
            && $body['password'] === 'PasswordFixture-456!' && $body['password_confirmation'] === 'PasswordFixture-456!';
    }

    public static function integrationsEnabled(): bool
    {
        if (getenv('AUTHORITY_HTTP_SELECTION_UI_TESTS') === '1') {
            return false;
        }
        if (getenv('AUTHORITY_HTTP_TIMELINE_UI_TESTS') === '1') {
            return false;
        }
        if (getenv('AUTHORITY_HTTP_UPLOADS_UI_TESTS') === '1' || getenv('AUTHORITY_HTTP_NOTIFICATION_UI_TESTS') === '1') {
            return false;
        }
        if (getenv('AUTHORITY_HTTP_INTEGRATIONS_TESTS') !== '1' || getenv('AUTHORITY_HTTP_VERIFICATION_TESTS') === '1' || getenv('AUTHORITY_HTTP_TESTS') !== '1' || getenv('AUTHORITY_MYSQL_TESTS') !== '1') {
            return false;
        }
        foreach (['AUTHORITY_HTTP_EXPORT_NOTIFICATION_TESTS', 'AUTHORITY_HTTP_PASSWORD_TESTS', 'AUTHORITY_HTTP_ACTIVITY_TESTS', 'AUTHORITY_HTTP_USERS_TESTS', 'AUTHORITY_HTTP_EDITOR_TESTS', 'AUTHORITY_HTTP_PULSE_TESTS', 'AUTHORITY_HTTP_TIMELINE_TESTS', 'AUTHORITY_HTTP_UPLOADS_TESTS', 'AUTHORITY_HTTP_UPLOADS_TRANSPORT_TESTS', 'AUTHORITY_HTTP_DEVTOOLS_TESTS'] as $flag) {
            if (getenv($flag) === '1') {
                return false;
            }
        }

        return true;
    }

    /** @return array{environment: string, database: string, replacement: string} */
    public static function integrationWebhooks(): array
    {
        return self::INTEGRATION_WEBHOOKS;
    }

    /** @param array<string, mixed> $fixture */
    private static function validIntegrationFixture(array $fixture): bool
    {
        return count($fixture) === 3 && isset($fixture['identities'], $fixture['users_snapshot'], $fixture['webhooks'])
            && is_array($fixture['identities'])
            && self::validUsersReadFixture(['identities' => $fixture['identities'], 'snapshot' => $fixture['users_snapshot']])
            && $fixture['webhooks'] === self::INTEGRATION_WEBHOOKS;
    }

    /** @param array<string, mixed> $body */
    public static function allowsIntegrationBody(array $body): bool
    {
        if (array_diff(array_keys($body), ['enabled', 'log_level', 'webhook_url', 'reset_webhook']) !== []
            || ! isset($body['enabled'], $body['log_level']) || ! is_bool($body['enabled'])
            || ! in_array($body['log_level'], ['info', 'warning', 'error', 'critical'], true)
            || (array_key_exists('reset_webhook', $body) && ! is_bool($body['reset_webhook']))) {
            return false;
        }
        if (! array_key_exists('webhook_url', $body) || $body['webhook_url'] === null) {
            return true;
        }
        $url = $body['webhook_url'];

        return is_string($url) && strlen($url) <= 512
            && (trim($url) === '' || in_array($url, self::INTEGRATION_WEBHOOKS, true));
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return list<array<string, mixed>>
     */
    public static function integrationSettingsRows(array $manifest): array
    {
        self::assertOwnedDirectories($manifest['settings']);
        $pdo = DisposableMysql::verifiedConnection($manifest['settings']);
        $fixture = $manifest['integration_settings'] ?? null;
        if (! self::integrationsEnabled() || ! is_array($fixture) || ! self::validIntegrationFixture($fixture)) {
            throw new RuntimeException('Integration settings require their final exclusive owned fixture.');
        }
        self::assertUsersReadFixture(['users_read' => ['identities' => $fixture['identities'], 'snapshot' => $fixture['users_snapshot']]], $pdo);
        $rows = $pdo->query('SELECT * FROM app_settings ORDER BY `group`, `key`')->fetchAll(\PDO::FETCH_ASSOC);
        if (count($rows) < 2 || count($rows) > 3) {
            throw new RuntimeException('Only the bounded synthetic Slack settings may exist.');
        }
        $values = [];
        foreach ($rows as $row) {
            if ($row['group'] !== 'slack' || ! in_array($row['key'], ['enabled', 'log_level', 'webhook_url'], true) || array_key_exists($row['key'], $values)) {
                throw new RuntimeException('An integration setting escaped the allowed synthetic keys.');
            }
            $values[$row['key']] = json_decode($row['value'], true, 32, JSON_THROW_ON_ERROR);
        }
        if (! isset($values['enabled'], $values['log_level']) || ! is_bool($values['enabled'])
            || ! in_array($values['log_level'], ['info', 'warning', 'error', 'critical'], true)
            || (array_key_exists('webhook_url', $values) && ! in_array($values['webhook_url'], self::INTEGRATION_WEBHOOKS, true))) {
            throw new RuntimeException('An integration setting escaped its finite owned states.');
        }

        return $rows;
    }

    /** @param array<string, mixed> $manifest */
    public static function recordIntegrationRequest(array $manifest): void
    {
        $rows = self::integrationSettingsRows($manifest);
        $records = [];
        foreach (Http::recorded() as [$request, $response]) {
            if (! in_array($request->url(), self::INTEGRATION_WEBHOOKS, true) || $request->method() !== 'POST') {
                throw new RuntimeException('An unexpected integration HTTP request reached the owned recorder.');
            }
            $records[] = ['url' => $request->url(), 'payload' => $request->data()];
        }
        self::appendIntegrationAudit($manifest, 'integrations-http.json', $records, 32);
        self::appendIntegrationAudit($manifest, 'integrations-settings-audit.json', [$rows], 128);
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @param  list<array<string, mixed>|list<array<string, mixed>>>  $entries
     */
    private static function appendIntegrationAudit(array $manifest, string $leaf, array $entries, int $limit): void
    {
        self::assertOwnedDirectories($manifest['settings']);
        if (! in_array($leaf, ['integrations-http.json', 'integrations-settings-audit.json'], true)) {
            throw new RuntimeException('Unexpected integration audit filename.');
        }
        $path = $manifest['settings']['path'].'/'.$leaf;
        $stat = lstat($path);
        if ($stat === false || is_link($path) || ($stat['mode'] & 0170000) !== 0100000 || $stat['nlink'] !== 1 || $stat['size'] > 65536
            || realpath($path) !== realpath($manifest['settings']['path']).DIRECTORY_SEPARATOR.$leaf) {
            throw new RuntimeException('Refusing an unowned integration audit file.');
        }
        $stream = fopen($path, 'r+b');
        if ($stream === false) {
            throw new RuntimeException('Cannot open the owned integration audit.');
        }
        try {
            $opened = fstat($stream);
            if ($opened === false || $opened['dev'] !== $stat['dev'] || $opened['ino'] !== $stat['ino'] || ! flock($stream, LOCK_EX)) {
                throw new RuntimeException('The integration audit changed before it was locked.');
            }
            $bytes = stream_get_contents($stream, 65537);
            if (! is_string($bytes) || strlen($bytes) > 65536) {
                throw new RuntimeException('The integration audit exceeded its bound.');
            }
            $existing = json_decode($bytes, true, 64, JSON_THROW_ON_ERROR);
            if (! is_array($existing) || ! array_is_list($existing) || count($existing) + count($entries) > $limit) {
                throw new RuntimeException('The integration audit has an invalid shape or count.');
            }
            $next = json_encode([...$existing, ...$entries], JSON_THROW_ON_ERROR);
            if (strlen($next) > 65536 || ! rewind($stream) || ! ftruncate($stream, 0) || fwrite($stream, $next) !== strlen($next) || ! fflush($stream)) {
                throw new RuntimeException('The owned integration audit could not be written completely.');
            }
        } finally {
            fclose($stream);
        }
    }

    public static function activityReadEnabled(): bool
    {
        if (getenv('AUTHORITY_HTTP_SELECTION_UI_TESTS') === '1') {
            return false;
        }
        if (getenv('AUTHORITY_HTTP_TIMELINE_UI_TESTS') === '1') {
            return false;
        }
        if (getenv('AUTHORITY_HTTP_UPLOADS_UI_TESTS') === '1' || getenv('AUTHORITY_HTTP_NOTIFICATION_UI_TESTS') === '1') {
            return false;
        }

        return getenv('AUTHORITY_HTTP_ACTIVITY_TESTS') === '1'
            && getenv('AUTHORITY_HTTP_EXPORT_NOTIFICATION_TESTS') !== '1'
            && getenv('AUTHORITY_HTTP_VERIFICATION_TESTS') !== '1'
            && getenv('AUTHORITY_HTTP_PASSWORD_TESTS') !== '1'
            && getenv('AUTHORITY_HTTP_TESTS') === '1' && getenv('AUTHORITY_MYSQL_TESTS') === '1'
            && getenv('AUTHORITY_HTTP_USERS_TESTS') !== '1' && getenv('AUTHORITY_HTTP_EDITOR_TESTS') !== '1'
            && getenv('AUTHORITY_HTTP_PULSE_TESTS') !== '1' && getenv('AUTHORITY_HTTP_TIMELINE_TESTS') !== '1'
            && getenv('AUTHORITY_HTTP_UPLOADS_TESTS') !== '1' && getenv('AUTHORITY_HTTP_UPLOADS_TRANSPORT_TESTS') !== '1'
            && getenv('AUTHORITY_HTTP_DEVTOOLS_TESTS') !== '1';
    }

    /** @param array<string, mixed> $fixture */
    private static function validActivityReadFixture(array $fixture): bool
    {
        if (count($fixture) !== 6 || ! isset($fixture['identities'], $fixture['client'], $fixture['logs'], $fixture['users_snapshot'], $fixture['clients_snapshot'], $fixture['logs_snapshot'])
            || ! is_array($fixture['identities']) || ! self::validUsersReadFixture(['identities' => $fixture['identities'], 'snapshot' => $fixture['users_snapshot']])
            || ! is_array($fixture['client']) || count($fixture['client']) !== 2
            || ! isset($fixture['client']['id'], $fixture['client']['uuid']) || ! is_int($fixture['client']['id']) || $fixture['client']['id'] < 1
            || ! is_string($fixture['client']['uuid']) || preg_match('/\A[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}\z/', $fixture['client']['uuid']) !== 1
            || ! is_array($fixture['logs']) || ! array_is_list($fixture['logs']) || count($fixture['logs']) !== 5) {
            return false;
        }
        foreach (['clients_snapshot', 'logs_snapshot'] as $field) {
            if (! is_string($fixture[$field]) || preg_match('/\A[a-f0-9]{64}\z/', $fixture[$field]) !== 1) {
                return false;
            }
        }
        foreach ($fixture['logs'] as $id) {
            if (! is_int($id) || $id < 1) {
                return false;
            }
        }

        return count(array_unique($fixture['logs'])) === 5;
    }

    /**
     * @param  array<mixed>  $logIds
     * @return array{users_snapshot: string, clients_snapshot: string, logs_snapshot: string}
     */
    public static function activityReadSnapshot(\PDO $pdo, array $logIds): array
    {
        if (count($logIds) !== 5 || ! array_is_list($logIds)) {
            throw new RuntimeException('Activity snapshots require exactly five owned log identifiers.');
        }
        foreach ($logIds as $id) {
            if (! is_int($id) || $id < 1) {
                throw new RuntimeException('Invalid owned Activity log identifier.');
            }
        }
        $logs = $pdo->prepare('SELECT * FROM activity_log WHERE id IN (?, ?, ?, ?, ?) ORDER BY id');
        $logs->execute($logIds);
        $rows = $logs->fetchAll(\PDO::FETCH_ASSOC);
        if (count($rows) !== 5) {
            throw new RuntimeException('The owned Activity snapshot lost a fixture log.');
        }

        return [
            'users_snapshot' => self::usersReadSnapshot($pdo),
            'clients_snapshot' => hash('sha256', json_encode($pdo->query('SELECT * FROM clients ORDER BY id')->fetchAll(\PDO::FETCH_ASSOC), JSON_THROW_ON_ERROR)),
            'logs_snapshot' => hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR)),
        ];
    }

    /** @param array<string, mixed> $manifest */
    private static function assertActivityReadFixture(array $manifest, \PDO $pdo): void
    {
        $fixture = $manifest['activity_read'] ?? null;
        if (! is_array($fixture) || ! self::validActivityReadFixture($fixture)) {
            throw new RuntimeException('Invalid owned read-only Activity fixture.');
        }
        self::assertUsersReadFixture(['users_read' => ['identities' => $fixture['identities'], 'snapshot' => $fixture['users_snapshot']]], $pdo);
        $clients = $pdo->query('SELECT id, uuid, name, email FROM clients ORDER BY id')->fetchAll(\PDO::FETCH_ASSOC);
        if (count($clients) !== 1 || (int) $clients[0]['id'] !== $fixture['client']['id'] || $clients[0]['uuid'] !== $fixture['client']['uuid']
            || $clients[0]['name'] !== 'Cliente atividade Café' || $clients[0]['email'] !== 'activity@authority.example.test') {
            throw new RuntimeException('Activity subjects must belong to the sole owned synthetic Client.');
        }
        foreach (self::activityReadSnapshot($pdo, $fixture['logs']) as $key => $hash) {
            if (! hash_equals($fixture[$key], $hash)) {
                throw new RuntimeException('The read-only Activity fixture was modified.');
            }
        }
    }

    /** @param array<string, mixed> $query */
    private static function allowsActivityQuery(array $query): bool
    {
        if (array_diff(array_keys($query), ['page', 'per_page', 'search', 'filter']) !== []
            || (array_key_exists('page', $query) && ! in_array($query['page'], ['1', '2'], true))
            || (array_key_exists('per_page', $query) && $query['per_page'] !== '30')
            || (array_key_exists('search', $query) && (! is_string($query['search']) || preg_match('//u', $query['search']) !== 1 || strlen($query['search']) > 100))) {
            return false;
        }
        if (! array_key_exists('filter', $query)) {
            return true;
        }
        $filters = $query['filter'];
        if (! is_array($filters) || count($filters) > 7 || array_diff(array_keys($filters), ['log_name', 'event', 'action_key', 'subject_type', 'causer_name', 'causer_id', 'created_at']) !== []) {
            return false;
        }
        $validText = static fn (mixed $value): bool => is_string($value) && preg_match('//u', $value) === 1 && strlen($value) <= 100;
        foreach ($filters as $column => $filter) {
            $operator = 'eq';
            $value = $filter;
            if (is_array($filter) && array_key_exists('operator', $filter)) {
                if (count($filter) !== 2 || ! array_key_exists('value', $filter) || ! is_string($filter['operator'])
                    || ! in_array($filter['operator'], ['eq', 'contains', 'in', 'between', 'gte', 'lte'], true)) {
                    return false;
                }
                $operator = $filter['operator'];
                $value = $filter['value'];
            }
            if ($column === 'created_at') {
                if (! is_array($value) || array_diff(array_keys($value), ['from', 'to']) !== [] || $value === []
                    || ! in_array($operator, ['eq', 'between'], true)) {
                    return false;
                }
                foreach ($value as $date) {
                    if (! is_string($date) || preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $date) !== 1) {
                        return false;
                    }
                    [$year, $month, $day] = array_map('intval', explode('-', $date));
                    if (! checkdate($month, $day, $year)) {
                        return false;
                    }
                }
            } elseif ($operator === 'in') {
                if (! is_array($value) || ! array_is_list($value) || $value === [] || count($value) > 10) {
                    return false;
                }
                foreach ($value as $item) {
                    if (! $validText($item)) {
                        return false;
                    }
                }
            } elseif (! in_array($operator, ['eq', 'contains'], true) || ! $validText($value)) {
                return false;
            }
            if ($column === 'causer_id' && (! is_string($value) || preg_match('/\A[1-9][0-9]{0,9}\z/', $value) !== 1)) {
                return false;
            }
        }

        return true;
    }

    public static function usersReadEnabled(): bool
    {
        if (getenv('AUTHORITY_HTTP_SELECTION_UI_TESTS') === '1') {
            return false;
        }
        if (getenv('AUTHORITY_HTTP_TIMELINE_UI_TESTS') === '1') {
            return false;
        }
        if (getenv('AUTHORITY_HTTP_UPLOADS_UI_TESTS') === '1' || getenv('AUTHORITY_HTTP_NOTIFICATION_UI_TESTS') === '1') {
            return false;
        }

        return getenv('AUTHORITY_HTTP_USERS_TESTS') === '1' && getenv('AUTHORITY_HTTP_ACTIVITY_TESTS') !== '1'
            && getenv('AUTHORITY_HTTP_EXPORT_NOTIFICATION_TESTS') !== '1'
            && getenv('AUTHORITY_HTTP_VERIFICATION_TESTS') !== '1'
            && getenv('AUTHORITY_HTTP_PASSWORD_TESTS') !== '1'
            && getenv('AUTHORITY_HTTP_TESTS') === '1' && getenv('AUTHORITY_MYSQL_TESTS') === '1'
            && getenv('AUTHORITY_HTTP_EDITOR_TESTS') !== '1' && getenv('AUTHORITY_HTTP_PULSE_TESTS') !== '1'
            && getenv('AUTHORITY_HTTP_TIMELINE_TESTS') !== '1' && getenv('AUTHORITY_HTTP_UPLOADS_TESTS') !== '1'
            && getenv('AUTHORITY_HTTP_UPLOADS_TRANSPORT_TESTS') !== '1' && getenv('AUTHORITY_HTTP_DEVTOOLS_TESTS') !== '1';
    }

    /** @param array<string, mixed> $fixture */
    private static function validUsersReadFixture(array $fixture): bool
    {
        if (count($fixture) !== 2 || ! isset($fixture['identities'], $fixture['snapshot'])
            || ! is_array($fixture['identities']) || count($fixture['identities']) !== 4
            || array_diff(array_keys($fixture['identities']), ['developer', 'admin', 'ordinary', 'target']) !== []
            || ! is_string($fixture['snapshot']) || preg_match('/\A[a-f0-9]{64}\z/', $fixture['snapshot']) !== 1) {
            return false;
        }
        $ids = [];
        $uuids = [];
        foreach ($fixture['identities'] as $identity) {
            if (! is_array($identity) || count($identity) !== 2 || ! isset($identity['id'], $identity['uuid'])
                || ! is_int($identity['id']) || $identity['id'] < 1 || ! is_string($identity['uuid'])
                || preg_match('/\A[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}\z/', $identity['uuid']) !== 1) {
                return false;
            }
            $ids[] = $identity['id'];
            $uuids[] = $identity['uuid'];
        }

        return count(array_unique($ids)) === 4 && count(array_unique($uuids)) === 4;
    }

    public static function usersReadSnapshot(\PDO $pdo): string
    {
        $rows = [];
        foreach (['users' => 'id', 'roles' => 'id', 'permissions' => 'id', 'users_roles' => 'user_id, role_id', 'users_permissions' => 'user_id, permission_id', 'roles_permissions' => 'role_id, permission_id'] as $table => $order) {
            $rows[$table] = $pdo->query('SELECT * FROM '.$table.' ORDER BY '.$order)->fetchAll(\PDO::FETCH_ASSOC);
        }

        return hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR));
    }

    /** @param array<string, mixed> $manifest */
    private static function assertUsersReadFixture(array $manifest, \PDO $pdo): void
    {
        $fixture = $manifest['users_read'] ?? null;
        if (! is_array($fixture) || ! self::validUsersReadFixture($fixture)) {
            throw new RuntimeException('Invalid owned read-only Users fixture.');
        }
        $rows = $pdo->query('SELECT id, uuid, email FROM users ORDER BY id')->fetchAll(\PDO::FETCH_ASSOC);
        $users = array_column($rows, null, 'id');
        if (count($users) !== 4) {
            throw new RuntimeException('Users scope requires exactly its four synthetic identities.');
        }
        foreach ($fixture['identities'] as $name => $identity) {
            $user = $users[$identity['id']] ?? null;
            if ($user === null || $user['uuid'] !== $identity['uuid'] || $user['email'] !== $name.'@authority.example.test') {
                throw new RuntimeException('Users identifiers must belong to the owned synthetic identities.');
            }
        }
        if (! hash_equals($fixture['snapshot'], self::usersReadSnapshot($pdo))) {
            throw new RuntimeException('The read-only Users fixture was modified.');
        }
    }

    public static function uploadsTransportEnabled(): bool
    {
        if (getenv('AUTHORITY_HTTP_SELECTION_UI_TESTS') === '1') {
            return false;
        }
        if (getenv('AUTHORITY_HTTP_TIMELINE_UI_TESTS') === '1') {
            return false;
        }
        if (getenv('AUTHORITY_HTTP_UPLOADS_UI_TESTS') === '1' || getenv('AUTHORITY_HTTP_NOTIFICATION_UI_TESTS') === '1') {
            return false;
        }

        return getenv('AUTHORITY_HTTP_UPLOADS_TRANSPORT_TESTS') === '1' && getenv('AUTHORITY_HTTP_ACTIVITY_TESTS') !== '1'
            && getenv('AUTHORITY_HTTP_EXPORT_NOTIFICATION_TESTS') !== '1'
            && getenv('AUTHORITY_HTTP_VERIFICATION_TESTS') !== '1'
            && getenv('AUTHORITY_HTTP_PASSWORD_TESTS') !== '1'
            && getenv('AUTHORITY_HTTP_TESTS') === '1' && getenv('AUTHORITY_MYSQL_TESTS') === '1' && getenv('AUTHORITY_HTTP_UPLOADS_TESTS') === '1'
            && getenv('AUTHORITY_HTTP_EDITOR_TESTS') !== '1' && getenv('AUTHORITY_HTTP_PULSE_TESTS') !== '1'
            && getenv('AUTHORITY_HTTP_TIMELINE_TESTS') !== '1' && getenv('AUTHORITY_HTTP_DEVTOOLS_TESTS') !== '1';
    }

    public static function requireOptIn(): void
    {
        if (getenv('AUTHORITY_HTTP_TESTS') !== '1' || getenv('AUTHORITY_MYSQL_TESTS') !== '1') {
            throw new RuntimeException('HTTP authority sandbox requires both explicit test opt-ins.');
        }
    }

    public static function configureGlobals(bool $console): void
    {
        self::requireOptIn();

        foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => '', 'APP_RUNNING_IN_CONSOLE' => $console ? 'true' : 'false'] as $key => $value) {
            $_ENV[$key] = $_SERVER[$key] = $value;
            putenv($key.'='.$value);
        }

        TestEnvironment::assertSafeEnvironment();
    }

    public static function assertLoopbackHost(string $host): void
    {
        if (! preg_match('/\A[a-z0-9_]+(?:[.-][a-z0-9_]+)*\z/', $host) || ! str_contains($host, '.') || filter_var($host, FILTER_VALIDATE_IP)) {
            throw new RuntimeException('An existing custom HTTP hostname is required.');
        }

        if (! function_exists('socket_addrinfo_lookup')) {
            throw new RuntimeException('The sockets extension is required to verify every resolved HTTP address.');
        }

        $addresses = socket_addrinfo_lookup($host, null, ['ai_socktype' => SOCK_STREAM]);

        if ($addresses === false || $addresses === []) {
            throw new RuntimeException('The HTTP sandbox hostname must already resolve to loopback.');
        }

        foreach ($addresses as $address) {
            $details = socket_addrinfo_explain($address);
            $ip = $details['ai_addr']['sin_addr'] ?? $details['ai_addr']['sin6_addr'] ?? '';

            if (! str_starts_with($ip, '127.') && $ip !== '::1' && ! str_starts_with($ip, '::ffff:127.')) {
                throw new RuntimeException('Refusing an HTTP sandbox hostname outside loopback.');
            }
        }
    }

    public static function requiredHost(): string
    {
        $host = getenv('AUTHORITY_HTTP_HOST');

        if (! is_string($host) || $host === '') {
            throw new RuntimeException('Set AUTHORITY_HTTP_HOST explicitly to an existing isolated loopback hostname.');
        }

        self::assertLoopbackHost($host);

        return $host;
    }

    /** @return array<string, mixed> */
    public static function readManifest(string $path): array
    {
        self::requireOptIn();
        if (getenv('AUTHORITY_HTTP_SELECTION_UI_TESTS') === '1' || getenv('AUTHORITY_HTTP_TIMELINE_UI_TESTS') === '1' || getenv('AUTHORITY_HTTP_UPLOADS_UI_TESTS') === '1' || getenv('AUTHORITY_HTTP_NOTIFICATION_UI_TESTS') === '1' || getenv('AUTHORITY_HTTP_PASSWORD_TESTS') === '1' || getenv('AUTHORITY_HTTP_VERIFICATION_TESTS') === '1' || getenv('AUTHORITY_HTTP_EXPORT_NOTIFICATION_TESTS') === '1') {
            $stat = lstat($path);
            if ($stat === false || is_link($path) || ($stat['mode'] & 0170000) !== 0100000 || $stat['nlink'] !== 1 || $stat['size'] > 32768) {
                throw new RuntimeException('Invalid owned password HTTP manifest file.');
            }
        }
        $manifest = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($manifest) || ! isset($manifest['settings'], $manifest['host'], $manifest['port']) || ! is_array($manifest['settings'])) {
            throw new RuntimeException('Invalid HTTP sandbox manifest.');
        }

        $settings = $manifest['settings'];
        $mode = $manifest['mode'] ?? 'authority';

        if ((getenv('AUTHORITY_HTTP_SELECTION_UI_TESTS') === '1' && (! self::selectionUiEnabled() || $mode !== 'authority'
                || array_diff(array_keys($manifest), ['settings', 'host', 'port', 'mode', 'selection_ui']) !== []))
            || (array_key_exists('selection_ui', $manifest) && (! self::selectionUiEnabled() || ! is_array($manifest['selection_ui']) || ! self::validSelectionUiFixture($manifest['selection_ui'])))) {
            throw new RuntimeException('Selection UI requires its exclusive finite authority profile.');
        }

        if ((getenv('AUTHORITY_HTTP_TIMELINE_UI_TESTS') === '1' && (! self::timelineUiEnabled() || $mode !== 'authority'
                || array_diff(array_keys($manifest), ['settings', 'host', 'port', 'mode', 'timeline_ui']) !== []))
            || (array_key_exists('timeline_ui', $manifest) && (! self::timelineUiEnabled() || ! is_array($manifest['timeline_ui']) || ! self::validTimelineUiFixture($manifest['timeline_ui'])))) {
            throw new RuntimeException('Timeline UI requires its exclusive finite authority profile.');
        }

        if ((getenv('AUTHORITY_HTTP_UPLOADS_UI_TESTS') === '1' && (! self::uploadsUiEnabled() || $mode !== 'authority'
                || array_diff(array_keys($manifest), ['settings', 'host', 'port', 'mode', 'uploads_ui']) !== []))
            || (array_key_exists('uploads_ui', $manifest) && (! self::uploadsUiEnabled() || ! is_array($manifest['uploads_ui']) || ! self::validUploadsUiFixture($manifest['uploads_ui'])))) {
            throw new RuntimeException('Uploads UI requires its exclusive finite authority profile.');
        }

        if ((getenv('AUTHORITY_HTTP_NOTIFICATION_UI_TESTS') === '1' && (! self::notificationUiEnabled() || $mode !== 'authority'
                || array_diff(array_keys($manifest), ['settings', 'host', 'port', 'mode', 'notification_ui']) !== []))
            || (array_key_exists('notification_ui', $manifest) && (! self::notificationUiEnabled() || ! is_array($manifest['notification_ui']) || ! self::validNotificationUiFixture($manifest['notification_ui'])))) {
            throw new RuntimeException('Notification UI requires its exclusive finite authority profile.');
        }

        if ((getenv('AUTHORITY_HTTP_EXPORT_NOTIFICATION_TESTS') === '1' && (! self::exportNotificationEnabled() || $mode !== 'authority'
                || array_diff(array_keys($manifest), ['settings', 'host', 'port', 'mode', 'export_notification']) !== []))
            || (array_key_exists('export_notification', $manifest) && (! self::exportNotificationEnabled() || ! is_array($manifest['export_notification']) || ! self::validExportNotificationFixture($manifest['export_notification'])))) {
            throw new RuntimeException('Export notifications require their exclusive finite authority profile.');
        }

        if ((getenv('AUTHORITY_HTTP_VERIFICATION_TESTS') === '1' && (! self::verificationEnabled() || $mode !== 'authority'
                || array_diff(array_keys($manifest), ['settings', 'host', 'port', 'mode', 'verification_flow']) !== []))
            || (array_key_exists('verification_flow', $manifest) && ! self::verificationEnabled())) {
            throw new RuntimeException('Verification requires its exclusive finite authority profile.');
        }

        if ((getenv('AUTHORITY_HTTP_PASSWORD_TESTS') === '1' && (! self::passwordEnabled() || $mode !== 'authority'
                || array_diff(array_keys($manifest), ['settings', 'host', 'port', 'mode', 'password_flow']) !== []))
            || (array_key_exists('password_flow', $manifest) && ! self::passwordEnabled())) {
            throw new RuntimeException('Password HTTP requires its exclusive finite authority profile.');
        }

        if ((getenv('AUTHORITY_HTTP_INTEGRATIONS_TESTS') === '1' && (! self::integrationsEnabled() || $mode !== 'authority'))
            || (array_key_exists('integration_settings', $manifest) && ! self::integrationsEnabled())
            || (self::integrationsEnabled() && array_intersect(array_keys($manifest), ['activity_read', 'users_read', 'uploads_transport', 'timeline', 'uploads', 'editor', 'pulse']) !== [])) {
            throw new RuntimeException('Integration settings require their exclusive owned authority profile.');
        }

        if ((getenv('AUTHORITY_HTTP_ACTIVITY_TESTS') === '1' && (! self::activityReadEnabled() || $mode !== 'authority'))
            || (array_key_exists('activity_read', $manifest) && ! self::activityReadEnabled())) {
            throw new RuntimeException('Activity reads require their exclusive owned authority profile.');
        }

        if ((getenv('AUTHORITY_HTTP_USERS_TESTS') === '1' && (! self::usersReadEnabled() || $mode !== 'authority'))
            || (array_key_exists('users_read', $manifest) && ! self::usersReadEnabled())) {
            throw new RuntimeException('Users reads require their exclusive owned authority profile.');
        }

        if (getenv('AUTHORITY_HTTP_UPLOADS_TRANSPORT_TESTS') === '1'
            && (! self::uploadsTransportEnabled() || ! in_array($mode, ['authority', 'uploads'], true))) {
            throw new RuntimeException('Uploads transport requires its exclusive owned Uploads profile.');
        }
        if (isset($manifest['uploads_transport']) && ! self::uploadsTransportEnabled()) {
            throw new RuntimeException('The writable Uploads fixture requires its separate explicit opt-in.');
        }

        if (! in_array($mode, ['authority', 'devtools', 'timeline', 'uploads', 'editor', 'pulse'], true) || ($mode === 'devtools' && getenv('AUTHORITY_HTTP_DEVTOOLS_TESTS') !== '1')
            || ($mode === 'pulse' && (getenv('AUTHORITY_HTTP_PULSE_TESTS') !== '1' || getenv('AUTHORITY_HTTP_EDITOR_TESTS') === '1' || getenv('AUTHORITY_HTTP_DEVTOOLS_TESTS') === '1' || getenv('AUTHORITY_HTTP_TIMELINE_TESTS') === '1' || getenv('AUTHORITY_HTTP_UPLOADS_TESTS') === '1'))
            || ($mode !== 'pulse' && $mode !== 'authority' && getenv('AUTHORITY_HTTP_PULSE_TESTS') === '1')
            || (getenv('AUTHORITY_HTTP_PULSE_TESTS') === '1' && (getenv('AUTHORITY_HTTP_EDITOR_TESTS') === '1' || getenv('AUTHORITY_HTTP_DEVTOOLS_TESTS') === '1' || getenv('AUTHORITY_HTTP_TIMELINE_TESTS') === '1' || getenv('AUTHORITY_HTTP_UPLOADS_TESTS') === '1'))
            || ($mode === 'uploads' && getenv('AUTHORITY_HTTP_UPLOADS_TESTS') !== '1')
            || ($mode === 'editor' && (getenv('AUTHORITY_HTTP_EDITOR_TESTS') !== '1' || getenv('AUTHORITY_HTTP_DEVTOOLS_TESTS') === '1' || getenv('AUTHORITY_HTTP_TIMELINE_TESTS') === '1' || getenv('AUTHORITY_HTTP_UPLOADS_TESTS') === '1'))
            || (in_array($mode, ['devtools', 'timeline', 'uploads'], true) && getenv('AUTHORITY_HTTP_EDITOR_TESTS') === '1')
            || ($mode !== 'authority' && getenv('AUTHORITY_HTTP_UPLOADS_TESTS') === '1' && (getenv('AUTHORITY_HTTP_DEVTOOLS_TESTS') === '1' || getenv('AUTHORITY_HTTP_TIMELINE_TESTS') === '1'))
            || ($mode === 'timeline' && (getenv('AUTHORITY_HTTP_TIMELINE_TESTS') !== '1' || getenv('AUTHORITY_HTTP_DEVTOOLS_TESTS') === '1'))) {
            throw new RuntimeException('The extended HTTP scope requires its separate unambiguous explicit opt-in.');
        }

        if (! isset($settings['path']) || realpath($path) !== realpath($settings['path'].'/http.json') || is_link($path)
            || ! is_string($manifest['host']) || ! preg_match('/\A[a-z0-9_]+(?:[.-][a-z0-9_]+)*\z/', $manifest['host'])
            || ! is_int($manifest['port']) || $manifest['port'] < 1024 || $manifest['port'] > 65535
            || in_array($manifest['port'], [3306, 33060], true)) {
            throw new RuntimeException('Refusing an unowned HTTP sandbox manifest.');
        }

        self::assertOwnedDirectories($settings);

        if ($mode === 'devtools') {
            self::assertDevToolsDirectories($settings);
        }

        $pdo = DisposableMysql::verifiedConnection($settings);

        if (self::selectionUiEnabled()) {
            if ($manifest['host'] !== self::requiredHost()) {
                throw new RuntimeException('Selection UI host must match its owned loopback profile.');
            }
            if (array_key_exists('selection_ui', $manifest)) {
                self::assertSelectionUiFixture($manifest, $pdo);
            } elseif (PHP_SAPI !== 'cli' || getenv('APP_RUNNING_IN_CONSOLE') !== 'true') {
                throw new RuntimeException('Selection UI runtime requires its final owned fixture.');
            }
        }

        if (self::timelineUiEnabled()) {
            if ($manifest['host'] !== self::requiredHost()) {
                throw new RuntimeException('Timeline UI host must match its owned loopback profile.');
            }
            if (array_key_exists('timeline_ui', $manifest)) {
                self::assertTimelineUiFixture($manifest, $pdo);
            } elseif (PHP_SAPI !== 'cli' || getenv('APP_RUNNING_IN_CONSOLE') !== 'true') {
                throw new RuntimeException('Timeline UI runtime requires its final owned fixture.');
            }
        }

        if (self::uploadsUiEnabled()) {
            if ($manifest['host'] !== self::requiredHost()) {
                throw new RuntimeException('Uploads UI host must match its owned loopback profile.');
            }
            if (array_key_exists('uploads_ui', $manifest)) {
                self::assertUploadsUiFixture($manifest, $pdo);
            } elseif (PHP_SAPI !== 'cli' || getenv('APP_RUNNING_IN_CONSOLE') !== 'true') {
                throw new RuntimeException('Uploads UI runtime requires its final owned fixture.');
            }
        }

        if (self::notificationUiEnabled()) {
            if ($manifest['host'] !== self::requiredHost()) {
                throw new RuntimeException('Notification UI host must match its owned loopback profile.');
            }
            if (array_key_exists('notification_ui', $manifest)) {
                self::assertNotificationUiFixture($manifest, $pdo);
            } elseif (PHP_SAPI !== 'cli' || getenv('APP_RUNNING_IN_CONSOLE') !== 'true') {
                throw new RuntimeException('Notification UI runtime requires its final owned fixture.');
            }
        }

        if (self::exportNotificationEnabled()) {
            if ($manifest['host'] !== self::requiredHost()) {
                throw new RuntimeException('Export notification host must match its explicit loopback profile.');
            }
            self::assertExportNotificationDirectory($settings);
            if (array_key_exists('export_notification', $manifest)) {
                self::assertExportNotificationFixture($manifest, $pdo);
            } elseif (PHP_SAPI !== 'cli' || getenv('APP_RUNNING_IN_CONSOLE') !== 'true') {
                throw new RuntimeException('Export notification runtime requires its final owned fixture.');
            }
        }

        if (self::verificationEnabled()) {
            if ($manifest['host'] !== self::requiredHost()) {
                throw new RuntimeException('Verification host must match its explicit loopback profile.');
            }
            if (array_key_exists('verification_flow', $manifest)) {
                self::assertVerificationFixture($manifest, $pdo);
            } elseif (PHP_SAPI !== 'cli' || getenv('APP_RUNNING_IN_CONSOLE') !== 'true') {
                throw new RuntimeException('Verification runtime requires its final owned fixture.');
            }
        }

        if (self::passwordEnabled()) {
            if ($manifest['host'] !== self::requiredHost()) {
                throw new RuntimeException('Password HTTP host must match its explicit loopback profile.');
            }
            if (array_key_exists('password_flow', $manifest)) {
                self::assertPasswordFixture($manifest, $pdo);
            } elseif (PHP_SAPI !== 'cli' || getenv('APP_RUNNING_IN_CONSOLE') !== 'true') {
                throw new RuntimeException('Password HTTP runtime requires its final owned fixture.');
            }
        }

        if (self::integrationsEnabled()) {
            if ($manifest['host'] !== self::requiredHost()) {
                throw new RuntimeException('Integration settings HTTP host must match its explicit loopback profile.');
            }
            if (array_key_exists('integration_settings', $manifest)) {
                self::integrationSettingsRows($manifest);
            } elseif (PHP_SAPI !== 'cli' || getenv('APP_RUNNING_IN_CONSOLE') !== 'true') {
                throw new RuntimeException('Integration settings HTTP requires its final owned fixture.');
            }
        }

        if (self::activityReadEnabled()) {
            if ($manifest['host'] !== self::requiredHost()) {
                throw new RuntimeException('Activity HTTP host must match its explicit loopback profile.');
            }
            if (array_key_exists('activity_read', $manifest)) {
                self::assertActivityReadFixture($manifest, $pdo);
            } elseif (PHP_SAPI !== 'cli' || getenv('APP_RUNNING_IN_CONSOLE') !== 'true') {
                throw new RuntimeException('Activity HTTP runtime requires its final owned fixture.');
            }
        }

        if (self::usersReadEnabled()) {
            if ($manifest['host'] !== self::requiredHost()) {
                throw new RuntimeException('Users HTTP host must match its explicit loopback profile.');
            }
            if (array_key_exists('users_read', $manifest)) {
                self::assertUsersReadFixture($manifest, $pdo);
            } elseif (PHP_SAPI !== 'cli' || getenv('APP_RUNNING_IN_CONSOLE') !== 'true') {
                throw new RuntimeException('Users HTTP runtime requires its final owned fixture.');
            }
        }

        if ($mode === 'timeline') {
            if ($manifest['host'] !== self::requiredHost()) {
                throw new RuntimeException('Timeline HTTP host must match its explicit loopback profile.');
            }
            self::assertTimelineFixture($manifest, $pdo);
        }

        if ($mode === 'uploads') {
            if ($manifest['host'] !== self::requiredHost()) {
                throw new RuntimeException('Uploads HTTP host must match its explicit loopback profile.');
            }
            self::assertUploadsFixture($manifest, $pdo);
        }

        if ($mode === 'editor') {
            if ($manifest['host'] !== self::requiredHost()) {
                throw new RuntimeException('Editor HTTP host must match its explicit loopback profile.');
            }
            self::assertEditorFixture($manifest, $pdo);
        }

        if ($mode === 'pulse') {
            if ($manifest['host'] !== self::requiredHost()) {
                throw new RuntimeException('Pulse HTTP host must match its explicit loopback profile.');
            }
            self::assertPulseFixture($manifest, $pdo);
        }

        $manifest['mode'] = $mode;

        return $manifest;
    }

    /** @param array<string, mixed> $manifest */
    private static function assertPulseFixture(array $manifest, \PDO $pdo): void
    {
        $fixture = $manifest['pulse'] ?? null;

        if (! is_array($fixture) || count($fixture) !== 4 || ! isset($fixture['user_id'], $fixture['user_uuid'], $fixture['update_path'], $fixture['snapshot'])
            || ! is_int($fixture['user_id']) || $fixture['user_id'] < 1
            || ! is_string($fixture['user_uuid']) || preg_match('/\A[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}\z/', $fixture['user_uuid']) !== 1
            || ! is_string($fixture['update_path']) || preg_match('~\A/livewire-[a-f0-9]{8}/update\z~', $fixture['update_path']) !== 1
            || ! is_string($fixture['snapshot']) || preg_match('/\A[a-f0-9]{64}\z/', $fixture['snapshot']) !== 1) {
            throw new RuntimeException('Invalid owned Pulse fixture identifiers.');
        }

        $query = $pdo->prepare('SELECT uuid, email FROM users WHERE id = ?');
        $query->execute([$fixture['user_id']]);
        $user = $query->fetch(\PDO::FETCH_ASSOC);

        if ($user === false || $user['uuid'] !== $fixture['user_uuid'] || $user['email'] !== 'developer@authority.example.test') {
            throw new RuntimeException('Pulse access must belong to its synthetic owned identity.');
        }

        $rows = [];
        foreach (['pulse_values', 'pulse_entries', 'pulse_aggregates'] as $table) {
            $rows[$table] = $pdo->query('SELECT * FROM '.$table.' ORDER BY id')->fetchAll(\PDO::FETCH_ASSOC);
            if ($rows[$table] !== []) {
                throw new RuntimeException('Pulse HTTP validation must not record metrics.');
            }
        }

        if (! hash_equals($fixture['snapshot'], hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR)))) {
            throw new RuntimeException('The owned Pulse tables were modified.');
        }
    }

    /** @param array<string, mixed> $manifest */
    private static function assertEditorFixture(array $manifest, \PDO $pdo): void
    {
        $fixture = $manifest['editor'] ?? null;

        if (! is_array($fixture) || count($fixture) !== 3 || ! isset($fixture['client_id'], $fixture['client_uuid'], $fixture['snapshot'])
            || ! is_int($fixture['client_id']) || $fixture['client_id'] < 1
            || ! is_string($fixture['client_uuid']) || preg_match('/\A[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}\z/', $fixture['client_uuid']) !== 1
            || ! is_string($fixture['snapshot']) || preg_match('/\A[a-f0-9]{64}\z/', $fixture['snapshot']) !== 1) {
            throw new RuntimeException('Invalid owned Editor fixture identifiers.');
        }

        $clients = $pdo->query('SELECT id, uuid, name, email FROM clients ORDER BY id')->fetchAll(\PDO::FETCH_ASSOC);

        if (count($clients) !== 1 || (int) $clients[0]['id'] !== $fixture['client_id'] || $clients[0]['uuid'] !== $fixture['client_uuid']
            || $clients[0]['name'] !== 'DEP001 Editor Fixture' || $clients[0]['email'] !== 'editor@authority.example.test') {
            throw new RuntimeException('Editor client does not belong to its neutral owned fixture.');
        }

        $rows = $pdo->query('SELECT * FROM timeline_comments ORDER BY id')->fetchAll(\PDO::FETCH_ASSOC);

        if ($rows !== [] || ! hash_equals($fixture['snapshot'], hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR)))
            || (int) $pdo->query('SELECT COUNT(*) FROM uploadeds')->fetchColumn() !== 0) {
            throw new RuntimeException('Editor HTTP scope must preserve empty comments and uploads.');
        }
    }

    /** @param array<string, mixed> $manifest */
    private static function assertTimelineFixture(array $manifest, \PDO $pdo): void
    {
        $fixture = $manifest['timeline'] ?? null;
        if (! is_array($fixture) || ! isset($fixture['client_id'], $fixture['client_uuid'], $fixture['root_id'], $fixture['reply_id'], $fixture['snapshot'])
            || ! is_int($fixture['client_id']) || $fixture['client_id'] < 1
            || ! is_string($fixture['client_uuid']) || preg_match('/\A[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}\z/', $fixture['client_uuid']) !== 1
            || ! is_int($fixture['root_id']) || ! is_int($fixture['reply_id']) || $fixture['root_id'] < 1 || $fixture['reply_id'] < 1 || $fixture['root_id'] === $fixture['reply_id']
            || ! is_string($fixture['snapshot']) || preg_match('/\A[a-f0-9]{64}\z/', $fixture['snapshot']) !== 1) {
            throw new RuntimeException('Invalid owned Timeline fixture identifiers.');
        }
        $client = $pdo->prepare('SELECT uuid FROM clients WHERE id = ?');
        $client->execute([$fixture['client_id']]);
        if ($client->fetchColumn() !== $fixture['client_uuid']) {
            throw new RuntimeException('Timeline client does not belong to its owned schema.');
        }
        $query = $pdo->query('SELECT * FROM timeline_comments ORDER BY id');
        $rows = $query->fetchAll(\PDO::FETCH_ASSOC);
        if (count($rows) !== 2 || ! hash_equals($fixture['snapshot'], hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR)))) {
            throw new RuntimeException('Owned old Timeline rows were modified.');
        }
        foreach ($rows as $row) {
            if ($row['commentable_type'] !== Client::class
                || ! in_array((int) $row['id'], [$fixture['root_id'], $fixture['reply_id']], true)
                || (int) $row['commentable_id'] !== $fixture['client_id'] || $row['body_format'] !== 'html'
                || ((int) $row['id'] === $fixture['root_id'] ? $row['parent_id'] !== null : (int) $row['parent_id'] !== $fixture['root_id'])) {
                throw new RuntimeException('Timeline root/reply escaped the owned client fixture.');
            }
        }
    }

    /** @param array<string, mixed> $settings */
    private static function assertOwnedDirectories(array $settings): void
    {
        if (! isset($settings['path'], $settings['token']) || ! is_string($settings['path']) || ! is_string($settings['token'])
            || ! preg_match('/^[a-f0-9]{32}$/', $settings['token']) || basename($settings['path']) !== 'start-kit-mysql-'.$settings['token']
            || realpath(dirname($settings['path'])) !== realpath(sys_get_temp_dir()) || is_link($settings['path'])
            || ! is_file($settings['path'].'/owner') || file_get_contents($settings['path'].'/owner') !== $settings['token']) {
            throw new RuntimeException('Refusing an unowned HTTP sandbox directory.');
        }

        foreach (['http-sessions', 'http-environments', 'http-public'] as $directory) {
            if (! is_dir($settings['path'].'/'.$directory) || is_link($settings['path'].'/'.$directory)) {
                throw new RuntimeException('HTTP sandbox directories must be owned directories.');
            }
        }

    }

    /** @param array<string, mixed> $manifest */
    private static function assertUploadsFixture(array $manifest, \PDO $pdo): void
    {
        $fixture = $manifest['uploads'] ?? null;
        $names = ['html' => 'old-html.pdf', 'forged' => 'old-forged.pdf', 'pdf' => 'safe-preview.pdf', 'png' => 'safe-preview.png'];

        if (! is_array($fixture) || ! isset($fixture['client_id'], $fixture['client_uuid'], $fixture['files'], $fixture['snapshot'])
            || ! is_int($fixture['client_id']) || $fixture['client_id'] < 1
            || ! is_string($fixture['client_uuid']) || preg_match('/\A[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}\z/', $fixture['client_uuid']) !== 1
            || ! is_array($fixture['files']) || array_keys($fixture['files']) !== array_keys($names)
            || ! is_string($fixture['snapshot']) || preg_match('/\A[a-f0-9]{64}\z/', $fixture['snapshot']) !== 1) {
            throw new RuntimeException('Invalid owned Uploads fixture identifiers.');
        }

        $root = $manifest['settings']['path'].'/http-upload-files';
        if (! is_dir($root) || is_link($root) || realpath(dirname($root)) !== realpath($manifest['settings']['path'])
            || ! is_file($root.'/owner') || is_link($root.'/owner') || file_get_contents($root.'/owner') !== $manifest['settings']['token']) {
            throw new RuntimeException('Refusing an unowned HTTP upload disk.');
        }
        $entries = array_values(array_diff(scandir($root), ['.', '..']));
        $expected = ['owner', ...array_values($names)];
        sort($entries);
        sort($expected);
        if ($entries !== $expected) {
            throw new RuntimeException('The HTTP upload disk must contain only its fixed fixtures.');
        }

        $ids = [];
        foreach ($names as $key => $name) {
            $file = $fixture['files'][$key];
            if (! is_array($file) || ! isset($file['id'], $file['path'], $file['sha256'])
                || ! is_int($file['id']) || $file['id'] < 1 || in_array($file['id'], $ids, true)
                || $file['path'] !== $name || ! is_string($file['sha256']) || preg_match('/\A[a-f0-9]{64}\z/', $file['sha256']) !== 1
                || ! is_file($root.'/'.$name) || is_link($root.'/'.$name)
                || realpath($root.'/'.$name) !== realpath($root).DIRECTORY_SEPARATOR.$name
                || ! hash_equals($file['sha256'], hash_file('sha256', $root.'/'.$name))) {
                throw new RuntimeException('An owned HTTP upload file changed or escaped its disk.');
            }
            $ids[] = $file['id'];
        }

        $client = $pdo->prepare('SELECT uuid FROM clients WHERE id = ?');
        $client->execute([$fixture['client_id']]);
        if ($client->fetchColumn() !== $fixture['client_uuid']) {
            throw new RuntimeException('Uploads client does not belong to its owned schema.');
        }
        $allRows = $pdo->query('SELECT * FROM uploadeds ORDER BY id')->fetchAll(\PDO::FETCH_ASSOC);
        $rows = self::uploadsTransportEnabled()
            ? array_values(array_filter($allRows, fn (array $row): bool => in_array((int) $row['id'], $ids, true)))
            : $allRows;
        if (count($rows) !== 4 || ! hash_equals($fixture['snapshot'], hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR)))) {
            throw new RuntimeException('Owned old Uploads rows were modified.');
        }
        $paths = array_column($fixture['files'], 'path', 'id');
        foreach ($rows as $row) {
            if (! isset($paths[$row['id']]) || $row['parentable_type'] !== Client::class || (int) $row['parentable_id'] !== $fixture['client_id']
                || $row['disk'] !== 'authority_uploads' || $row['path'] !== $paths[$row['id']]) {
                throw new RuntimeException('An old upload escaped the owned client fixture.');
            }
        }
        if (self::uploadsTransportEnabled()) {
            self::assertUploadsTransportFixture($manifest, $pdo, array_values(array_filter($allRows, fn (array $row): bool => ! in_array((int) $row['id'], $ids, true))));
        }
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @param  list<array<string, mixed>>  $rows
     */
    private static function assertUploadsTransportFixture(array $manifest, \PDO $pdo, array $rows): void
    {
        $fixture = $manifest['uploads_transport'] ?? null;
        if (! is_array($fixture) || count($fixture) !== 5 || ! self::validTransportSubject($fixture)
            || ! isset($fixture['user_id'], $fixture['user_uuid'], $fixture['clients_snapshot'])
            || ! is_int($fixture['user_id']) || $fixture['user_id'] < 1
            || ! is_string($fixture['user_uuid']) || preg_match('/\A[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}\z/', $fixture['user_uuid']) !== 1
            || ! is_string($fixture['clients_snapshot']) || preg_match('/\A[a-f0-9]{64}\z/', $fixture['clients_snapshot']) !== 1
            || $fixture['client_id'] === $manifest['uploads']['client_id'] || $fixture['client_uuid'] === $manifest['uploads']['client_uuid']) {
            throw new RuntimeException('Invalid owned Uploads transport fixture.');
        }
        $clients = $pdo->query('SELECT * FROM clients ORDER BY id')->fetchAll(\PDO::FETCH_ASSOC);
        $transportClients = array_values(array_filter($clients, fn (array $row): bool => (int) $row['id'] === $fixture['client_id']));
        if (count($clients) !== 2 || count($transportClients) !== 1 || $transportClients[0]['uuid'] !== $fixture['client_uuid']
            || $transportClients[0]['name'] !== 'DEP001 Transport' || $transportClients[0]['email'] !== 'transport@authority.example.test'
            || ! hash_equals($fixture['clients_snapshot'], hash('sha256', json_encode($clients, JSON_THROW_ON_ERROR)))) {
            throw new RuntimeException('The two owned Uploads clients must remain unchanged.');
        }
        $user = $pdo->prepare('SELECT uuid, email FROM users WHERE id = ?');
        $user->execute([$fixture['user_id']]);
        $identity = $user->fetch(\PDO::FETCH_ASSOC);
        if ($identity === false || $identity['uuid'] !== $fixture['user_uuid'] || $identity['email'] !== 'developer@authority.example.test') {
            throw new RuntimeException('Transport uploads require the owned synthetic developer identity.');
        }
        foreach (['http-transport-files', 'http-upload-tmp'] as $directory) {
            $root = $manifest['settings']['path'].'/'.$directory;
            if (! is_dir($root) || is_link($root) || realpath(dirname($root)) !== realpath($manifest['settings']['path'])
                || ! is_file($root.'/owner') || is_link($root.'/owner') || file_get_contents($root.'/owner') !== $manifest['settings']['token']) {
                throw new RuntimeException('Transport storage and PHP upload temporary files must be owned.');
            }
        }
        $root = $manifest['settings']['path'].'/http-transport-files';
        if (count($rows) > 2) {
            throw new RuntimeException('The transport fixture permits at most two distinct uploads.');
        }
        $expectedPaths = ['owner'];
        $expectedDirectories = [];
        $originalNames = [];
        foreach ($rows as $row) {
            $spec = self::TRANSPORT_UPLOAD_FILES[$row['original_name']] ?? null;
            if ($spec === null || in_array($row['original_name'], $originalNames, true)
                || $row['parentable_type'] !== Client::class || (int) $row['parentable_id'] !== $fixture['client_id']
                || (int) $row['user_id'] !== $fixture['user_id'] || (int) $row['uploader_id'] !== $fixture['user_id']
                || $row['disk'] !== 'authority_transport' || $row['file_hash'] !== $spec['sha256'] || (int) $row['size'] !== $spec['size']
                || $row['extension'] !== $spec['extension'] || $row['mime_type'] !== $spec['mime'] || $row['source'] !== 'upload'
                || $row['tag'] !== null || $row['collection'] !== null || (bool) $row['is_cover'] || (bool) $row['is_favorite']
                || $row['name'] !== pathinfo($row['original_name'], PATHINFO_FILENAME)
                || ! is_string($row['path']) || preg_match('~\Auploads/client/'.preg_quote($fixture['client_uuid'], '~').'/[0-9]{4}/(?:0[1-9]|1[0-2])/[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}\.'.$spec['extension'].'\z~', $row['path']) !== 1
                || $row['file_name'] !== basename($row['path'])) {
                throw new RuntimeException('An upload escaped its exact neutral transport fixture.');
            }
            $path = $root.'/'.$row['path'];
            $stat = lstat($path);
            if ($stat === false || ! is_file($path) || is_link($path) || $stat['nlink'] !== 1
                || ! str_starts_with(str_replace('\\', '/', (string) realpath($path)), str_replace('\\', '/', (string) realpath($root)).'/')
                || ! hash_equals($spec['sha256'], hash_file('sha256', $path))) {
                throw new RuntimeException('A transport file changed or escaped its owned disk.');
            }
            $originalNames[] = $row['original_name'];
            $expectedPaths[] = $row['path'];
            $directory = dirname($row['path']);
            while ($directory !== '.') {
                $expectedDirectories[] = $directory;
                $directory = dirname($directory);
            }
        }
        $actualPaths = [];
        $actualDirectories = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
        foreach ($iterator as $entry) {
            $entryPath = $entry->getRealPath();
            if ($entry->isLink() || $entryPath === false || ! str_starts_with(str_replace('\\', '/', $entryPath), str_replace('\\', '/', (string) realpath($root)).'/')) {
                throw new RuntimeException('Transport inventory must not contain links or external paths.');
            }
            if ($entry->isFile()) {
                $actualPaths[] = str_replace('\\', '/', substr($entry->getPathname(), strlen($root) + 1));
            } elseif ($entry->isDir()) {
                $actualDirectories[] = str_replace('\\', '/', substr($entry->getPathname(), strlen($root) + 1));
            } else {
                throw new RuntimeException('Transport inventory must contain regular files and directories only.');
            }
        }
        sort($actualPaths);
        sort($expectedPaths);
        $expectedDirectories = array_values(array_unique($expectedDirectories));
        sort($actualDirectories);
        sort($expectedDirectories);
        if ($actualPaths !== $expectedPaths || $actualDirectories !== $expectedDirectories) {
            throw new RuntimeException('Transport disk inventory must match its exact uploaded rows.');
        }
    }

    /** @param array<string, mixed> $fixture */
    private static function validTransportSubject(array $fixture): bool
    {
        return isset($fixture['client_id'], $fixture['client_uuid']) && is_int($fixture['client_id']) && $fixture['client_id'] > 0
            && is_string($fixture['client_uuid']) && preg_match('/\A[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}\z/', $fixture['client_uuid']) === 1;
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $files
     * @param  array<string, mixed>  $fixture
     */
    public static function allowsTransportUpload(array $input, array $files, array $fixture): bool
    {
        if (! self::uploadsTransportEnabled() || ! self::validTransportSubject($fixture)
            || array_diff(array_keys($input), ['parentable_type', 'parentable_id']) !== [] || ($input['parentable_type'] ?? null) !== 'client'
            || ! in_array($input['parentable_id'] ?? null, [(string) $fixture['client_id'], $fixture['client_uuid']], true)
            || array_diff(array_keys($files), ['files']) !== []) {
            return false;
        }
        if ($files === []) {
            return true;
        }
        $uploads = $files['files'] ?? null;
        if (! is_array($uploads) || array_keys($uploads) !== [0] || ! is_array($uploads[0])
            || count($uploads[0]) !== 3 || ! isset($uploads[0]['name'], $uploads[0]['size'], $uploads[0]['sha256'])
            || ! is_string($uploads[0]['name']) || ! is_int($uploads[0]['size']) || ! is_string($uploads[0]['sha256'])) {
            return false;
        }
        $file = $uploads[0];
        $spec = self::TRANSPORT_UPLOAD_FILES[$file['name']] ?? null;

        return $file['size'] <= 2048 && $spec !== null && $file['size'] === $spec['size'] && $file['sha256'] === $spec['sha256'];
    }

    /** @param array<string, mixed> $settings */
    private static function assertDevToolsDirectories(array $settings): void
    {
        self::assertOwnedDirectories($settings);

        foreach (['http-devtools-env', 'http-devtools-storage'] as $directory) {
            $path = $settings['path'].'/'.$directory;

            if (! is_dir($path) || is_link($path) || ! is_file($path.'/owner') || file_get_contents($path.'/owner') !== $settings['token']) {
                throw new RuntimeException('Refusing a DevTools path outside the owned HTTP sandbox.');
            }
        }

        $envPath = $settings['path'].'/http-devtools-env/.env';

        if (! is_file($envPath) || is_link($envPath) || is_link($settings['path'].'/http-devtools-storage/framework')) {
            throw new RuntimeException('Refusing an unowned DevTools environment or maintenance directory.');
        }
    }

    /** @param array<string, mixed> $manifest */
    public static function bootApplication(array $manifest, TestEnvironment $environment): Application
    {
        $app = DisposableMysql::bootApplication($manifest['settings'], $environment);
        $origin = 'http://'.$manifest['host'].':'.$manifest['port'];
        $app['config']->set([
            'app.url' => $origin,
            'session.driver' => 'file',
            'session.files' => $manifest['settings']['path'].'/http-sessions',
            'session.cookie' => 'authority_http_'.substr($manifest['settings']['token'], 0, 16),
            'session.domain' => null,
            'session.path' => '/',
            'session.secure' => false,
            'session.http_only' => true,
            'session.same_site' => 'lax',
            'sanctum.stateful' => [$manifest['host'].':'.$manifest['port']],
            'permissions.functional' => array_values(array_unique([...$app['config']->get('permissions.functional', []), 'view-browser-fixture'])),
        ]);
        $app->make(Vite::class)->useHotFile($environment->path.'/disabled-vite.hot');

        if (self::selectionUiEnabled() || self::timelineUiEnabled()) {
            $actions = $app['config']->get('activity-actions.actions');
            if (! is_array($actions) || ! isset($actions['auth.login'], $actions['auth.logout'])) {
                throw new RuntimeException('Timeline UI requires the existing literal authentication action definitions.');
            }
            $actions['auth.login']['enabled'] = false;
            $actions['auth.logout']['enabled'] = false;
            $app['config']->set('activity-actions.actions', $actions);
            $app['config']->set('broadcasting.default', 'null');
        }

        if (self::uploadsUiEnabled()) {
            $app['config']->set([
                'filesystems.disks.authority_ui_sources' => ['driver' => 'local', 'root' => $manifest['settings']['path'].'/http-ui-files', 'visibility' => 'private', 'serve' => false, 'throw' => true],
                'filesystems.disks.local' => ['driver' => 'local', 'root' => $manifest['settings']['path'].'/http-ui-zips', 'visibility' => 'private', 'serve' => false, 'throw' => true],
                'broadcasting.default' => 'null',
            ]);
            $app->make('filesystem')->forgetDisk(['local', 'authority_ui_sources']);
        }

        if (self::exportNotificationEnabled()) {
            $root = self::assertExportNotificationDirectory($manifest['settings']);
            $app['config']->set([
                'exports.disk' => 'local',
                'exports.directory' => 'exports',
                'filesystems.disks.local' => ['driver' => 'local', 'root' => $root, 'visibility' => 'private', 'serve' => false, 'throw' => true],
            ]);
            $app->make('filesystem')->forgetDisk('local');
        }

        if (self::integrationsEnabled() && isset($manifest['integration_settings'])) {
            self::integrationSettingsRows($manifest);
            $app['config']->set([
                'slack.enabled' => true,
                'slack.webhook_url' => self::INTEGRATION_WEBHOOKS['environment'],
                'slack.app_name' => 'SEC004 Integration Fixture',
                'slack.app_url' => $origin,
                'slack.environment' => 'testing',
                'slack.log_level' => 'warning',
                'logging.default' => 'null',
                'external-apis.diagnostics.enabled' => true,
            ]);
            Http::preventStrayRequests();
            Http::fake(function (ClientRequest $request): PromiseInterface {
                if ($request->method() !== 'POST' || ! in_array($request->url(), self::INTEGRATION_WEBHOOKS, true)) {
                    throw new RuntimeException('Only synthetic Slack destinations may reach the integration fake.');
                }

                return Http::response('synthetic Slack response', $request->url() === self::INTEGRATION_WEBHOOKS['replacement'] ? 500 : 200);
            });
            Cache::put('external-api:diagnostics:recent-requests', [[
                'id' => '40000000-0000-4000-8000-000000000004', 'module' => 'spotify', 'mode' => 'fake',
                'method' => 'GET', 'endpoint' => '/tracks?token=SEC004_PRIVATE_SENTINEL',
                'url' => 'https://provider.example.test/path?token=SEC004_PRIVATE_SENTINEL',
                'status' => 'failed', 'status_code' => 500, 'duration_ms' => 12, 'retry_count' => 0, 'cache_hit' => false,
                'recorded_at' => '2026-01-01T00:00:00+00:00',
                'error' => 'SEC004_PRIVATE_SENTINEL external error',
                'body' => ['private' => 'SEC004_PRIVATE_SENTINEL body'],
                'headers' => ['Authorization' => 'Bearer SEC004_PRIVATE_SENTINEL'],
            ]]);
        }

        if (($manifest['mode'] ?? 'authority') === 'pulse') {
            if (getenv('AUTHORITY_HTTP_PULSE_TESTS') !== '1') {
                throw new RuntimeException('Pulse runtime requires its explicit HTTP test scope.');
            }

            self::assertPulseFixture($manifest, DisposableMysql::verifiedConnection($manifest['settings']));
            if (EndpointResolver::updatePath() !== $manifest['pulse']['update_path']) {
                throw new RuntimeException('The Pulse update endpoint must match the owned application configuration.');
            }

            $app['config']->set('pulse.enabled', false);
            $app->make(Pulse::class)->stopRecording();
            Gate::define('viewPulse', fn (?User $user = null): bool => $user !== null
                && $user->getKey() === $manifest['pulse']['user_id']
                && (string) $user->getAttribute('uuid') === $manifest['pulse']['user_uuid']);
        }

        if (($manifest['mode'] ?? 'authority') === 'uploads') {
            self::assertUploadsFixture($manifest, DisposableMysql::verifiedConnection($manifest['settings']));
            $app['config']->set('filesystems.disks.authority_uploads', [
                'driver' => 'local', 'root' => $manifest['settings']['path'].'/http-upload-files', 'throw' => true,
            ]);
            if (self::uploadsTransportEnabled()) {
                $app['config']->set('filesystems.disks.authority_transport', [
                    'driver' => 'local', 'root' => $manifest['settings']['path'].'/http-transport-files', 'throw' => true,
                ]);
                $app['config']->set('uploads.disk', 'authority_transport');
            }
        }

        if (($manifest['mode'] ?? 'authority') === 'devtools') {
            if (getenv('AUTHORITY_HTTP_DEVTOOLS_TESTS') !== '1') {
                throw new RuntimeException('DevTools runtime requires its explicit HTTP test scope.');
            }

            self::assertDevToolsDirectories($manifest['settings']);
            $app->useEnvironmentPath($manifest['settings']['path'].'/http-devtools-env');
            $app->useStoragePath($manifest['settings']['path'].'/http-devtools-storage');
            $app['config']->set('app.maintenance.driver', 'file');
            $settings = Dotenv::parse((string) file_get_contents($app->environmentFilePath()));

            foreach (DevToolsService::OPERATIONAL_CONFIG_KEYS as $key => $configKey) {
                if (! isset($settings[$key]) || ! is_string($settings[$key])) {
                    throw new RuntimeException('The owned DevTools fixture must contain every operational setting.');
                }

                $value = DevToolsService::validateOperationalSetting($key, $settings[$key]);
                $app['config']->set($configKey, $key === 'TELESCOPE_ENABLED' ? $value === 'true' : $value);
            }
        }

        return $app;
    }

    /** @param array<string, mixed> $manifest */
    public static function registerEnvironment(array $manifest, TestEnvironment $environment): string
    {
        file_put_contents($environment->path.'/authority-owner', $manifest['settings']['token']);
        $registry = $manifest['settings']['path'].'/http-environments/'.basename($environment->path);
        file_put_contents($registry, $environment->path);

        return $registry;
    }

    /** @param array<string, mixed> $manifest */
    public static function cleanUpEnvironments(array $manifest): void
    {
        self::assertOwnedDirectories($manifest['settings']);

        foreach ((new Filesystem)->files($manifest['settings']['path'].'/http-environments') as $entry) {
            $path = trim((string) file_get_contents($entry->getPathname()));

            if (! file_exists($path)) {
                continue;
            }

            if (realpath(dirname($path)) !== realpath(sys_get_temp_dir()) || ! preg_match('/^start-kit-tests-[a-f0-9]{32}$/', basename($path))
                || is_link($path) || ! is_file($path.'/authority-owner') || file_get_contents($path.'/authority-owner') !== $manifest['settings']['token']) {
                throw new RuntimeException('Refusing cleanup of an unowned HTTP request environment.');
            }

            (new Filesystem)->deleteDirectory($path);

            if (file_exists($path)) {
                throw new RuntimeException('HTTP request environment cleanup failed.');
            }
        }
    }

    public static function assetPath(string $requestPath, string $publicRoot): ?string
    {
        $path = rawurldecode($requestPath);

        if (str_contains($path, '..') || str_contains($path, '\\') || str_contains($path, "\0")) {
            return null;
        }

        $favicons = ['/favicon.ico', '/favicon.svg', '/apple-touch-icon.png'];
        $build = str_starts_with($path, '/build/');

        if (! $build && ! in_array($path, $favicons, true)) {
            return null;
        }

        if (is_link($publicRoot.'/build') || is_link($publicRoot.$path)) {
            return null;
        }

        $root = realpath($build ? $publicRoot.'/build' : $publicRoot);
        $file = realpath($publicRoot.$path);

        if ($root === false || $file === false || ! is_file($file) || ! str_starts_with(str_replace('\\', '/', $file), str_replace('\\', '/', $root).'/')
            || ! in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['js', 'css', 'svg', 'ico', 'png', 'jpg', 'jpeg', 'webp', 'gif', 'woff', 'woff2', 'ttf'], true)) {
            return null;
        }

        return $file;
    }

    public static function contentSecurityPolicy(string $mode = 'authority'): string
    {
        return "default-src 'self'; script-src 'self' 'unsafe-inline'".($mode === 'pulse' ? " 'unsafe-eval'" : '')."; style-src 'self' 'unsafe-inline'; font-src 'self'; img-src 'self' data:; connect-src 'self'";
    }

    /**
     * @param  array<string, mixed>|null  $uploadFixture
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $transportFixture
     * @param  array<string, mixed>|null  $usersFixture
     * @param  array<string, mixed>|null  $integrationFixture
     */
    public static function allowsRequest(string $path, string $mode = 'authority', string $method = 'GET', ?string $timelineClient = null, ?array $uploadFixture = null, array $query = [], ?string $pulseUpdatePath = null, ?array $transportFixture = null, ?array $usersFixture = null, ?array $activityFixture = null, ?array $integrationFixture = null, ?array $passwordFixture = null, ?array $passwordBody = null, ?array $verificationFixture = null, ?array $verificationBody = null, ?array $exportNotificationFixture = null, ?array $exportNotificationBody = null, ?array $notificationUiFixture = null, ?array $notificationUiBody = null, ?array $uploadsUiFixture = null, ?array $uploadsUiBody = null, ?array $timelineUiFixture = null, ?array $timelineUiBody = null, ?array $selectionUiFixture = null, ?array $selectionUiBody = null): bool
    {
        if (getenv('AUTHORITY_HTTP_SELECTION_UI_TESTS') === '1') {
            if ($mode !== 'authority' || $selectionUiFixture === null) {
                return false;
            }
            foreach ([$timelineClient, $uploadFixture, $pulseUpdatePath, $transportFixture, $usersFixture, $activityFixture, $integrationFixture, $passwordFixture, $passwordBody, $verificationFixture, $verificationBody, $exportNotificationFixture, $exportNotificationBody, $notificationUiFixture, $notificationUiBody, $uploadsUiFixture, $uploadsUiBody, $timelineUiFixture, $timelineUiBody] as $foreignFixture) {
                if ($foreignFixture !== null) {
                    return false;
                }
            }

            return self::allowsSelectionUiRequest($path, $method, $selectionUiFixture, $query, $selectionUiBody);
        }
        if ($selectionUiFixture !== null || $selectionUiBody !== null) {
            return false;
        }
        if (getenv('AUTHORITY_HTTP_TIMELINE_UI_TESTS') === '1') {
            if ($mode !== 'authority' || $timelineUiFixture === null) {
                return false;
            }
            foreach ([$timelineClient, $uploadFixture, $pulseUpdatePath, $transportFixture, $usersFixture, $activityFixture, $integrationFixture, $passwordFixture, $passwordBody, $verificationFixture, $verificationBody, $exportNotificationFixture, $exportNotificationBody, $notificationUiFixture, $notificationUiBody, $uploadsUiFixture, $uploadsUiBody] as $foreignFixture) {
                if ($foreignFixture !== null) {
                    return false;
                }
            }

            return self::allowsTimelineUiRequest($path, $method, $timelineUiFixture, $query, $timelineUiBody);
        }
        if ($timelineUiFixture !== null || $timelineUiBody !== null) {
            return false;
        }
        if (getenv('AUTHORITY_HTTP_UPLOADS_UI_TESTS') === '1') {
            if ($mode !== 'authority' || $uploadsUiFixture === null || $timelineClient !== null || $uploadFixture !== null || $pulseUpdatePath !== null
                || $transportFixture !== null || $usersFixture !== null || $activityFixture !== null || $integrationFixture !== null || $passwordFixture !== null
                || $passwordBody !== null || $verificationFixture !== null || $verificationBody !== null || $exportNotificationFixture !== null || $exportNotificationBody !== null
                || $notificationUiFixture !== null || $notificationUiBody !== null) {
                return false;
            }

            return self::allowsUploadsUiRequest($path, $method, $uploadsUiFixture, $query, $uploadsUiBody);
        }
        if ($uploadsUiFixture !== null || $uploadsUiBody !== null) {
            return false;
        }
        if (getenv('AUTHORITY_HTTP_NOTIFICATION_UI_TESTS') === '1') {
            if (! self::notificationUiEnabled() || $mode !== 'authority' || $notificationUiFixture === null || ! self::validNotificationUiFixture($notificationUiFixture)
                || $timelineClient !== null || $uploadFixture !== null || $pulseUpdatePath !== null || $transportFixture !== null || $usersFixture !== null || $activityFixture !== null
                || $integrationFixture !== null || $passwordFixture !== null || $passwordBody !== null || $verificationFixture !== null || $verificationBody !== null
                || $exportNotificationFixture !== null || $exportNotificationBody !== null) {
                return false;
            }
            if ($method === 'GET' && $notificationUiBody === null) {
                if ($path !== '/api/v1/notifications') {
                    return $query === [] && in_array($path, ['/', '/login', '/sanctum/csrf-cookie', '/dashboard', '/notifications', '/api/v1/notifications/unread', '/api/v1/notifications/unread-count', '/__ui_notification_event'], true);
                }
                foreach ($query as $key => $value) {
                    if (! is_string($value) || ! match ($key) {
                        'type' => in_array($value, ['system', 'warning', 'success'], true),
                        'read' => in_array($value, ['true', 'false'], true),
                        'page' => in_array($value, ['1', '2', '3'], true),
                        default => false,
                    }) {
                        return false;
                    }
                }

                return true;
            }
            if ($query !== []) {
                return false;
            }
            if ($method === 'POST' && $path === '/login' && $notificationUiBody !== null) {
                $body = $notificationUiBody;
                if (isset($body['remember']) && in_array($body['remember'], [false, '0'], true)) {
                    unset($body['remember']);
                }

                return count($body) === 2 && in_array($body['email'] ?? null, ['admin@authority.example.test', 'ordinary@authority.example.test'], true)
                    && ($body['password'] ?? null) === 'BrowserFixture-123!';
            }
            if ($method === 'POST' && in_array($path, ['/logout', '/api/v1/notifications/mark-all-read'], true)) {
                return $notificationUiBody === [];
            }
            if (in_array($method, ['PATCH', 'DELETE'], true) && $notificationUiBody === []) {
                $known = [...$notificationUiFixture['notification_uuids'], ...$notificationUiFixture['observer_uuids']];
                if ($notificationUiFixture['published'] || $method === 'PATCH') {
                    $known[] = $notificationUiFixture['event_uuid'];
                }
                foreach ($known as $uuid) {
                    if ($path === '/api/v1/notifications/'.$uuid) {
                        return true;
                    }
                }
            }

            return false;
        }
        if ($notificationUiFixture !== null || $notificationUiBody !== null) {
            return false;
        }
        if (getenv('AUTHORITY_HTTP_EXPORT_NOTIFICATION_TESTS') === '1') {
            if ($mode !== 'authority' || ! self::exportNotificationEnabled() || $exportNotificationFixture === null || ! self::validExportNotificationFixture($exportNotificationFixture)
                || $timelineClient !== null || $uploadFixture !== null || $pulseUpdatePath !== null || $transportFixture !== null
                || $usersFixture !== null || $activityFixture !== null || $integrationFixture !== null || $passwordFixture !== null
                || $passwordBody !== null || $verificationFixture !== null || $verificationBody !== null || $query !== []) {
                return false;
            }
            if ($method === 'POST' && $path === '/login' && $exportNotificationBody !== null) {
                $body = $exportNotificationBody;
                if (isset($body['remember']) && in_array($body['remember'], [false, '0'], true)) {
                    unset($body['remember']);
                }

                return count($body) === 2 && isset($body['email'], $body['password'])
                    && in_array($body['email'], ['admin@authority.example.test', 'ordinary@authority.example.test'], true)
                    && $body['password'] === 'BrowserFixture-123!';
            }
            if ($method === 'POST' && $path === '/logout') {
                return $exportNotificationBody === [];
            }
            if ($method === 'PATCH') {
                return $path === '/api/v1/notifications/'.$exportNotificationFixture['notification_uuid'] && $exportNotificationBody === [];
            }

            return $method === 'GET' && $exportNotificationBody === null
                && in_array($path, ['/', '/login', '/sanctum/csrf-cookie', '/dashboard', '/notifications', '/api/v1/notifications', '/api/v1/notifications/unread', '/api/v1/notifications/unread-count', '/exportacoes', '/exports/'.$exportNotificationFixture['export_uuid'].'/download'], true);
        }
        if ($exportNotificationFixture !== null || $exportNotificationBody !== null) {
            return false;
        }
        if (getenv('AUTHORITY_HTTP_VERIFICATION_TESTS') === '1') {
            if ($mode !== 'authority' || ! self::verificationEnabled() || $verificationFixture === null || ! self::validVerificationFixture($verificationFixture)
                || $timelineClient !== null || $uploadFixture !== null || $pulseUpdatePath !== null || $transportFixture !== null
                || $usersFixture !== null || $activityFixture !== null || $integrationFixture !== null || $passwordFixture !== null || $passwordBody !== null) {
                return false;
            }
            if (in_array($method, ['POST', 'PATCH'], true)) {
                return $verificationBody !== null && self::allowsVerificationBody($path, $verificationBody)
                    && ($method === 'PATCH' ? $path === '/settings/profile' && $query === []
                        : ($path === '/settings/profile' ? false : ($path === '/settings/force-password-change' ? $query === ['_method' => 'PUT'] : $query === [])));
            }
            if ($method !== 'GET' || $verificationBody !== null) {
                return false;
            }
            if ($query === [] && in_array($path, ['/', '/login', '/forgot-password', '/sanctum/csrf-cookie', '/dashboard', '/email/verify', '/settings/profile', '/settings/force-password-change', '/api/v1/auth/me', '/api/v1/notifications/unread', '/api/v1/notifications/unread-count'], true)) {
                return true;
            }
            foreach ($verificationFixture['signed_links'] as $links) {
                foreach ($links as $link) {
                    if ($path === $link['path'] && $query === $link['query']) {
                        return true;
                    }
                }
            }

            return false;
        }
        if ($verificationFixture !== null || $verificationBody !== null) {
            return false;
        }
        if (getenv('AUTHORITY_HTTP_PASSWORD_TESTS') === '1') {
            if ($mode !== 'authority' || ! self::passwordEnabled() || $passwordFixture === null || ! self::validPasswordFixture($passwordFixture)
                || $timelineClient !== null || $uploadFixture !== null || $pulseUpdatePath !== null || $transportFixture !== null
                || $usersFixture !== null || $activityFixture !== null || $integrationFixture !== null) {
                return false;
            }
            if ($method === 'POST') {
                return $passwordBody !== null && self::allowsPasswordBody($path, $passwordBody)
                    && ($path === '/settings/force-password-change' ? $query === ['_method' => 'PUT'] : $query === []);
            }

            return $method === 'GET' && $query === [] && $passwordBody === null
                && in_array($path, ['/', '/login', '/sanctum/csrf-cookie', '/dashboard', '/settings/force-password-change', '/api/v1/auth/me', '/api/v1/notifications/unread', '/api/v1/notifications/unread-count', '/api/v1/clients'], true);
        }
        if ($passwordFixture !== null || $passwordBody !== null) {
            return false;
        }
        if (getenv('AUTHORITY_HTTP_INTEGRATIONS_TESTS') === '1') {
            if ($mode !== 'authority' || ! self::integrationsEnabled() || $integrationFixture === null || ! self::validIntegrationFixture($integrationFixture)
                || $timelineClient !== null || $uploadFixture !== null || $pulseUpdatePath !== null || $transportFixture !== null || $usersFixture !== null || $activityFixture !== null || $query !== []) {
                return false;
            }
            if ($method === 'POST') {
                return in_array($path, ['/login', '/logout', '/admin/slack/test'], true);
            }
            if ($method === 'PUT') {
                return $path === '/admin/slack';
            }

            return $method === 'GET' && in_array($path, ['/login', '/sanctum/csrf-cookie', '/dashboard', '/admin/slack', '/external-api/diagnostics', '/api/v1/notifications/unread', '/api/v1/notifications/unread-count'], true);
        }
        if ($integrationFixture !== null) {
            return false;
        }

        if (getenv('AUTHORITY_HTTP_ACTIVITY_TESTS') === '1') {
            if ($mode !== 'authority' || ! self::activityReadEnabled() || $activityFixture === null || ! self::validActivityReadFixture($activityFixture)) {
                return false;
            }
            if ($method === 'POST') {
                return $query === [] && in_array($path, ['/login', '/logout'], true);
            }
            if ($method !== 'GET') {
                return false;
            }
            if ($path === '/api/v1/activity-log') {
                return self::allowsActivityQuery($query);
            }

            return $query === [] && in_array($path, ['/login', '/sanctum/csrf-cookie', '/dashboard', '/activity-log', '/admin/dev/components/activity-actions', '/api/v1/notifications/unread', '/api/v1/notifications/unread-count'], true);
        }
        if ($activityFixture !== null) {
            return false;
        }

        if (getenv('AUTHORITY_HTTP_USERS_TESTS') === '1') {
            if ($mode !== 'authority' || ! self::usersReadEnabled() || $usersFixture === null || ! self::validUsersReadFixture($usersFixture)) {
                return false;
            }
            if ($method === 'POST') {
                return $query === [] && in_array($path, ['/login', '/logout'], true);
            }
            if ($method !== 'GET') {
                return false;
            }
            if ($path === '/api/v1/users') {
                return array_diff(array_keys($query), ['page', 'per_page', 'search']) === []
                    && (! array_key_exists('page', $query) || in_array($query['page'], ['1', '2'], true))
                    && (! array_key_exists('per_page', $query) || in_array($query['per_page'], ['15', '30'], true))
                    && (! array_key_exists('search', $query) || (is_string($query['search']) && preg_match('//u', $query['search']) === 1 && strlen($query['search']) <= 80));
            }
            if ($query !== []) {
                return false;
            }
            if (in_array($path, ['/login', '/sanctum/csrf-cookie', '/dashboard', '/users', '/users/create', '/api/v1/notifications/unread', '/api/v1/notifications/unread-count'], true)) {
                return true;
            }
            foreach ($usersFixture['identities'] as $identity) {
                if (in_array($path, ['/users/'.$identity['uuid'], '/users/'.$identity['uuid'].'/edit', '/users/'.$identity['uuid'].'/roles', '/users/'.$identity['uuid'].'/permissions'], true)) {
                    return true;
                }
            }

            return false;
        }
        if ($usersFixture !== null) {
            return false;
        }

        if (! in_array($mode, ['authority', 'devtools', 'timeline', 'uploads', 'editor', 'pulse'], true)
            || (getenv('AUTHORITY_HTTP_UPLOADS_TRANSPORT_TESTS') === '1' && ($mode !== 'uploads' || ! self::uploadsTransportEnabled()))
            || ($mode !== 'pulse' && getenv('AUTHORITY_HTTP_PULSE_TESTS') === '1')
            || ($mode !== 'editor' && getenv('AUTHORITY_HTTP_EDITOR_TESTS') === '1')) {
            return false;
        }

        if ($mode === 'pulse') {
            if (getenv('AUTHORITY_HTTP_PULSE_TESTS') !== '1' || getenv('AUTHORITY_HTTP_EDITOR_TESTS') === '1'
                || getenv('AUTHORITY_HTTP_DEVTOOLS_TESTS') === '1' || getenv('AUTHORITY_HTTP_TIMELINE_TESTS') === '1' || getenv('AUTHORITY_HTTP_UPLOADS_TESTS') === '1') {
                return false;
            }

            if ($method === 'POST') {
                return $query === [] && (in_array($path, ['/login', '/logout'], true)
                    || ($pulseUpdatePath !== null && preg_match('~\A/livewire-[a-f0-9]{8}/update\z~', $pulseUpdatePath) === 1 && $path === $pulseUpdatePath));
            }

            if ($method !== 'GET') {
                return false;
            }

            if (in_array($path, ['/login', '/sanctum/csrf-cookie'], true)) {
                return $query === [];
            }

            return $path === '/pulse' && ($query === [] || (count($query) === 1 && isset($query['period']) && is_string($query['period'])
                && in_array($query['period'], ['1_hour', '6_hours', '24_hours', '7_days'], true)));
        }

        if ($mode === 'editor') {
            if (getenv('AUTHORITY_HTTP_EDITOR_TESTS') !== '1' || getenv('AUTHORITY_HTTP_DEVTOOLS_TESTS') === '1'
                || getenv('AUTHORITY_HTTP_TIMELINE_TESTS') === '1' || getenv('AUTHORITY_HTTP_UPLOADS_TESTS') === '1') {
                return false;
            }

            if ($method === 'POST') {
                return $query === [] && in_array($path, ['/login', '/logout'], true);
            }

            if ($method !== 'GET') {
                return false;
            }

            if (in_array($path, ['/login', '/sanctum/csrf-cookie', '/admin/dev/components/rich-text-editor', '/admin/dev/components/activity-timeline', '/api/v1/notifications/unread', '/api/v1/notifications/unread-count'], true)) {
                return $query === [];
            }

            if ($timelineClient === null || preg_match('/\A[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}\z/', $timelineClient) !== 1
                || $path !== '/api/v1/timeline/client/'.$timelineClient) {
                return false;
            }

            return $query === [] || (count($query) === 1 && isset($query['limit']) && is_string($query['limit'])
                && preg_match('/\A(?:[1-9][0-9]?|100)\z/', $query['limit']) === 1);
        }

        if ($mode === 'uploads') {
            if (getenv('AUTHORITY_HTTP_UPLOADS_TESTS') !== '1' || getenv('AUTHORITY_HTTP_DEVTOOLS_TESTS') === '1' || getenv('AUTHORITY_HTTP_TIMELINE_TESTS') === '1') {
                return false;
            }
            if ($method === 'POST') {
                return $query === [] && (in_array($path, ['/login', '/logout'], true)
                    || ($path === '/api/v1/uploads' && self::uploadsTransportEnabled() && $transportFixture !== null && self::validTransportSubject($transportFixture)));
            }
            if ($method !== 'GET') {
                return false;
            }
            if (self::uploadsTransportEnabled() && $transportFixture !== null && self::validTransportSubject($transportFixture)) {
                $uploadedIds = array_key_exists('uploaded_ids', $transportFixture) ? $transportFixture['uploaded_ids'] : [];
                if (! is_array($uploadedIds) || ! array_is_list($uploadedIds) || count($uploadedIds) > 2) {
                    return false;
                }
                foreach ($uploadedIds as $id) {
                    if (! is_int($id) || $id < 1) {
                        return false;
                    }
                }
                if (count(array_unique($uploadedIds)) !== count($uploadedIds)) {
                    return false;
                }
                foreach ($uploadedIds as $id) {
                    if ($path === '/uploads/'.$id.'/preview') {
                        return $query === [];
                    }
                }
                if (in_array($path, ['/clients', '/clients/create', '/clients/'.$transportFixture['client_uuid'], '/clients/'.$transportFixture['client_uuid'].'/edit', '/api/v1/clients/'.$transportFixture['client_uuid'], '/dashboard'], true)) {
                    return $query === [];
                }
                if ($path === '/api/v1/clients') {
                    return array_diff(array_keys($query), ['page', 'per_page', 'search']) === []
                        && (! array_key_exists('page', $query) || in_array($query['page'], ['1', '2'], true))
                        && (! array_key_exists('per_page', $query) || in_array($query['per_page'], ['15', '30'], true))
                        && (! array_key_exists('search', $query) || (is_string($query['search']) && preg_match('//u', $query['search']) === 1 && strlen($query['search']) <= 80));
                }
                if ($path === '/__uploads_control') {
                    return false;
                }
                if ($path === '/api/v1/uploads' && count($query) === 2 && ($query['parentable_type'] ?? null) === 'client'
                    && in_array($query['parentable_id'] ?? null, [(string) $transportFixture['client_id'], $transportFixture['client_uuid']], true)) {
                    return true;
                }
            }
            if (in_array($path, ['/login', '/sanctum/csrf-cookie', '/admin/dev/components/file-attachments', '/__uploads_control', '/api/v1/notifications/unread', '/api/v1/notifications/unread-count'], true)) {
                return $query === [];
            }
            if (! is_array($uploadFixture) || ! isset($uploadFixture['client_id'], $uploadFixture['client_uuid'], $uploadFixture['files'])
                || ! is_int($uploadFixture['client_id']) || $uploadFixture['client_id'] < 1 || ! is_string($uploadFixture['client_uuid'])
                || preg_match('/\A[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}\z/', $uploadFixture['client_uuid']) !== 1 || ! is_array($uploadFixture['files'])) {
                return false;
            }
            if ($path === '/api/v1/uploads/tags') {
                return $query === ['parentable_type' => 'client'];
            }
            if ($path === '/api/v1/uploads') {
                return count($query) === 2 && ($query['parentable_type'] ?? null) === 'client'
                    && in_array($query['parentable_id'] ?? null, [(string) $uploadFixture['client_id'], $uploadFixture['client_uuid']], true);
            }
            if ($query !== []) {
                return false;
            }
            foreach ($uploadFixture['files'] as $file) {
                if (is_array($file) && isset($file['id']) && is_int($file['id']) && $file['id'] > 0
                    && in_array($path, ['/uploads/'.$file['id'].'/preview', '/uploads/'.$file['id'].'/download'], true)) {
                    return true;
                }
            }

            return false;
        }

        if ($mode === 'timeline') {
            if (getenv('AUTHORITY_HTTP_TIMELINE_TESTS') !== '1' || getenv('AUTHORITY_HTTP_DEVTOOLS_TESTS') === '1') {
                return false;
            }
            if (($method === 'GET' && in_array($path, ['/login', '/sanctum/csrf-cookie', '/admin/dev/components/activity-timeline', '/__timeline_control', '/api/v1/notifications/unread', '/api/v1/notifications/unread-count'], true))
                || ($method === 'POST' && in_array($path, ['/login', '/logout'], true))) {
                return true;
            }

            return $method === 'GET' && $timelineClient !== null
                && preg_match('/\A[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}\z/', $timelineClient) === 1
                && $path === '/api/v1/timeline/client/'.$timelineClient;
        }

        if ($mode === 'devtools' && getenv('AUTHORITY_HTTP_DEVTOOLS_TESTS') === '1') {
            if ($method === 'GET' && in_array($path, ['/admin/dev', '/admin/dev/dev-tools'], true)) {
                return true;
            }

            if ($method === 'POST' && in_array($path, ['/admin/dev/dev-tools/env/update', '/admin/dev/dev-tools/maintenance/toggle'], true)) {
                return true;
            }
        }

        if (in_array($path, ['/', '/login', '/logout', '/dashboard', '/sanctum/csrf-cookie', '/settings/profile'], true)) {
            return true;
        }

        return preg_match('~^/(?:roles|permissions)(?:/[0-9]+(?:/(?:edit|permissions))?)?/?$~', $path) === 1
            || preg_match('~^/users(?:/[a-f0-9-]{36}(?:/(?:edit|roles|permissions))?)?/?$~', $path) === 1
            || preg_match('~^/api/v1/(?:roles|permissions)(?:/[0-9]+(?:/sync-permissions)?)?/?$~', $path) === 1
            || preg_match('~^/api/v1/users(?:/[a-f0-9-]{36}/sync-roles-permissions)?/?$~', $path) === 1
            || preg_match('~^/api/v1/(?:auth/(?:login|me|logout|logout-all)|notifications/(?:unread|unread-count))$~', $path) === 1;
    }
}
