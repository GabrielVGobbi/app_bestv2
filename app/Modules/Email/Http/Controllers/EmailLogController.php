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
use Inertia\Inertia;
use Inertia\Response;

class EmailLogController extends Controller
{
    public function index(Request $request): Response
    {
        $logs = EmailLog::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->category))
            ->when($request->filled('search'), fn ($q) => $q->where(function ($inner) use ($request) {
                $inner->where('to_email', 'like', "%{$request->search}%")
                    ->orWhere('subject', 'like', "%{$request->search}%");
            }))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('app/email-logs/index', [
            'logs' => $logs,
            'filters' => $request->only(['status', 'category', 'search']),
            'statuses' => EmailStatus::options(),
            'categories' => EmailCategory::options(),
            'settings' => AppSettings::all('email'),
        ]);
    }

    public function resend(EmailLog $emailLog, ResendEmailAction $action): RedirectResponse
    {
        $action->handle($emailLog);

        return back()->with('success', 'E-mail adicionado de volta à fila.');
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'auth_enabled' => ['required', 'boolean'],
            'export_enabled' => ['required', 'boolean'],
            'notification_enabled' => ['required', 'boolean'],
            'system_enabled' => ['required', 'boolean'],
        ]);

        foreach ($validated as $key => $value) {
            AppSettings::set('email', $key, $value);
        }

        return response()->json(['message' => 'Configurações salvas.']);
    }
}
