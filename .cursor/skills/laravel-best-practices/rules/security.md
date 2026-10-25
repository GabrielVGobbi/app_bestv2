# Security Best Practices

## Mass Assignment Protection

Follow the canonical [AGENTS.md](../../../../AGENTS.md) and existing model conventions. Protect mass assignment with an explicit allowlist or guarded strategy: Laravel 13 supports `#[Fillable]` / `#[Guarded]` attributes as well as `$fillable` / `$guarded` properties. Do not rewrite a valid existing mechanism merely to use another syntax; only pass validated, authorized attributes.

Incorrect:
```php
class User extends Model
{
    protected $guarded = []; // All fields are mass assignable
}
```

Correct:
```php
class User extends Model
{
    protected $fillable = [
        'name',
        'email',
        'password',
    ];
}
```

Never use `$guarded = []` on models that accept user input.

## Authorize Every Action

Use policies or gates in controllers. Never skip authorization.

Incorrect:
```php
public function update(UpdatePostRequest $request, Post $post)
{
    $post->update($request->validated());
}
```

Correct:
```php
public function update(UpdatePostRequest $request, Post $post)
{
    Gate::authorize('update', $post);

    $post->update($request->validated());
}
```

Or via Form Request:

```php
public function authorize(): bool
{
    return $this->user()->can('update', $this->route('post'));
}
```

## Prevent SQL Injection

Always use parameter binding. Never interpolate user input into queries.

Incorrect:
```php
DB::select("SELECT * FROM users WHERE name = '{$request->name}'");
```

Correct:
```php
User::where('name', $request->name)->get();

// Raw expressions with bindings
User::whereRaw('LOWER(name) = ?', [strtolower($request->name)])->get();
```

## Escape Output to Prevent XSS

Use `{{ }}` for HTML escaping. Only use `{!! !!}` for trusted, pre-sanitized content.

Incorrect:
```blade
{!! $user->bio !!}
```

Correct:
```blade
{{ $user->bio }}
```

## CSRF Protection

Include `@csrf` in state-changing Blade forms (POST, PUT, PATCH, DELETE). React/Inertia forms do not receive a Blade directive automatically: Inertia's HTTP client reads Laravel's `XSRF-TOKEN` cookie and sends the `X-XSRF-TOKEN` header. Preserve the existing middleware and transport configuration; a separate HTTP client must send the required token itself. See the [Inertia CSRF documentation](https://inertiajs.com/docs/v3/security/csrf-protection).

Incorrect:
```blade
<form method="POST" action="/posts">
    <input type="text" name="title">
</form>
```

Correct:
```blade
<form method="POST" action="/posts">
    @csrf
    <input type="text" name="title">
</form>
```

## Rate Limit Auth and API Routes

Apply `throttle` middleware to authentication and API routes.

```php
RateLimiter::for('login', function (Request $request) {
    return Limit::perMinute(5)->by($request->ip());
});

Route::post('/login', LoginController::class)->middleware('throttle:login');
```

## Validate File Uploads

Validate content type and size. Both `mimes` and `mimetypes` inspect file contents: `mimes` guesses the MIME type associated with its listed extensions, while `mimetypes` compares MIME types directly. Neither verifies the client-assigned filename extension; add `extensions` when that filename constraint is required, alongside content validation. Never trust client-provided filenames. See the [file validation documentation](https://laravel.com/docs/13.x/validation#rule-mimes).

```php
public function rules(): array
{
    return [
        'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
    ];
}
```

Store with generated filenames:

```php
$path = $request->file('avatar')->store('avatars', 'public');
```

## Keep Secrets Out of Code

Never commit `.env`. Access secrets via `config()` only.

Incorrect:
```php
$key = env('API_KEY');
```

Correct:
```php
// config/services.php
'api_key' => env('API_KEY'),

// In application code
$key = config('services.api_key');
```

## Audit Dependencies

Run `composer audit` periodically to check for known vulnerabilities in dependencies. Automate this in CI to catch issues before deployment.

```bash
composer audit
```

## Encrypt Sensitive Database Fields

Use `encrypted` cast for API keys/tokens and mark the attribute as `hidden`.

Incorrect:
```php
class Integration extends Model
{
    protected function casts(): array
    {
        return [
            'api_key' => 'string',
        ];
    }
}
```

Correct:
```php
class Integration extends Model
{
    protected $hidden = ['api_key', 'api_secret'];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'api_secret' => 'encrypted',
        ];
    }
}
```