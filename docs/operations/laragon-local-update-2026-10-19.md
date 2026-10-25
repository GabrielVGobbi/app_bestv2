# Atualização da instalação Laragon — 2026-10-19

Concluída por autorização humana para conferir e atualizar a instalação existente, preservando dados e configurações, sem pausas por etapa. Esta operação posterior não reabre os 66 itens encerrados da estabilização.

## Resultado

- Seis migrations aplicadas; 37 registradas, nenhuma pendente. Nenhum refresh, reset, seed, troca de senha, chave ou concessão de permissões.
- Comparação das 36 tabelas imediatamente após as migrations: todas as linhas e valores anteriores iguais; apenas seis registros acrescentados ao histórico de migrations. Após a verificação no navegador e a ativação da fila, os dados de aplicação continuam iguais; sessões e observabilidade receberam os registros normais dessas operações.
- `.env`, `.config`, `.npmrc`, manifests/locks Composer/npm e `bootstrap/app.php` preservados por SHA-256. A chave e as credenciais existentes permanecem as mesmas.
- Composer instalado corresponde aos 157 pacotes do lock; requisitos da plataforma aprovados. `node_modules` foi sincronizado pelo lock, offline e com scripts desativados: 596 pacotes, sem divergências de versão.
- Builds cliente e SSR concluídos; todos os arquivos referidos pelo manifesto existem. Views compiladas foram limpas; o servidor voltou ao ar e não há marcador de manutenção.
- Listener local iniciado com `queue:listen --timeout=960 --tries=1`, preservando a configuração original e seu número de tentativas. A fila estava vazia; nenhum job de verificação foi enfileirado. Identidade inicial: `laragon-local-4c7b0c55`, PID 29804. O PID é histórico; conferir processo/comando antes de operá-lo. Não foi instalado serviço Windows ou início automático.

## Backup e recuperação

Diretório privado, fora do projeto e da raiz web:

`D:\laragon\backups\start-kit\20261019-200900-4c7b0c55`

Contém `database-before.sql` (92.764.380 bytes), configuração original, assets anteriores e recibos privados. ACL Windows restrita ao operador, SYSTEM e Administradores. O backup contém dados sensíveis e não é um artefato para publicar ou versionar.

O dump completo foi restaurado em outra instância MySQL, com UUID e porta distintos. As contagens e os hashes das linhas das 36 tabelas coincidiram. O processo foi encerrado e o clone descartável removido depois de conferir caminho, marcador de propriedade, ausência de processos e reparse points. A instância MySQL original do Laragon permaneceu em execução.

Para recuperação futura, seguir o [procedimento de recuperação](../stabilization/ops-backup-recovery.md) em destino separado e identificado. Esta atualização não executou rollback nem restauração sobre o banco original.

## Verificação da instalação real

[Acesso local](http://www2.start_kit.com.br/login), resolvido para loopback; Apache/PHP 8.3.4 e MySQL do Laragon existentes.

Chrome headless, perfil separado: 12 verificações aprovadas, 98 respostas, nenhuma exceção JavaScript e nenhum POST. Páginas inicial/login e saúde HTTP 200; assets servidos, interação React do campo de senha e layout móvel funcionais; dashboard anônimo redireciona para login. O domínio HTTP real não é contexto seguro e não oferece `crypto.randomUUID`; nenhuma chamada nova foi introduzida.

Não foram usadas credenciais existentes, criadas contas, executadas alterações CRUD ou acionados SMTP/provedores externos. As cinco requisições de fontes externas foram bloqueadas no perfil de conferência; os diagnósticos correspondentes foram retidos. Não se alega console global vazio ou autenticação com uma conta real.

O bundle SSR foi gerado; processos SSR, Reverb e scheduler não foram iniciados. A configuração existente foi preservada. Para reiniciar a fila local após encerrar o processo atual, usar na raiz do projeto:

```powershell
php artisan queue:listen --timeout=960 --tries=1
```

## Evidências e preparação

Os recibos completos estão no diretório privado: `installation-result.json`, `protected-before.json`, `database-inventory-before.json`, `rows-before.json`, `rows-after-migrations.json`, `rows-after.json`, `backup.json`, `restore-verification.json`, `restore-directory-cleanup.json`, `migrate.log`, `status.log`, `build-receipt.json`, `browser-verification.json` e `queue-runtime.json`.

Ocorrências de preparação preservadas: escrita inicial recusada pela ACL restrita e repetida com acesso adequado; atalho PowerShell npm incompatível com a invocação composta, resolvido pelo executável Node/npm CLI explícito; primeira guarda de limpeza recusou diferença de separador final, depois normalizada sem remover as verificações de propriedade/processos. Nenhuma dessas tentativas foi considerada aprovação.

Não houve commit, push, merge, deploy de produção, testes com fixtures sobre o banco existente ou alteração global do Laragon/PHP/Apache. Os artefatos da estabilização anterior permanecem evidências históricas da cópia isolada; a mudança dos assets e do schema existentes foi autorizada e comprovada nesta operação.
