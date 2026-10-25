<?php

use App\Models\User;
use App\Modules\Clients\Models\Client;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Permissions\Models\Role;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Tests\AuthorityHttpEnvironment;

require dirname(__DIR__).'/vendor/autoload.php';
require __DIR__.'/closure-browser-bootstrap.php';

function closureActorAuthorityHash(int $id): string
{
    return hash('sha256', json_encode([
        DB::table('users_roles')->where('user_id', $id)->orderBy('role_id')->get()->all(),
        DB::table('users_permissions')->where('user_id', $id)->orderBy('permission_id')->get()->all(),
    ], JSON_THROW_ON_ERROR));
}

$runtime = null;
$exitCode = 0;
$stage = 'guard';
try {
    AuthorityHttpEnvironment::configureGlobals(true);
    optionalRuntimeAssertCopy();
    if (getenv('ARC002_BROWSER_TESTS') !== '1' || count($argv) !== 4 || ! in_array($argv[1], ['seed', 'verify'], true)) {
        throw new RuntimeException('Only an explicitly owned closure operation is allowed.');
    }
    $manifest = AuthorityHttpEnvironment::readManifest($argv[2]);
    if ($manifest['mode'] !== 'authority' || realpath(dirname($argv[3])) !== realpath($manifest['settings']['path'])
        || basename($argv[3]) !== 'closure-browser.json') {
        throw new RuntimeException('Closure state must stay inside its owned database destination.');
    }
    if ($argv[1] === 'seed') {
        if (file_exists($argv[3]) || is_link($argv[3])) {
            throw new RuntimeException('The closure seed destination must be new.');
        }
        $stage = 'seed-boot';
        $runtime = closureBrowserBoot($manifest);
        $stage = 'seed-migrations';
        if ($runtime['app']->make(Kernel::class)->call('migrate', ['--force' => true, '--no-interaction' => true]) !== 0) {
            throw new RuntimeException('Closure disposable migration failed.');
        }
        $roles = [];
        $stage = 'seed-essential-roles';
        foreach (['developer', 'super-admin', 'admin', 'user'] as $slug) {
            $roles[$slug] = Role::query()->create(['name' => $slug, 'slug' => $slug]);
        }
        $stage = 'seed-actor';
        $actor = User::factory()->create(['name' => 'ARC Browser Maintainer', 'email' => 'arc-maintainer@example.test',
            'password' => 'ArcFixture-123!', 'email_verified_at' => now(), 'must_change_password' => false]);
        $stage = 'seed-actor-roles';
        $actor->roles()->sync([$roles['developer']->id, $roles['admin']->id]);
        $stage = 'seed-functional-permissions';
        foreach (['view-users', 'delete-users', 'view-clients', 'delete-clients'] as $slug) {
            $permission = Permission::query()->create(['name' => $slug, 'slug' => $slug]);
            $actor->permissions()->attach($permission->id);
        }
        $stage = 'seed-target-user';
        $target = User::factory()->create(['name' => 'ARC Delete User', 'email' => 'arc-target@example.test',
            'email_verified_at' => now(), 'must_change_password' => false]);
        $target->roles()->sync([$roles['user']->id]);
        $stage = 'seed-client';
        $client = Client::factory()->create(['name' => 'ARC Delete Client', 'email' => 'arc-client@example.test']);
        $stage = 'seed-editable-role';
        $role = Role::query()->create(['name' => 'ARC Editable Role', 'slug' => 'arc-editable-role']);
        $stage = 'seed-editable-permission';
        $permission = Permission::query()->create(['name' => 'ARC Editable Permission', 'slug' => 'create-clients']);
        $stage = 'seed-fixture-ledger';
        $fixture = [
            'token' => $manifest['settings']['token'],
            'actor' => ['id' => $actor->id, 'uuid' => $actor->uuid, 'email' => $actor->email, 'password' => 'ArcFixture-123!'],
            'target_user' => ['id' => $target->id, 'uuid' => $target->uuid],
            'client' => ['id' => $client->id, 'uuid' => $client->uuid],
            'role' => ['id' => $role->id, 'slug' => $role->slug],
            'permission' => ['id' => $permission->id, 'slug' => $permission->slug],
            'actor_authority_hash' => closureActorAuthorityHash($actor->id),
        ];
        file_put_contents($argv[3], json_encode($fixture, JSON_THROW_ON_ERROR));
        closureBrowserInput($argv[2], $argv[3]);
        echo json_encode(['seeded' => true], JSON_THROW_ON_ERROR)."\n";

        return;
    }
    $stage = 'verify-input';
    $input = closureBrowserInput($argv[2], $argv[3]);
    $fixture = $input['fixture'];
    $stage = 'verify-boot';
    $runtime = closureBrowserBoot($manifest);
    $stage = 'verify-ledger';
    $role = Role::query()->findOrFail($fixture['role']['id']);
    $permission = Permission::query()->findOrFail($fixture['permission']['id']);
    $result = [
        'role_updated' => $role->name === 'ARC Role Updated' && $role->slug === $fixture['role']['slug'],
        'permission_updated' => $permission->name === 'ARC Permission Updated' && $permission->slug === $fixture['permission']['slug'],
        'user_removed' => ! User::query()->whereKey($fixture['target_user']['id'])->exists(),
        'client_removed' => ! Client::query()->whereKey($fixture['client']['id'])->exists(),
        'actor_authority_unchanged' => hash_equals($fixture['actor_authority_hash'], closureActorAuthorityHash($fixture['actor']['id'])),
    ];
    if (in_array(false, $result, true)) {
        throw new RuntimeException('The finite closure mutation ledger did not match its expected final state.');
    }
    echo json_encode($result, JSON_THROW_ON_ERROR)."\n";
} catch (Throwable $exception) {
    $exitCode = 1;
    fwrite(STDERR, json_encode(['stage' => $stage, 'class' => $exception::class,
        'message' => $exception->getMessage(), 'file' => $exception->getFile(), 'line' => $exception->getLine()],
        JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE)."\n");
} finally {
    if ($runtime !== null) {
        $runtime['app']->make('db')->disconnect();
        $runtime['app']->flush();
        $runtime['environment']->cleanUp();
        if (is_file($runtime['registry'])) {
            unlink($runtime['registry']);
        }
    }
}

exit($exitCode);
