<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

/**
 * php artisan permissions:install
 * php artisan migrate
 * php artisan db:seed --class="App\Modules\Permissions\Database\Seeders\RolesSeeder"
 * php artisan db:seed --class="App\Modules\Permissions\Database\Seeders\PermissionsSeeder"
 * bootstrap\providers.php PermissionsServiceProvider::class
 * Model:user HasPermissionsTrait
 * $roleDev = Role::whereSlug('developer')->first();
 * foreach (User::all() as $user) {
 *     $user->roles()->syncWithoutDetaching([$roleDev]);
 *  }
 */
class InstallPermissionsModule extends Command
{
    protected $signature = 'permissions:install {--force : sobrescreve arquivos existentes}';

    protected $description = 'Instala módulo de Permissions em app/Modules/Permissions (Models, Trait, Middleware, Provider, Migrations, Seeders)';

    public function handle(Filesystem $files): int
    {
        $force = (bool) $this->option('force');

        $base = app_path('Modules/Permissions');
        $migrations = $base.'/Database/Migrations';
        $seeders = $base.'/Database/Seeders';

        $dirs = [
            $base.'/Models',
            $base.'/Traits',
            $base.'/Http/Middleware',
            $base.'/Providers',
            $migrations,
            $seeders,
        ];

        foreach ($dirs as $dir) {
            if (! $files->isDirectory($dir)) {
                $files->makeDirectory($dir, 0755, true);
            }
        }

        // Models
        $this->write($files, $base.'/Models/Permission.php', $this->permissionModel(), $force);
        $this->write($files, $base.'/Models/Role.php', $this->roleModel(), $force);

        // Traits
        $this->write($files, $base.'/Traits/HasPermissionsTrait.php', $this->traitHasPermissions(), $force);

        // Middleware
        $this->write($files, $base.'/Http/Middleware/RoleMiddleware.php', $this->roleMiddleware(), $force);

        // Provider
        $this->write($files, $base.'/Providers/PermissionsServiceProvider.php', $this->moduleProvider(), $force);

        // Config
        $this->write($files, config_path('permissions.php'), $this->configFile(), $force);

        // Seeders
        $this->write($files, $seeders.'/RolesSeeder.php', $this->rolesSeeder(), $force);
        $this->write($files, $seeders.'/PermissionsSeeder.php', $this->permissionsSeeder(), $force);

        // Migrations com timestamps em ordem
        $t = now();
        $ts1 = $t->format('Y_m_d_His');
        $ts2 = $t->copy()->addSecond()->format('Y_m_d_His');
        $ts3 = $t->copy()->addSeconds(2)->format('Y_m_d_His');
        $ts4 = $t->copy()->addSeconds(3)->format('Y_m_d_His');
        $ts5 = $t->copy()->addSeconds(4)->format('Y_m_d_His');

        $this->write($files, "{$migrations}/{$ts1}_create_permissions_table.php", $this->migPermissions(), $force);
        $this->write($files, "{$migrations}/{$ts2}_create_roles_table.php", $this->migRoles(), $force);
        $this->write($files, "{$migrations}/{$ts3}_create_users_permissions_table.php", $this->migUsersPermissions(), $force);
        $this->write($files, "{$migrations}/{$ts4}_create_users_roles_table.php", $this->migUsersRoles(), $force);
        $this->write($files, "{$migrations}/{$ts5}_create_roles_permissions_table.php", $this->migRolesPermissions(), $force);

        $this->newLine();

        // Registrar Service Provider automaticamente
        $this->registerServiceProvider($files);

        // Registrar Middleware automaticamente
        $this->registerMiddleware($files);

        // Registrar Seeders no DatabaseSeeder
        $this->registerSeedersInDatabaseSeeder($files);

        $this->newLine();
        $this->info('✅ Permissions module instalado em app/Modules/Permissions');
        $this->line('📋 Próximos passos:');
        $this->line('1) composer dump-autoload');
        $this->line('2) php artisan optimize:clear');
        $this->line('3) php artisan migrate --seed (ou migrate:fresh --seed)');
        $this->line('4) No User model: use App\\Modules\\Permissions\\Traits\\HasPermissionsTrait;');
        $this->newLine();

        return self::SUCCESS;
    }

    private function registerServiceProvider(Filesystem $files): void
    {
        $providersPath = base_path('bootstrap/providers.php');

        if (! $files->exists($providersPath)) {
            $this->warn('⚠️  Arquivo bootstrap/providers.php não encontrado - registre manualmente');

            return;
        }

        $content = $files->get($providersPath);
        $providerClass = 'App\\Modules\\Permissions\\Providers\\PermissionsServiceProvider::class';

        if (str_contains($content, $providerClass)) {
            $this->info('ℹ️  Service Provider já registrado');

            return;
        }

        // Adiciona antes do último ];
        $pattern = '/(\s*)(];)(\s*)$/';
        $replacement = "$1    {$providerClass},\n$1$2$3";
        $content = preg_replace($pattern, $replacement, $content);

        $files->put($providersPath, $content);
        $this->info('✅ Service Provider registrado em bootstrap/providers.php');
    }

    private function registerMiddleware(Filesystem $files): void
    {
        $appPath = base_path('bootstrap/app.php');

        if (! $files->exists($appPath)) {
            $this->warn('⚠️  Arquivo bootstrap/app.php não encontrado - registre manualmente');

            return;
        }

        $content = $files->get($appPath);

        if (str_contains($content, 'RoleMiddleware')) {
            $this->info('ℹ️  Middleware já registrado');

            return;
        }

        // Verifica se já existe bloco withMiddleware
        if (! str_contains($content, '->withMiddleware(')) {
            $this->warn('⚠️  Não foi possível registrar middleware automaticamente');
            $this->line('   Adicione manualmente em bootstrap/app.php:');
            $this->line("   'role' => \\App\\Modules\\Permissions\\Http\\Middleware\\RoleMiddleware::class,");

            return;
        }

        // Tenta adicionar no bloco alias existente
        $middlewareAlias = "'role' => \\App\\Modules\\Permissions\\Http\\Middleware\\RoleMiddleware::class";

        if (preg_match('/(->alias\(\[)([^\]]*)(]\))/s', $content, $matches)) {
            $aliases = $matches[2];
            $newAliases = rtrim($aliases)."\n        {$middlewareAlias},\n    ";
            $content = str_replace($matches[0], $matches[1].$newAliases.$matches[3], $content);

            $files->put($appPath, $content);
            $this->info('✅ Middleware registrado em bootstrap/app.php');
        } else {
            $this->warn('⚠️  Registre o middleware manualmente em bootstrap/app.php');
        }
    }

    private function registerSeedersInDatabaseSeeder(Filesystem $files): void
    {
        $seederPath = database_path('seeders/DatabaseSeeder.php');

        if (! $files->exists($seederPath)) {
            $this->warn('⚠️  Arquivo DatabaseSeeder.php não encontrado - registre manualmente');

            return;
        }

        $content = $files->get($seederPath);

        // Verifica se já está registrado
        if (str_contains($content, 'RolesSeeder::class')) {
            $this->info('ℹ️  Seeders já registrados no DatabaseSeeder');

            return;
        }

        // Adiciona os imports
        $rolesImport = 'use App\\Modules\\Permissions\\Database\\Seeders\\RolesSeeder;';
        $permsImport = 'use App\\Modules\\Permissions\\Database\\Seeders\\PermissionsSeeder;';

        if (! str_contains($content, $rolesImport)) {
            // Adiciona imports após o namespace
            $pattern = '/(namespace Database\\Seeders;)/';
            $replacement = "$1\n\n{$rolesImport}\n{$permsImport}";
            $content = preg_replace($pattern, $replacement, $content);
        }

        // Adiciona no array call()
        // Procura o padrão $this->call([ ... ]);
        if (preg_match('/(\$this->call\(\[)([^\]]*)(]\);)/s', $content, $matches)) {
            $currentCalls = $matches[2];

            // Adiciona os seeders no início (antes de outros seeders)
            $newCalls = "\n            RolesSeeder::class,\n            PermissionsSeeder::class,";

            // Se já tem conteúdo, adiciona com quebra de linha
            if (trim($currentCalls)) {
                $newCalls .= $currentCalls;
            } else {
                $newCalls .= "\n        ";
            }

            $content = str_replace($matches[0], $matches[1].$newCalls.$matches[3], $content);

            $files->put($seederPath, $content);
            $this->info('✅ Seeders registrados no DatabaseSeeder');
        } else {
            $this->warn('⚠️  Não foi possível registrar seeders automaticamente');
            $this->line('   Adicione manualmente em database/seeders/DatabaseSeeder.php:');
            $this->line('   RolesSeeder::class,');
            $this->line('   PermissionsSeeder::class,');
        }
    }

    private function write(Filesystem $files, string $path, string $content, bool $force): void
    {
        if ($files->exists($path) && ! $force) {
            $this->warn("⏭️  Já existe: {$path} (use --force para sobrescrever)");

            return;
        }

        $files->put($path, $content);
        $this->line("📝 Criado: {$path}");
    }

    private function configFile(): string
    {
        return <<<'PHP'
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Super Role
    |--------------------------------------------------------------------------
    |
    | A role que tem acesso total automático a todas as permissões.
    | Users com esta role não precisam ter permissões específicas.
    |
    */
    'super_role' => env('PERMISSIONS_SUPER_ROLE', 'developer'),

    /*
    |--------------------------------------------------------------------------
    | Models
    |--------------------------------------------------------------------------
    |
    | Classes dos models de Permission e Role.
    |
    */
    'models' => [
        'permission' => \App\Modules\Permissions\Models\Permission::class,
        'role' => \App\Modules\Permissions\Models\Role::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Tables
    |--------------------------------------------------------------------------
    |
    | Nomes das tabelas do banco de dados.
    |
    */
    'tables' => [
        'permissions' => 'permissions',
        'roles' => 'roles',
        'users_roles' => 'users_roles',
        'users_permissions' => 'users_permissions',
        'roles_permissions' => 'roles_permissions',
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | Ativar cache de permissões para melhorar performance.
    | Tempo em minutos.
    |
    */
    'cache' => [
        'enabled' => env('PERMISSIONS_CACHE_ENABLED', false),
        'ttl' => env('PERMISSIONS_CACHE_TTL', 60),
        'key' => 'permissions.cache',
    ],
];
PHP;
    }

    private function permissionModel(): string
    {
        return <<<'PHP'
<?php

namespace App\Modules\Permissions\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    protected $fillable = ['name', 'slug', 'group', 'description'];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'roles_permissions');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\User::class, 'users_permissions');
    }
}
PHP;
    }

    private function roleModel(): string
    {
        return <<<'PHP'
<?php

namespace App\Modules\Permissions\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    protected $fillable = ['name', 'slug', 'description'];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'roles_permissions');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\User::class, 'users_roles');
    }
}
PHP;
    }

    private function traitHasPermissions(): string
    {
        return <<<'PHP'
<?php

namespace App\Modules\Permissions\Traits;

use App\Modules\Permissions\Models\Permission;
use App\Modules\Permissions\Models\Role;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

trait HasPermissionsTrait
{
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'users_roles');
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'users_permissions');
    }

    public function hasRole(string ...$roles): bool
    {
        $super = config('permissions.super_role', 'developer');

        if ($this->roles->contains('slug', $super)) {
            return true;
        }

        foreach ($roles as $role) {
            if ($this->roles->contains('slug', $role)) {
                return true;
            }
        }
        return false;
    }

    public function hasPermissionTo(Permission $permission): bool
    {
        $super = config('permissions.super_role', 'developer');

        if ($this->hasRole($super)) {
            return true;
        }

        if ($this->permissions->contains('slug', $permission->slug)) {
            return true;
        }

        foreach ($permission->roles as $role) {
            if ($this->roles->contains($role)) {
                return true;
            }
        }

        return false;
    }
}
PHP;
    }

    private function roleMiddleware(): string
    {
        return <<<'PHP'
<?php

namespace App\Modules\Permissions\Http\Middleware;

use App\Modules\Permissions\Models\Permission;
use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string $role, ?string $permission = null)
    {
        // Verifica autenticação
        if (!$request->user()) {
            return redirect()->route('login');
        }

        // Verifica role
        if (!$request->user()->hasRole($role)) {
            abort(403, 'Acesso negado');
        }

        // Verifica permission (se fornecida)
        if ($permission !== null) {
            $perm = Permission::where('slug', $permission)->first();

            if (!$perm || !$request->user()->hasPermissionTo($perm)) {
                abort(403, 'Acesso negado');
            }
        }

        return $next($request);
    }
}
PHP;
    }

    private function moduleProvider(): string
    {
        return <<<'PHP'
<?php

namespace App\Modules\Permissions\Providers;

use App\Modules\Permissions\Models\Permission;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class PermissionsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
        $this->registerGates();
    }

    protected function registerGates(): void
    {
        Gate::before(function ($user, $ability) {
            $super = config('permissions.super_role', 'developer');

            // Super role tem acesso a tudo
            if ($user->hasRole($super)) {
                return true;
            }

            // Verifica se existe uma permission com este slug
            $permission = Permission::where('slug', $ability)->first();

            if ($permission && $user->hasPermissionTo($permission)) {
                return true;
            }

            return null;
        });
    }
}
PHP;
    }

    private function rolesSeeder(): string
    {
        return <<<'PHP'
<?php

namespace App\Modules\Permissions\Database\Seeders;

use App\Modules\Permissions\Models\Role;
use Illuminate\Database\Seeder;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'Developer', 'slug' => 'developer', 'description' => 'Acesso total (super role)'],
            ['name' => 'Super Admin', 'slug' => 'super-admin', 'description' => 'Administração completa'],
            ['name' => 'Admin', 'slug' => 'admin', 'description' => 'Administração padrão'],
        ];

        foreach ($roles as $role) {
            Role::query()->firstOrCreate(
                ['slug' => $role['slug']],
                ['name' => $role['name'], 'description' => $role['description']]
            );
        }
    }
}
PHP;
    }

    private function permissionsSeeder(): string
    {
        return <<<'PHP'
<?php

namespace App\Modules\Permissions\Database\Seeders;

use App\Modules\Permissions\Models\Permission;
use App\Modules\Permissions\Models\Role;
use Illuminate\Database\Seeder;

class PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permission = Permission::query()->firstOrCreate(
            ['slug' => 'access-painel'],
            [
                'name' => 'Acessar Painel',
                'group' => 'painel',
                'description' => 'Permite acessar o painel administrativo',
            ]
        );

        $roles = Role::query()->whereIn('slug', ['developer', 'super-admin', 'admin'])->get();

        foreach ($roles as $role) {
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
    }
}
PHP;
    }

    private function migPermissions(): string
    {
        return <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('group')->nullable()->index();
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
PHP;
    }

    private function migRoles(): string
    {
        return <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->index();
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
PHP;
    }

    private function migUsersPermissions(): string
    {
        return <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('users_permissions', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->primary(['user_id', 'permission_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users_permissions');
    }
};
PHP;
    }

    private function migUsersRoles(): string
    {
        return <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('users_roles', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->primary(['user_id', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users_roles');
    }
};
PHP;
    }

    private function migRolesPermissions(): string
    {
        return <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('roles_permissions', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles_permissions');
    }
};
PHP;
    }
}
