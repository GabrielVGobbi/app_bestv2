# StartKit

Aplicação modular Laravel 13, PHP 8.3+, React 19 e Inertia 3, com Vite 8 e Tailwind 4. O projeto usa Composer e npm; os arquivos de lock definem a instalação reproduzível.

## Antes de começar

- Leia [AGENTS.md](AGENTS.md), a fonte das regras compartilhadas, e o [contrato de autoridade](docs/stabilization/tasks/PREP-002.md#contrato-adotado). Autenticação, permissões, alvo, ambiente e abilities de token são barreiras distintas; o nome de um papel não concede um bypass global.
- Use PHP compatível com `composer.lock` e suas extensões, Node `^22.13.0` e npm `^10.9.3` (`packageManager: npm@10.9.3`). As versões realmente executadas e o escopo Windows local estão no [contrato de verificações](docs/stabilization/ci-runtime.md).
- Escolha uma pasta nova e um banco vazio exclusivo antes de instalar. Os passos abaixo são de preparação de uma nova aplicação; não são comandos de manutenção nem testes para um checkout com dados existentes.

## Instalação em uma cópia nova

No terminal aberto na raiz da cópia nova, confira primeiro o destino do banco e as integrações. Prepare um `.env` local a partir de [`.env.example`](.env.example), com chave e credenciais próprias. Não copie `.env`, `.config`, bancos ou credenciais de outra aplicação. Mantenha os serviços externos desativados ou em destinos de desenvolvimento; exemplos de configuração não autorizam envios reais.

```powershell
composer install --prefer-dist --no-interaction
php artisan app:init --skip-storage-link --no-interaction
```

O [comando de inicialização](app/Console/Commands/AppInitCommand.php) prepara arquivos locais e SQLite quando aplicável, preservando arquivos, chave e banco já existentes. Ele não substitui a conferência do destino. Não use `key:generate`, `migrate:fresh`, `composer run setup` ou uma receita de portabilidade para corrigir uma instalação que contenha dados.

Depois de confirmar que a conexão aponta exclusivamente ao banco novo e vazio escolhido para essa cópia:

```powershell
php artisan migrate --no-interaction
npm ci
npm run build
```

Mantenha a política existente [`.npmrc`](.npmrc), com scripts npm desativados. Não use `update`, `--force`, `--legacy-peer-deps` ou pins nativos adicionais para contornar inconsistências da instalação. O build gera os helpers Wayfinder e os assets a partir das fontes. O alias `composer run setup` também instala, inicializa, migra e compila; por isso não é uma etapa de retomada de uma aplicação existente.

## Permissões iniciais

O `db:seed` chama `RolesSeeder` e `PermissionsSeeder`. O segundo cadastra o catálogo funcional de `config/permissions.php` e concede essas capacidades explicitamente a `developer`, `super-admin` e `admin`. O papel `user` recebe apenas `access-painel`. As policies continuam aplicando hierarquia, alvo e ambiente; ações técnicas e de gestão de autoridade não recebem bypass por esses vínculos.

Para aplicar somente esse provisionamento em uma instalação local existente, após conferir a conexão e os papéis que receberão as capacidades, execute na raiz do projeto:

```powershell
php artisan db:seed --class='App\Modules\Permissions\Database\Seeders\PermissionsSeeder' --no-interaction
```

O seeder é transacional e pode ser repetido: preserva metadados e vínculos existentes, adicionando os vínculos padrão ausentes. Reexecutá-lo também restaura capacidades padrão que tenham sido removidas dos papéis administrativos; ele não altera permissões diretas dos usuários.

## Desenvolvimento e recursos opcionais

```powershell
composer run dev
```

O comando inicia servidor Laravel, listener da fila e Vite. Reverb é um processo opcional separado; sua ativação exige configuração coerente do backend e do cliente. A configuração de exemplo deixa `INERTIA_SSR_ENABLED=false`. Na produção, SSR requer bundle e processo SSR próprios: compilar `build:ssr` comprova apenas a compilação. Consulte a [validação dos perfis opcionais](docs/stabilization/tasks/OPS-004.md) e os contratos de [workers](docs/stabilization/ops-worker-runtime.md) e [agendamento](docs/stabilization/ops-scheduler-runtime.md) antes de operá-los.

## Verificações isoladas

Os testes não devem usar o banco, arquivos privados, filas, e-mail ou integrações desta instalação. Siga a preparação de [CI no Windows local](docs/stabilization/ci-runtime.md), com cópia descartável, variáveis sintéticas, hostname próprio resolvendo somente para loopback e os guards de [TestEnvironment](tests/TestEnvironment.php), [bootstrap](tests/bootstrap.php) e [TestCase](tests/TestCase.php).

Nessa cópia preparada, a entrada canônica é [tests/ci.php](tests/ci.php): o perfil `common` executa a suíte comum; `native` exige também Oracle MySQL descartável e os opt-ins documentados; `all` combina os dois. Exemplos e pré-requisitos completos estão no contrato CI. Código de saída, JUnit e contagens determinam o resultado; skips e falhas não são aprovação. Os perfis adicionais de lotes individuais continuam identificados nos respectivos documentos.

O escopo comprovado da estabilização é Windows local. Não atribua às evidências locais execução Linux, E2E global ou produção. Consulte as [decisões sobre majors](docs/stabilization/tasks/DEP-003.md): a retenção de ESLint 9 é uma exceção explícita de ferramenta de desenvolvimento em EOL, limitada pela incompatibilidade oficial do plugin React com ESLint 10.

## Documentação e retomada

- [Mapa da arquitetura](.docs/project/architecture.md): módulos, providers, frontend, configuração e contratos compartilhados.
- [Guia de retomada](.docs/project/project.md): onde consultar estado, evidências, decisões e limites antes de continuar.
- [Índice dos módulos](docs/.start_kit.obs/Bem-vindo.md): documentação canônica e receitas contextuais identificadas.
- [Painel da estabilização](docs/stabilization/board.md) e [critérios de conclusão](docs/stabilization/README.md): autorização, tarefas e evidências vigentes.
- [Backup e recuperação](docs/backups/backup-dumps-module.md) e [prova de restauração OPS-003](docs/stabilization/tasks/OPS-003.md): banco, arquivos e segredos recuperados separadamente em destino descartável. Um backup criado não comprova restauração.

Este guia não ativa integrações, cria usuário privilegiado, executa restauração ou autoriza alterações em produção. Retome a aplicação existente a partir de sua configuração preservada e do procedimento documentado para a operação necessária.
