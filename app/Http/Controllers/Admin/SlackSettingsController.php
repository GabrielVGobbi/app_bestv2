<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSlackSettingsRequest;
use App\Modules\Slack\Contracts\SlackServiceInterface;
use App\Modules\Slack\Enums\SlackAlertLevel;
use App\Supports\AppSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SlackSettingsController extends Controller
{
    public function __construct(private readonly SlackServiceInterface $slack) {}

    public function show(): Response
    {
        $enabled = AppSettings::get('slack', 'enabled') ?? config('slack.enabled', true);
        $level = AppSettings::get('slack', 'log_level') ?? config('slack.log_level', 'warning');
        $webhook = AppSettings::get('slack', 'webhook_url') ?: config('slack.webhook_url');
        $alertLevel = is_string($level) ? SlackAlertLevel::tryFrom($level) : null;

        return Inertia::render('admin/slack/index', [
            'settings' => [
                'enabled' => (bool) $enabled,
                'log_level' => $alertLevel === null ? SlackAlertLevel::Warning->value : $alertLevel->value,
            ],
            'webhook_configured' => is_string($webhook) && trim($webhook) !== '',
            'isEnabled' => $this->slack->isEnabled(),
            'levelOptions' => SlackAlertLevel::options(),
        ]);
    }

    public function update(UpdateSlackSettingsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        DB::transaction(function () use ($validated, $request): void {
            AppSettings::set('slack', 'enabled', (bool) $validated['enabled']);
            AppSettings::set('slack', 'log_level', $validated['log_level']);
            if ($request->boolean('reset_webhook')) {
                AppSettings::forget('slack', 'webhook_url');
            } elseif (isset($validated['webhook_url']) && is_string($validated['webhook_url']) && trim($validated['webhook_url']) !== '') {
                AppSettings::set('slack', 'webhook_url', $validated['webhook_url']);
            }
        });

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
