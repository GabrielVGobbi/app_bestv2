# Mapa da arquitetura

Este é um mapa de descoberta do código atual. As regras estão em [AGENTS.md](../../AGENTS.md), a preparação está no [README](../../README.md) e os contratos de cada domínio permanecem nas fontes indicadas abaixo.

## Backend e limites dos módulos

[bootstrap/providers.php](../../bootstrap/providers.php) registra os providers da aplicação e dos módulos. Os módulos ficam em [app/Modules](../../app/Modules): Backup, Clients, CrudGenerator, Email, ExternalApi, Notifications, Permissions, SeederGenerator, Timeline e Uploads têm providers registrados nesse arquivo. Suppliers e Slack também têm fontes próprias; a presença de uma pasta não comprova que todas as integrações estejam ativadas.

Rotas gerais ficam em [routes](../../routes). Módulos possuem suas próprias rotas e configuração conforme seus providers. Clientes, por exemplo, têm uma declaração canônica em [app/Modules/Clients/routes.php](../../app/Modules/Clients/routes.php); não repetir o resource nos arquivos gerais. Controllers coordenam respostas, enquanto regras de domínio são mantidas nos serviços, ações, requests, policies e jobs existentes.

Autenticação usa Fortify/Sanctum. A autorização segue [PREP-002](../../docs/stabilization/tasks/PREP-002.md#contrato-adotado): capacidade, ação, alvo, ambiente e abilities aceitas são avaliados pelo servidor. Menu visível e nome de papel não substituem policies nem invariantes de hierarquia.

## Frontend e compilação

[resources/js/app.tsx](../../resources/js/app.tsx) é a entrada React/Inertia. Páginas, módulos, layouts, hooks e componentes compartilhados vivem em [resources/js](../../resources/js). O DataTable compartilhado está em [components/data-table](../../resources/js/components/data-table); reutilize seus contratos de estado, consulta e seleção.

[vite.config.ts](../../vite.config.ts) conecta Laravel, Inertia, React Compiler, Tailwind e Wayfinder. Helpers de rotas em `resources/js/actions`, `routes` e `wayfinder` são gerados. [package.json](../../package.json), [package-lock.json](../../package-lock.json), [composer.json](../../composer.json) e [composer.lock](../../composer.lock) definem ferramentas e versões; não há outro gerenciador de pacotes adotado.

O frontend reutiliza os helpers e padrões existentes. UUIDs precisam funcionar em HTTP no domínio local, conforme AGENTS; não introduzir `crypto.randomUUID()` direto. SSR e realtime são opcionais, com evidências e limites nos [quatro perfis OPS-004](../../docs/stabilization/tasks/OPS-004.md).

Navegação e mutações de rotas web que retornam redirects usam o router Inertia. Consultas TanStack e formulários que esperam JSON usam os helpers HTTP e as rotas API geradas; uma resposta HTML após redirect não representa um envelope JSON. O contrato de paginação está em [types/pagination.ts](../../resources/js/types/pagination.ts); os nomes públicos de [lib/http.ts](../../resources/js/lib/http.ts) são aliases desse contrato. Componentes com consumidores ativos permanecem disponíveis.

Nas listas GET de uploads e timeline, decisões de policy do mesmo pai são reutilizadas somente nos atributos da Request atual, separadas por ator, token, modelo, chave e ability. Escritas e outras requisições reavaliam a autorização. As relações consumidas pelas policies de anexos e comentários são carregadas antes da serialização; a regressão compara listas de 1 e 12 itens com detecção estrita de lazy loading.

## Contratos e configuração

| Domínio | Fonte canônica |
| --- | --- |
| Exports, filas e downloads | [Exports no vault](../../docs/.start_kit.obs/Exports/async-export-system.md) |
| Backups, dumps e recuperação | [Módulo Backups](../../docs/backups/backup-dumps-module.md) |
| Notificações e ações | [Módulo Notifications](../../docs/.start_kit.obs/Notifications/notifications-module.md) |
| E-mail | [Módulo Email](../../docs/.start_kit.obs/Email/email-module.md) |
| Slack | [Módulo Slack](../../docs/.start_kit.obs/Slack/slack-module.md) |
| Navegação | [Navigation](../../docs/.start_kit.obs/Navigation/navigation-config.md) |
| Uploads | [Configuração do módulo](../../app/Modules/Uploads/Config/uploads.php) |
| Workers e agendamento | [Workers](../../docs/stabilization/ops-worker-runtime.md), [scheduler](../../docs/stabilization/ops-scheduler-runtime.md) |

Configuração geral está em [config](../../config); configuração modular também pode ficar no próprio módulo. `.env` e `.config` locais são privados e não são fontes para fixtures ou documentação de credenciais. Dados existentes não são destinos de testes.

## Verificação e evidência

[tests/ci.php](../../tests/ci.php) coordena os perfis locais; [ci-runtime.md](../../docs/stabilization/ci-runtime.md) define a instalação limpa e o isolamento de banco, HTTP, Mail, filas, SDKs, caches, storage e processos. O [painel](../../docs/stabilization/board.md) aponta as tarefas e os pacotes de validação. Um build não comprova execução SSR, e um teste transacional não isola um servidor HTTP externo.
