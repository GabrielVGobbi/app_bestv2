# Projeto e retomada

Comece pelo [README da aplicação](../../README.md) para uma cópia nova e por [AGENTS.md](../../AGENTS.md) para as regras. O [mapa da arquitetura](architecture.md) aponta o código e a documentação dos domínios, sem criar contratos paralelos.

## Continuar uma entrega

1. Leia o estado vigente no [README da estabilização](../../docs/stabilization/README.md) e no [painel](../../docs/stabilization/board.md). Registros anteriores são históricos; a presença de uma tarefa no backlog não autoriza sua execução.
2. Abra a nota da tarefa indicada no painel e siga seu escopo, dependências, próxima ação, evidência e limites. O [backlog](../../docs/stabilization/backlog.md) é um índice derivado; o [registro de evidências](../../docs/stabilization/evidence.md) liga decisões aos resultados reais.
3. Confira as alterações locais antes de editar e preserve o trabalho anterior. Leia o documento canônico do domínio pelo [índice do vault](../../docs/.start_kit.obs/Bem-vindo.md), especialmente antes de mexer em exports, filas ou downloads.
4. Para verificar mudanças, prepare a cópia descartável descrita em [ci-runtime.md](../../docs/stabilization/ci-runtime.md). Nunca use os dados e integrações da aplicação existente para essa finalidade. Os guards, fakes, opt-ins e destinos isolados fazem parte da verificação.
5. Registre o que mudou, os checks realmente executados, falhas intermediárias e o que ficou sem prova. Atualize a tarefa e os links de estado somente após os critérios pertinentes e a revisão prevista.

## Decisões que precisam continuar visíveis

- A autoridade vem de [PREP-002](../../docs/stabilization/tasks/PREP-002.md#contrato-adotado), incluindo escopo por alvo, ambiente e token; não inferir acesso pelo nome de um papel.
- Instalação e execução comprovadas nesta iniciativa são Windows local. Linux, recursos de produção e outros perfis exigem evidência correspondente; cobertura histórica de outro lock não valida uma instalação nova.
- [DEP-002](../../docs/stabilization/tasks/DEP-002.md) consolida os manifests e a saída PHPUnit nativa. O resultado é definido por exit code, contagens e JUnit.
- [DEP-003](../../docs/stabilization/tasks/DEP-003.md) registra as decisões de majors sob o congelamento de escopo. ESLint 9 está em EOL e permanece como exceção explícita de tooling; isso não significa suporte upstream nem ausência de vulnerabilidades. Outras migrações opcionais não devem ser iniciadas automaticamente.
- SSR/realtime seguem [OPS-004](../../docs/stabilization/tasks/OPS-004.md); banco, arquivos e segredos seguem os contratos de [backup](../../docs/backups/backup-dumps-module.md) e [restauração](../../docs/stabilization/tasks/OPS-003.md).

Não executar `setup`, instalação, migrações, limpeza de dados ou restauração para retomar um checkout existente. Identifique primeiro a operação necessária e seu destino. Esta documentação não autoriza commit, push, deploy, produção, cobrança ou mensagens reais sob a iniciativa de estabilização.
