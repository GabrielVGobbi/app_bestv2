<?php

declare(strict_types=1);

namespace App\Modules\Email\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Email\Actions\ResendEmailAction;
use App\Modules\Email\Enums\EmailCategory;
use App\Modules\Email\Enums\EmailStatus;
use App\Modules\Email\Models\EmailLog;
use App\Supports\AppSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class EmailLogController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('view-email-logs', EmailLog::class);

        $logs = EmailLog::query()
            ->select([
                'uuid', 'to_email', 'to_name', 'subject', 'template', 'category', 'status',
                'attempts', 'mailer', 'message_id', 'sent_at', 'failed_at', 'created_at', 'error_message',
            ])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->category))
            ->when($request->filled('search'), fn ($q) => $q->where(function ($inner) use ($request) {
                $inner->where('to_email', 'like', "%{$request->search}%")
                    ->orWhere('subject', 'like', "%{$request->search}%");
            }))
            ->latest()
            ->paginate(25)
            ->withQueryString()
            ->through(fn (EmailLog $log): array => [
                ...$log->only([
                    'uuid', 'to_email', 'to_name', 'subject', 'template', 'category', 'status',
                    'attempts', 'mailer', 'message_id', 'sent_at', 'failed_at', 'created_at',
                ]),
                'error_message' => $log->error_message === null ? null : 'Falha no processamento do e-mail.',
            ]);

        return Inertia::render('app/email-logs/index', [
            'logs' => $logs,
            'filters' => $request->only(['status', 'category', 'search']),
            'statuses' => EmailStatus::options(),
            'categories' => EmailCategory::options(),
            'settings' => $this->emailSettings(),
        ]);
    }

    public function resend(EmailLog $emailLog, ResendEmailAction $action): RedirectResponse
    {
        Gate::authorize('resend-email-logs', EmailLog::class);

        $action->handle($emailLog);

        return back()->with('success', 'E-mail adicionado de volta à fila.');
    }

    public function updateSettings(Request $request): JsonResponse|RedirectResponse
    {
        Gate::authorize('manage-email-settings', EmailLog::class);

        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'auth_enabled' => ['required', 'boolean'],
            'export_enabled' => ['required', 'boolean'],
            'notification_enabled' => ['required', 'boolean'],
            'system_enabled' => ['required', 'boolean'],
        ]);

        foreach ($validated as $key => $value) {
            AppSettings::set('email', $key, (bool) $value);
        }

        if ($request->header('X-Inertia')) {
            return back()->with('success', 'Configurações salvas.');
        }

        return response()->json(['message' => 'Configurações salvas.']);
    }

    /** @return array<string, bool> */
    private function emailSettings(): array
    {
        $stored = AppSettings::all('email');
        $settings = [];

        foreach (['enabled', 'auth_enabled', 'export_enabled', 'notification_enabled', 'system_enabled'] as $key) {
            $configured = config("email.defaults.{$key}", true);
            $default = is_bool($configured) ? $configured : true;
            $value = $stored[$key] ?? $default;
            $settings[$key] = in_array($value, [true, false, 0, 1, '0', '1'], true) ? (bool) $value : $default;
        }

        return $settings;
    }
}
