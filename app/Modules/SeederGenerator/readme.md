# Seeder Generator

Ferramenta técnica para visualizar, gerar e executar seeders de dados explicitamente autorizados. As seis rotas em `admin/dev/seeder-generator` exigem sessão autenticada com o papel literal `developer` e ambiente coerente `local` ou `testing`. Bearer tokens não habilitam a ferramenta. Os serviços também recusam ambientes incompatíveis.

## Configuração por projeto

`config/seeder-generator.php` começa com `allowed_tables => []`: nenhuma tabela fica disponível automaticamente. O responsável pelo projeto deve revisar os dados antes de habilitar tabela, colunas, modos e operações. Autorizar uma tabela não autoriza todas as suas colunas.

Exemplo ilustrativo para uma tabela de catálogo sem dados pessoais, que deve existir com `code` único e não nulo:

```php
'allowed_tables' => [
    'reference_catalog' => [
        'columns' => ['label', 'code'],
        'unique_columns' => ['code'],
        'modes' => ['data'],
        'operations' => ['preview', 'generate', 'execute', 'run'],
        'truncate' => false,
    ],
],
```

O exemplo não habilita uma tabela real deste starter kit. Dados pessoais, anonimização e escopos privados precisam de um contrato próprio antes dessa configuração. Tabelas de identidade, autoridade, autenticação, tokens, integrações, configurações, logs e infraestrutura permanecem proibidas. Colunas secretas ou contêineres de dados sensíveis são recusadas. A amostra usa somente a projeção permitida.

Modo `factory` exige mapping explícito de model/factory confiáveis, `factory_trusted => true` e compatibilidade com tabela/conexão. A ferramenta não descobre nem autoriza factories por semelhança de nomes. Opções de dados retidas pela interface, como truncate e unique_column, não operam no modo factory. O responsável deve revisar callbacks da factory: uma transação SQL não desfaz efeitos externos arbitrários.

## Geração e execução

O limite por operação é de 1 a 1.000 registros. Nomes e parâmetros são validados também nos serviços; os valores são serializados como literais PHP. Dados não serializáveis são recusados sem substituição silenciosa. Idempotência exige uma coluna permitida com restrição única simples e não nula demonstrada pelo banco.

Arquivos ficam exclusivamente em `database/seeders` do projeto. Código fornecido pelo cliente não é adotado. Arquivos manuais ou legados não são sobrescritos; links, hardlinks, redirecionamentos e colisões de caixa são recusados. Repetir o mesmo artefato pode ser um no-op; um conteúdo diferente exige outro nome.

O artefato contém um descriptor de dados. Antes de carregar a classe, o serviço compara o arquivo inteiro com a reconstrução canônica e revalida a política atual. Um hash embutido isolado não autentica PHP. O endpoint `run` mantém o payload `{class: class_name}` da interface e não aceita classes arbitrárias ou namespaces fornecidos pelo cliente. Não usa `--force`.

O `run()` da classe gerada delega a execução ao serviço deste módulo. Isso mantém a validação de ambiente, política e banco também no uso pelo terminal; o artefato depende do módulo. A execução usa o snapshot canônico capturado, com a política atual, e não consulta novamente dados para trocar silenciosamente esse snapshot. Classes previamente carregadas são recusadas conservadoramente pelo endpoint; novas requisições PHP carregam a classe após a validação.

Execuções e escritas usam lock do projeto. Uma tentativa ocupada deve ser repetida depois da liberação, sem contornar o lock. Truncate é uma capacidade separada e opt-in: utiliza `DELETE` transacional, preservando FKs, sem `TRUNCATE` ou desligamento de constraints. Somente drivers/engines com as garantias verificadas são aceitos. Falhas SQL restauram o destino transacional e retornam erro genérico. Contagens representam inserts efetivos, incluindo zero em repetição idempotente.

### Amostra e reposição — SEED-001

Sem `truncate`, a amostra permanece não destrutiva e a idempotência existente é preservada. `truncate=true` é opt-in de reposição completa da **projeção autorizada**, condicionado também à política da tabela. A captura consulta no máximo `limit+1`: se houver mais linhas que o limite, preview, geração, save e execução direta recusam a reposição parcial. O limite máximo continua 1.000; tabelas maiores não têm reposição por este gerador.

Snapshots salvos mantêm seus dados e o descriptor v1; não são recapturados para contornar o limite. Antes de apagar, a execução recusa destino atual maior que o número efetivo de linhas do snapshot, mesmo que o `limit` original seja maior. Dentro da transação, o número realmente apagado pelo DELETE também não pode exceder o snapshot. Uma inserção externa após a conferência provoca rollback se tornaria a reposição parcial, inclusive sob MySQL READ COMMITTED; o lock de projeto sozinho não protege contra escritas externas. Linhas inseridas depois do DELETE não são apagadas por ele. Não se promete exclusão global de escritores da aplicação.

Na reposição, cada linha do snapshot deve ser inserida: não há deduplicação silenciosa de projeções idênticas nem descarte de conflito único. Conflitos e falhas de INSERT/FK revertem toda a operação, com FKs sempre habilitadas. A interface recebe erro genérico de execução sem SQL/segredos; capturas parciais apresentam erro de validação em `truncate`.

Reposição opt-in pode substituir valores atuais e não é backup/restauração de todas as colunas: colunas excluídas seguem os defaults/constraints do destino, IDs não projetados podem mudar e sequências/AUTO_INCREMENT não têm promessa de rollback. Snapshots de outra origem podem substituir um destino com número igual ou menor de linhas; essa substituição é a operação destrutiva explicitamente autorizada por `truncate`, nunca consequência de gerar uma amostra comum. Revisar a política e o snapshot antes de habilitá-la; nenhuma tabela real é liberada pelo kit.

## Verificação

Os testes próprios ficam em `tests/Unit/SeederGenerator`, `tests/Feature/SeederGenerator` e fixtures sintéticas em `tests/Fixtures/SeederGenerator`. Os testes padrão usam a sandbox PREP-001 e SQLite em memória; Artisan é interceptado nos testes de fronteira. A integração nativa usa MySQL descartável com opt-ins explícitos, ownership, schema e porta próprios. Links reais são comprovados em Linux isolado.

Resultados executados, snapshots testados e limitações pertencem a [SEC-007](../../../docs/stabilization/tasks/SEC-007.md) e às [evidências](../../../docs/stabilization/evidence.md). A existência de testes ou deste documento não equivale à aprovação do lote.

A proteção de completude e a corrida com escritor externo pertencem a [SEED-001](../../../docs/stabilization/tasks/SEED-001.md); consultar seu gate para as execuções realmente realizadas. Testes nativos usam somente servidor/schema/credenciais/processos descartáveis, nunca dados locais existentes.
