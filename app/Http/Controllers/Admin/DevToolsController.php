<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateEnvRequest;
use App\Http\Resources\ActivityLogResource;
use App\Models\User;
use App\Modules\Clients\Models\Client;
use App\Modules\Email\Contracts\EmailServiceInterface;
use App\Modules\Email\DTOs\SendEmailDTO;
use App\Modules\Email\Enums\EmailCategory;
use App\Modules\Email\Models\EmailLog;
use App\Modules\Notifications\Actions\CreateNotificationAction;
use App\Modules\Notifications\DTOs\CreateNotificationDTO;
use App\Modules\Notifications\Enums\NotificationCategory;
use App\Modules\Notifications\Enums\NotificationType;
use App\Services\DevToolsService;
use App\Supports\ActivityActions\ActivityActionLogger;
use App\Supports\ActivityActions\ActivityActionRegistry;
use App\Supports\AppSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class DevToolsController extends Controller
{
    public function __construct(
        private readonly DevToolsService $devToolsService,
    ) {}

    public function designSystem(Request $request)
    {
        return Inertia::render('admin/dev/design-system');
    }

    public function components(): Response
    {
        return Inertia::render('admin/dev/components/index');
    }

    public function fileAttachments(): Response
    {
        return Inertia::render('admin/dev/components/file-attachments');
    }

    public function inlineCellEdit(): Response
    {
        return Inertia::render('admin/dev/components/inline-cell-edit');
    }

    public function asyncSelect(): Response
    {
        return Inertia::render('admin/dev/components/async-select');
    }

    public function formPlayground(): Response
    {
        return Inertia::render('admin/dev/components/form-playground');
    }

    public function toasts(): Response
    {
        return Inertia::render('admin/dev/components/toasts');
    }

    public function notifications(): Response
    {
        return Inertia::render('admin/dev/components/notifications');
    }

    public function activityActions(ActivityActionRegistry $activityActions): Response
    {
        $recentLogs = Activity::query()
            ->with(['causer', 'subject'])
            ->where('properties->test', true)
            ->orderByDesc('created_at')
            ->limit(8)
            ->get();

        return Inertia::render('admin/dev/components/activity-actions', [
            'activityActionCatalog' => $activityActions->catalog(includeDisabled: true),
            'recentLogs' => ActivityLogResource::collection($recentLogs)->resolve(request()),
        ]);
    }

    public function activityTimeline(): Response
    {
        $client = Client::query()->oldest('id')->first(['id', 'uuid', 'name']);

        return Inertia::render('admin/dev/components/activity-timeline', [
            'defaultSubject' => $client ? [
                'type' => 'client',
                'id' => $client->uuid ?: $client->id,
                'label' => $client->name,
            ] : null,
        ]);
    }

    public function richTextEditor(): Response
    {
        return Inertia::render('admin/dev/components/rich-text-editor');
    }

    public function email(): Response
    {
        $recentLogs = EmailLog::query()
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['uuid', 'to_email', 'subject', 'status', 'created_at', 'category']);

        $settings = AppSettings::all('email');

        return Inertia::render('admin/dev/components/email', [
            'recentLogs' => $recentLogs,
            'settings' => $settings,
        ]);
    }

    public function previewEmail(Request $request): \Illuminate\Http\Response
    {
        $validated = $request->validate([
            'template' => ['required', 'string', 'max:255'],
            'variables' => ['nullable', 'string'],
        ]);

        $variables = [];
        if (! empty($validated['variables'])) {
            $decoded = json_decode($validated['variables'], true);
            if (is_array($decoded)) {
                $variables = $decoded;
            }
        }

        try {
            $html = view($validated['template'], $variables)->render();
        } catch (\Throwable $e) {
            $html = '<html><body style="font-family:monospace;padding:24px;color:#dc2626"><strong>Erro ao renderizar template:</strong><br><br>'.e($e->getMessage()).'</body></html>';
        }

        return response($html, 200)->header('Content-Type', 'text/html');
    }

    public function sendTestEmail(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'to' => ['required', 'email'],
            'subject' => ['required', 'string', 'max:255'],
            'template' => ['required', 'string'],
            'category' => ['required', 'string', 'in:auth,export,notification,system'],
            'variables' => ['nullable', 'string'],
        ]);

        $variables = [];
        if (! empty($validated['variables'])) {
            $decoded = json_decode($validated['variables'], true);
            if (is_array($decoded)) {
                $variables = $decoded;
            }
        }

        $dto = new SendEmailDTO(
            to: $validated['to'],
            subject: $validated['subject'],
            template: $validated['template'],
            category: EmailCategory::from($validated['category']),
            variables: $variables,
        );

        $log = app(EmailServiceInterface::class)->queue($dto);

        if ($log === null) {
            return response()->json([
                'success' => false,
                'message' => 'Email bloqueado pelo kill switch ou duplicata detectada.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Email enfileirado com sucesso.',
            'uuid' => $log->uuid,
            'status' => $log->status->value,
        ]);
    }

    public function seedNotification(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $action = app(CreateNotificationAction::class);

        $dto = match ($validated['type']) {
            'system' => CreateNotificationDTO::system(
                recipientId: $user->id,
                title: 'Exportação concluída',
                body: 'Seu relatório foi exportado com sucesso e está pronto para download.',
                category: NotificationCategory::EXPORT,
                data: ['file_name' => 'relatorio_maio.xlsx', 'format' => 'xlsx'],
            ),
            'user' => CreateNotificationDTO::fromUser(
                recipientId: $user->id,
                senderId: $user->id,
                title: 'Novo comentário',
                body: "{$user->name} comentou em sua requisição.",
                category: NotificationCategory::COMMENT,
            ),
            'mention' => CreateNotificationDTO::mention(
                recipientId: $user->id,
                senderId: $user->id,
                title: 'Você foi mencionado',
                body: "{$user->name} mencionou você em um comentário.",
            ),
            'social' => new CreateNotificationDTO(
                recipientId: $user->id,
                type: NotificationType::SOCIAL,
                category: NotificationCategory::FOLLOW,
                title: 'Novo seguidor',
                body: "{$user->name} começou a seguir você.",
                senderId: $user->id,
            ),
            'action' => CreateNotificationDTO::withActions(
                recipientId: $user->id,
                title: 'Solicitação pendente',
                body: 'Você tem uma solicitação aguardando aprovação.',
                actions: [
                    [
                        'label' => 'Aprovar',
                        'type' => 'approve_request',
                        'variant' => 'primary',
                        'requires_confirmation' => true,
                        'confirmation_message' => 'Tem certeza que deseja aprovar esta solicitação?',
                    ],
                    [
                        'label' => 'Recusar',
                        'type' => 'reject_request',
                        'variant' => 'destructive',
                        'requires_confirmation' => true,
                        'confirmation_message' => 'Tem certeza que deseja recusar esta solicitação?',
                    ],
                ],
                category: NotificationCategory::TASK,
                notifiableType: User::class,
                notifiableId: $user->id,
            ),
            'warning' => new CreateNotificationDTO(
                recipientId: $user->id,
                type: NotificationType::WARNING,
                category: NotificationCategory::SECURITY,
                title: 'Alerta de segurança',
                body: 'Uma nova sessão foi iniciada no seu account a partir de um dispositivo desconhecido.',
            ),
            default => CreateNotificationDTO::system(
                recipientId: $user->id,
                title: 'Notificação de teste',
                body: 'Esta é uma notificação de teste gerada pelo playground.',
            ),
        };

        $notification = $action->handle($dto);

        return response()->json(['created' => true, 'uuid' => $notification->uuid]);
    }

    public function seedActivityAction(
        Request $request,
        ActivityActionRegistry $activityActions,
        ActivityActionLogger $activityLogger,
    ): JsonResponse {
        $validated = $request->validate([
            'action_key' => ['required', 'string'],
        ]);

        $definition = $activityActions->require($validated['action_key']);

        /** @var User $user */
        $user = $request->user();
        $subject = str_starts_with($definition->key, 'clients.')
            ? Client::query()->oldest('id')->first()
            : $user;

        $activity = $activityLogger->log(
            actionKey: $definition->key,
            subject: $subject,
            causer: $user,
            properties: [
                'test' => true,
                'source' => 'dev_playground',
                'note' => $subject
                    ? 'Registro criado pelo playground de activity actions.'
                    : 'Nenhum cliente encontrado para vincular como alvo.',
            ],
            request: $request,
        );

        return response()->json([
            'created' => $activity !== null,
            'id' => $activity?->id,
        ]);
    }

    public function asyncSelectUsers(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $users = User::query()
            ->when($validated['search'] ?? null, function ($query, string $search): void {
                $query
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            })
            ->orderBy('name')
            ->paginate(
                perPage: $validated['limit'] ?? 10,
                page: $validated['page'] ?? 1,
            );

        return response()->json([
            'data' => $users->getCollection()->map(fn (User $user): array => [
                'value' => $user->uuid,
                'label' => $user->name,
                'description' => $user->email,
            ])->values(),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
            ],
        ]);
    }

    public function updateInlineCellEditUser(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'field' => ['required', 'string', 'in:name,email,email_verified_at'],
            'value' => ['nullable', 'string'],
        ]);

        if ($validated['field'] === 'name') {
            $request->validate([
                'value' => ['required', 'string', 'min:3', 'max:255'],
            ]);

            $user->forceFill(['name' => $validated['value']]);
        }

        if ($validated['field'] === 'email') {
            $request->validate([
                'value' => [
                    'required',
                    'string',
                    'email',
                    'max:255',
                    Rule::unique(User::class, 'email')->ignore($user->id),
                ],
            ]);

            $user->forceFill([
                'email' => $validated['value'],
                'email_verified_at' => null,
            ]);
        }

        if ($validated['field'] === 'email_verified_at') {
            $request->validate([
                'value' => ['required', 'string', 'in:verified,pending'],
            ]);

            $user->forceFill([
                'email_verified_at' => $validated['value'] === 'verified' ? now() : null,
            ]);
        }

        $user->save();

        return back()->with('success', 'Celula atualizada com sucesso.');
    }

    /**
     * Exibe o painel de ferramentas de desenvolvimento.
     */
    public function index(): Response
    {
        return Inertia::render('admin/dev/tools', [
            'systemInfo' => $this->devToolsService->getSystemInfo(),
            'envVariables' => $this->devToolsService->getEnvVariables(),
            'maintenanceMode' => app()->isDownForMaintenance(),
            'routes' => Inertia::defer(fn () => $this->devToolsService->getRouteList()),
        ]);
    }

    /**
     * Alterna o modo de manutenção do sistema.
     */
    public function toggleMaintenance(): RedirectResponse
    {
        $this->devToolsService->toggleMaintenance();

        $isDown = app()->isDownForMaintenance();

        return back()->with(
            'success',
            $isDown ? 'Modo de manutenção ativado.' : 'Sistema restaurado com sucesso.',
        );
    }

    /**
     * Limpa todos os caches da aplicação.
     */
    public function clearAllCaches(): RedirectResponse
    {
        $this->devToolsService->clearAllCaches();

        return back()->with('success', 'Todos os caches foram limpos.');
    }

    /**
     * Limpa um tipo específico de cache.
     */
    public function clearSpecificCache(Request $request): RedirectResponse
    {
        $request->validate([
            'type' => ['required', 'string', 'in:cache,config,route,view'],
        ]);

        $this->devToolsService->clearSpecificCache($request->string('type')->toString());

        $labels = [
            'cache' => 'Cache da aplicação',
            'config' => 'Cache de configuração',
            'route' => 'Cache de rotas',
            'view' => 'Cache de views',
        ];

        $label = $labels[$request->input('type')] ?? 'Cache';

        return back()->with('success', "{$label} limpo com sucesso.");
    }

    /**
     * Otimiza a aplicação (cache de config, rotas e views).
     */
    public function optimize(): RedirectResponse
    {
        $this->devToolsService->optimize();

        return back()->with('success', 'Aplicação otimizada com sucesso.');
    }

    /**
     * Alterna o estado habilitado/desabilitado do Telescope.
     */
    public function toggleTelescope(): RedirectResponse
    {
        $this->devToolsService->toggleTelescope();

        return back()->with('success', 'Estado do Telescope atualizado.');
    }

    /**
     * Alterna o modo de captura total do Telescope.
     */
    public function toggleTelescopeCaptureAll(): RedirectResponse
    {
        $this->devToolsService->toggleTelescopeCaptureAll();

        return back()->with('success', 'Modo de captura do Telescope atualizado.');
    }

    /**
     * Atualiza uma variável no arquivo .env.
     */
    public function updateEnv(UpdateEnvRequest $request): RedirectResponse
    {
        $this->devToolsService->updateEnvVariable(
            $request->string('key')->toString(),
            $request->string('value')->toString(),
        );

        return back()->with('success', "Variável {$request->input('key')} atualizada com sucesso.");
    }
}
