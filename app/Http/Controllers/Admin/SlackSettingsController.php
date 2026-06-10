<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Slack\Contracts\SlackServiceInterface;
use App\Modules\Slack\Enums\SlackAlertLevel;
use App\Supports\AppSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SlackSettingsController extends Controller
{
    public function __construct(private readonly SlackServiceInterface $slack) {}

    public function show(): Response
    {
        // Merge config defaults (from .env) with DB overrides (AppSettings).
        // Same priority as SlackService: DB first, then .env fallback.
        // Null DB values are skipped so the .env fallback is preserved.
        $dbSettings = array_filter(AppSettings::all('slack'), fn ($v) => $v !== null);

        $settings = array_merge([
            'webhook_url' => config('slack.webhook_url'),
            'enabled' => config('slack.enabled', true),
            'log_level' => config('slack.log_level', 'warning'),
        ], $dbSettings);

        return Inertia::render('admin/slack/index', [
            'settings' => $settings,
            'isEnabled' => $this->slack->isEnabled(),
            'levelOptions' => SlackAlertLevel::options(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'webhook_url' => ['nullable', 'url', 'max:512'],
            'enabled' => ['required', 'boolean'],
            'log_level' => ['required', 'string', 'in:info,warning,error,critical'],
        ]);

        foreach ($validated as $key => $value) {
            AppSettings::set('slack', $key, $value);
        }

        return response()->json(['message' => 'Configurações salvas com sucesso.']);
    }

    public function test(): JsonResponse
    {
        if (! $this->slack->isEnabled()) {
            return response()->json([
                'success' => false,
                'message' => 'Slack não está configurado ou está desativado.',
            ], 422);
        }

        $sent = $this->slack->alert(
            message: 'Esta é uma mensagem de teste enviada pelo painel admin.',
            level: SlackAlertLevel::Info,
            context: [
                'app' => config('slack.app_name'),
                'url' => config('slack.app_url'),
                'env' => config('slack.environment'),
                'data' => now()->toDateTimeString(),
            ],
        );

        if (! $sent) {
            return response()->json([
                'success' => false,
                'message' => 'Falha ao enviar mensagem de teste. Verifique a webhook URL.',
            ], 422);
        }

        return response()->json(['success' => true, 'message' => 'Mensagem de teste enviada com sucesso!']);
    }
}
