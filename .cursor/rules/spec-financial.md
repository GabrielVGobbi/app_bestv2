# Especificação Técnica — Módulo Financeiro

## Objetivo

Definir a estrutura ideal do módulo financeiro para um sistema construído com **Laravel + React + Inertia.js + MySQL**, cobrindo:

* contas a pagar
* contas a receber
* transações bancárias
* bancos
* transferências internas
* fluxo de caixa
* conciliação
* categorias
* recorrência
* visão gerencial

Esta especificação assume que o sistema já possui as entidades:

* `companies`
* `cost_centers`
* `banks`

Portanto, elas serão tratadas apenas como dependências estruturais do domínio financeiro.

---

## Premissas de modelagem

### 1. Separação de responsabilidades

O domínio financeiro deve separar claramente:

* **obrigação financeira**: aquilo que a empresa deve pagar ou tem a receber
* **movimentação real**: aquilo que realmente entrou ou saiu de uma conta bancária/caixa
* **transferência interna**: movimentação entre contas da própria empresa
* **conciliação**: vínculo entre obrigação e movimentação real
* **visão gerencial**: leitura consolidada para tomada de decisão

### 2. Dinheiro armazenado como inteiro

Valores monetários devem ser armazenados como **inteiro (`BIGINT`)**, nunca como `DECIMAL`.

Exemplo de convenção:

* `1000` = R$ 10,00
* `105055` = R$ 1.050,55

A recomendação é adotar uma unidade mínima fixa, normalmente **centavos**.

### 3. Fonte da verdade do saldo

O saldo real das contas não deve depender de edição manual livre.

A fonte da verdade deve ser:

* saldo inicial da conta
* soma das transações confirmadas dessa conta

### 4. Nada crítico deve ser apagado

Registros financeiros críticos não devem ser removidos fisicamente em operações normais.

Deve existir:

* cancelamento
* estorno
* reversão
* trilha de auditoria

---

# Estrutura macro do domínio

## Camadas

### 1. Cadastros base

Entidades de apoio:

* `companies` (já existente)
* `cost_centers` (já existente)
* `banks` (já existente, substituindo `financial_accounts`)
* `financial_categories`
* `payment_methods`
* `recurring_templates`

### 2. Obrigações financeiras

Entidades que representam compromissos:

* `financial_entries`
* `financial_entry_installments`

### 3. Movimentações reais

Entidades que representam fatos ocorridos:

* `bank_transactions`
* `account_transfers`
* `bank_statement_imports`
* `bank_statement_lines`

### 4. Conciliação

Entidade relacional:

* `reconciliations`

### 5. Leitura gerencial

Pode ser construída via:

* queries otimizadas
* query objects
* views SQL
* tabelas materializadas, se necessário

---

# Estrutura das entidades

## 1. `companies`

Já existente.

Uso esperado:

* multiempresa / multi-tenant lógico
* escopo obrigatório em praticamente todas as tabelas financeiras

Campos esperados no domínio:

* `id`
* `name`
* outros campos institucionais

### Observação

Todas as tabelas financeiras devem possuir `company_id`.

---

## 2. `cost_centers`

Já existente.

Uso esperado:

* classificação gerencial por área
* relatórios por operação
* separação por unidade de negócio

Exemplos:

* Administrativo
* Comercial
* Marketing
* Operação

---

## 3. `banks`

Esta entidade substitui o papel que normalmente seria de `financial_accounts`.

No seu domínio, `banks` representa a conta ou origem financeira onde o dinheiro existe.

### Responsabilidade

Representar:

* conta corrente
* conta digital
* carteira
* caixa
* conta investimento, se fizer sentido

### Estrutura sugerida

* `id`
* `company_id`
* `name`
* `type`
* `bank_name`
* `bank_code`
* `agency`
* `account_number`
* `pix_key`
* `currency_code`
* `initial_balance` `BIGINT`
* `current_balance_cache` `BIGINT` nullable
* `is_active`
* `metadata` json nullable
* `created_at`
* `updated_at`

### Sugestão para `type`

* `checking`
* `savings`
* `cash`
* `wallet`
* `investment`
* `card_settlement`

### Regras

* `initial_balance` é o saldo de partida
* `current_balance_cache` é opcional, apenas para performance
* a fonte real do saldo continua sendo o histórico de transações

---

## 4. `financial_categories`

Responsável pela classificação financeira e gerencial.

### Estrutura sugerida

* `id`
* `company_id`
* `parent_id` nullable
* `name`
* `type`
* `code` nullable
* `color` nullable
* `is_active`
* `created_at`
* `updated_at`

### Sugestão para `type`

* `income`
* `expense`
* `transfer`
* `neutral`

### Regras

* deve suportar hierarquia
* deve permitir relatórios agrupados por categoria pai
* transferência interna não deve contaminar receita/despesa gerencial

### Exemplo de árvore

* Receita

  * Serviços
  * Vendas
* Despesas

  * Marketing
  * Folha
  * Impostos
* Transferências

  * Entre contas

---

## 5. `payment_methods`

Tabela opcional, mas recomendada.

### Objetivo

Classificar como o pagamento/recebimento foi ou será realizado.

### Estrutura sugerida

* `id`
* `company_id`
* `name`
* `type`
* `is_active`
* `created_at`
* `updated_at`

### Exemplos de `type`

* `pix`
* `bank_slip`
* `bank_transfer`
* `cash`
* `credit_card`
* `debit_card`
* `check`
* `internal`

---

# Obrigações financeiras

## 6. `financial_entries`

Entidade central do módulo.

Representa o título financeiro: aquilo que deve ser pago ou recebido.

### Responsabilidade

Serve para:

* contas a pagar
* contas a receber
* lançamentos avulsos
* títulos originados de outros módulos
* previsão financeira

### Estrutura sugerida

* `id`
* `company_id`
* `type`
* `bank_id` nullable
* `category_id`
* `cost_center_id` nullable
* `payment_method_id` nullable
* `source_type` nullable
* `source_id` nullable
* `document_number` nullable
* `external_reference` nullable
* `description`
* `notes` text nullable
* `issue_date` nullable
* `competence_date` nullable
* `due_date`
* `expected_amount` `BIGINT`
* `open_amount` `BIGINT`
* `paid_amount` `BIGINT` default 0
* `discount_amount` `BIGINT` default 0
* `interest_amount` `BIGINT` default 0
* `fine_amount` `BIGINT` default 0
* `status`
* `settlement_date` nullable
* `is_recurring_generated` boolean default false
* `created_by` nullable
* `approved_by` nullable
* `cancelled_by` nullable
* `cancelled_at` nullable
* `created_at`
* `updated_at`

### Sugestão para `type`

* `payable`
* `receivable`

### Sugestão para `status`

* `draft`
* `open`
* `partial`
* `settled`
* `overdue`
* `cancelled`

### Regras

* `expected_amount` = valor original do título
* `open_amount` = valor ainda não liquidado
* `paid_amount` = valor já quitado/recebido
* `settlement_date` só deve existir quando totalmente liquidado
* `status = overdue` quando vencido e ainda aberto
* títulos podem nascer de outros módulos via `source_type` e `source_id`

### Observação importante

Mesmo que um título tenha apenas uma parcela, ele ainda pode usar a estrutura de parcelas para manter consistência de domínio.

---

## 7. `financial_entry_installments`

Representa as parcelas reais de um título.

### Por que essa tabela é importante

Sem ela, parcelamento, recebimento parcial, múltiplas baixas e aging ficam confusos.

### Estrutura sugerida

* `id`
* `company_id`
* `financial_entry_id`
* `installment_number`
* `description` nullable
* `due_date`
* `expected_amount` `BIGINT`
* `open_amount` `BIGINT`
* `settled_amount` `BIGINT` default 0
* `discount_amount` `BIGINT` default 0
* `interest_amount` `BIGINT` default 0
* `fine_amount` `BIGINT` default 0
* `status`
* `settlement_date` nullable
* `created_at`
* `updated_at`

### Sugestão para `status`

* `open`
* `partial`
* `settled`
* `overdue`
* `cancelled`

### Regras

* somatório das parcelas deve bater com o título
* conciliação preferencialmente deve acontecer na parcela
* uma parcela pode ser quitada por uma ou mais transações
* uma transação pode quitar uma ou mais parcelas

---

# Movimentações financeiras reais

## 8. `bank_transactions`

Representa todo movimento real de entrada ou saída nas contas.

### Responsabilidade

É a base do saldo realizado.

### Estrutura sugerida

* `id`
* `company_id`
* `bank_id`
* `direction`
* `transaction_type`
* `transaction_date`
* `posted_at` nullable
* `amount` `BIGINT`
* `description`
* `document_number` nullable
* `external_reference` nullable
* `counterparty_name` nullable
* `category_id` nullable
* `payment_method_id` nullable
* `origin_type`
* `origin_id` nullable
* `reconciliation_status`
* `statement_line_id` nullable
* `imported_hash` nullable unique
* `metadata` json nullable
* `created_by` nullable
* `cancelled_at` nullable
* `created_at`
* `updated_at`

### Sugestão para `direction`

* `inflow`
* `outflow`

### Sugestão para `transaction_type`

* `manual`
* `settlement`
* `transfer`
* `fee`
* `refund`
* `adjustment`
* `statement_import`

### Sugestão para `reconciliation_status`

* `pending`
* `partial`
* `reconciled`
* `ignored`

### Regras

* toda saída ou entrada real deve existir aqui
* não apagar transação; cancelar ou estornar
* transferência gera duas transações relacionadas
* tarifa pode gerar uma transação separada
* o saldo realizado vem dessa tabela

---

## 9. `account_transfers`

Representa transferência entre contas da própria empresa.

### Estrutura sugerida

* `id`
* `company_id`
* `from_bank_id`
* `to_bank_id`
* `transfer_date`
* `amount` `BIGINT`
* `fee_amount` `BIGINT` default 0
* `description` nullable
* `status`
* `outflow_transaction_id` nullable
* `inflow_transaction_id` nullable
* `fee_transaction_id` nullable
* `created_by` nullable
* `cancelled_by` nullable
* `cancelled_at` nullable
* `created_at`
* `updated_at`

### Sugestão para `status`

* `draft`
* `completed`
* `cancelled`

### Regras

* deve sempre ocorrer dentro de `DB::transaction()`
* gera uma saída na conta origem
* gera uma entrada na conta destino
* opcionalmente gera uma tarifa
* não compõe receita nem despesa gerencial

---

# Importação de extrato

## 10. `bank_statement_imports`

Representa um arquivo/processo de importação de extrato.

### Estrutura sugerida

* `id`
* `company_id`
* `bank_id`
* `file_name`
* `file_type`
* `status`
* `imported_at` nullable
* `imported_by` nullable
* `metadata` json nullable
* `created_at`
* `updated_at`

### Sugestão para `file_type`

* `ofx`
* `csv`
* `xlsx`
* `manual`

### Sugestão para `status`

* `pending`
* `processed`
* `failed`

---

## 11. `bank_statement_lines`

Representa as linhas brutas do extrato importado.

### Estrutura sugerida

* `id`
* `company_id`
* `bank_statement_import_id`
* `bank_id`
* `line_date`
* `description`
* `document_number` nullable
* `amount` `BIGINT`
* `direction`
* `hash` unique
* `raw_data` json nullable
* `matched_bank_transaction_id` nullable
* `created_at`
* `updated_at`

### Regras

* nunca descartar a linha bruta importada
* hash evita duplicidade
* a linha pode virar transação ou ser associada a uma já existente
* serve de base para auditoria e reprocessamento

---

# Conciliação

## 12. `reconciliations`

Tabela de vínculo entre movimentação real e obrigação financeira.

### Estrutura sugerida

* `id`
* `company_id`
* `bank_transaction_id`
* `reconciliable_type`
* `reconciliable_id`
* `amount` `BIGINT`
* `reconciled_at`
* `created_by` nullable
* `notes` nullable
* `created_at`
* `updated_at`

### `reconciliable_type` esperado

Principalmente:

* `financial_entry_installment`
* `financial_entry`

### Regras

* uma transação pode ter várias reconciliações
* uma parcela pode ter várias reconciliações
* o valor conciliado nunca pode exceder:

  * o valor da transação
  * o valor em aberto da parcela/título
* conciliação parcial deve ser suportada
* conciliação é o elo entre previsto e realizado

---

# Recorrência

## 13. `recurring_templates`

Modelo de geração recorrente de títulos.

### Estrutura sugerida

* `id`
* `company_id`
* `type`
* `bank_id` nullable
* `category_id`
* `cost_center_id` nullable
* `payment_method_id` nullable
* `description`
* `notes` nullable
* `amount` `BIGINT`
* `frequency`
* `start_date`
* `end_date` nullable
* `next_run_date`
* `is_active`
* `last_generated_at` nullable
* `created_by` nullable
* `created_at`
* `updated_at`

### Sugestão para `frequency`

* `daily`
* `weekly`
* `monthly`
* `yearly`

### Regras

* deve gerar `financial_entries`
* não deve duplicar geração para a mesma competência indevidamente
* precisa de trava de idempotência

---

# Tabelas auxiliares recomendadas

## 14. `attachments`

Opcional, mas útil.

Para anexar:

* comprovantes
* boletos
* notas fiscais
* documentos financeiros

### Estrutura sugerida

* `id`
* `company_id`
* `attachable_type`
* `attachable_id`
* `file_name`
* `file_path`
* `mime_type`
* `file_size`
* `uploaded_by` nullable
* `created_at`
* `updated_at`

---

## 15. `activity_logs`

Recomendado para trilha de auditoria.

Pode ser nativo do sistema ou via pacote como Spatie Activitylog.

Registrar mudanças críticas:

* criação de título
* edição de valor
* baixa manual
* conciliação
* cancelamento
* estorno
* transferência

---

# Relacionamentos principais

## `companies`

* hasMany `banks`
* hasMany `financial_categories`
* hasMany `payment_methods`
* hasMany `financial_entries`
* hasMany `financial_entry_installments`
* hasMany `bank_transactions`
* hasMany `bank_statement_imports`
* hasMany `bank_statement_lines`
* hasMany `account_transfers`
* hasMany `reconciliations`
* hasMany `recurring_templates`

## `banks`

* belongsTo `company`
* hasMany `financial_entries`
* hasMany `bank_transactions`
* hasMany `bank_statement_imports`
* hasMany `bank_statement_lines`
* hasMany `outgoingTransfers` via `from_bank_id`
* hasMany `incomingTransfers` via `to_bank_id`

## `financial_entries`

* belongsTo `company`
* belongsTo `bank` nullable
* belongsTo `category`
* belongsTo `cost_center` nullable
* belongsTo `payment_method` nullable
* morphTo `source` nullable
* hasMany `installments`

## `financial_entry_installments`

* belongsTo `company`
* belongsTo `financial_entry`
* morphMany `reconciliations`

## `bank_transactions`

* belongsTo `company`
* belongsTo `bank`
* belongsTo `category` nullable
* belongsTo `payment_method` nullable
* hasMany `reconciliations`
* belongsTo `statementLine` nullable
* morphTo `origin` nullable

## `account_transfers`

* belongsTo `company`
* belongsTo `fromBank`
* belongsTo `toBank`
* belongsTo `outflowTransaction` nullable
* belongsTo `inflowTransaction` nullable
* belongsTo `feeTransaction` nullable

---

# Regras de negócio obrigatórias

## 1. Título financeiro não é transação bancária

Um título representa obrigação.
Uma transação representa fato realizado.

Os dois não podem ser confundidos.

## 2. Conciliação deve suportar parcial

Cenários reais:

* cliente pagou parte
* conta foi quitada em duas transferências
* uma entrada única recebeu várias parcelas

## 3. Transferência interna é neutra gerencialmente

Ela altera saldo entre contas, mas não deve entrar como receita ou despesa do negócio.

## 4. Estorno não apaga histórico

Deve gerar reversão rastreável.

## 5. Cancelamento depende de regra de segurança

Exemplo:

* título conciliado não pode ser cancelado sem antes desfazer a conciliação

## 6. Overdue deve ser derivado de data + status

Pode ser persistido ou calculado, mas a regra precisa ser consistente.

## 7. Importação bancária precisa prevenir duplicidade

Use hash por linha importada.

## 8. Toda operação financeira crítica deve usar transação de banco

Exemplos:

* criar transferência
* baixar título e gerar transação
* desfazer conciliação
* estornar movimentação

## 9. Dinheiro deve ser formatado só na borda

No banco e no domínio: inteiro.
Na interface: máscara/formatador.

---

# Fluxos principais

## Fluxo 1 — Conta a pagar

1. usuário cria `financial_entry` do tipo `payable`
2. sistema gera 1 ou N parcelas em `financial_entry_installments`
3. título entra no fluxo previsto
4. pagamento ocorre
5. sistema gera `bank_transaction` de saída ou importa do extrato
6. conciliação vincula transação à parcela
7. parcela/título atualizam `open_amount`, `settled_amount`, `status`

## Fluxo 2 — Conta a receber

1. usuário cria `financial_entry` do tipo `receivable`
2. sistema gera parcelas, se necessário
3. entra no fluxo previsto
4. recebimento cai em conta ou é registrado manualmente
5. `bank_transaction` de entrada é criada/encontrada
6. conciliação vincula entrada ao recebível
7. sistema atualiza baixa parcial ou total

## Fluxo 3 — Transferência interna

1. usuário informa conta origem, conta destino e valor
2. action valida regras
3. sistema cria `account_transfer`
4. dentro de transação atômica:

   * cria saída em `bank_transactions`
   * cria entrada em `bank_transactions`
   * cria tarifa, se existir
5. registra vínculos na transferência

## Fluxo 4 — Importação de extrato

1. usuário envia OFX/CSV/XLSX
2. sistema cria `bank_statement_imports`
3. parser gera `bank_statement_lines`
4. sistema identifica duplicidade por hash
5. cada linha pode:

   * gerar nova `bank_transaction`
   * ser vinculada a transação existente
   * aguardar conciliação manual

## Fluxo 5 — Recorrência

1. job verifica `recurring_templates`
2. identifica templates com `next_run_date <= hoje`
3. gera novo `financial_entry`
4. atualiza `next_run_date`
5. grava controle de geração

---

# Fluxo de caixa

## Conceitos obrigatórios

### Fluxo previsto

Vem de:

* `financial_entries`
* `financial_entry_installments`
* recorrências futuras

### Fluxo realizado

Vem de:

* `bank_transactions`

## Recomendação de leitura

Separar sempre:

* entradas previstas
* saídas previstas
* entradas realizadas
* saídas realizadas
* saldo inicial
* saldo acumulado projetado
* saldo acumulado realizado

---

# Arquitetura no Laravel

## Estrutura recomendada

```txt
app/
 ├─ Domains/
 │   ├─ Financial/
 │   │   ├─ Actions/
 │   │   ├─ DTOs/
 │   │   ├─ Enums/
 │   │   ├─ Models/
 │   │   ├─ Policies/
 │   │   ├─ Queries/
 │   │   ├─ Services/
 │   │   └─ Support/
```

## Recomendação por camada

### Models

Somente:

* relacionamentos
* casts
* escopos simples
* helpers leves

### Actions

Ponto principal da regra de negócio.

Exemplos:

* `CreateFinancialEntryAction`
* `GenerateInstallmentsAction`
* `SettleInstallmentAction`
* `CreateBankTransactionAction`
* `CreateTransferAction`
* `ReconcileTransactionAction`
* `UndoReconciliationAction`
* `ImportBankStatementAction`
* `GenerateRecurringEntriesAction`

### Services

Para lógica de domínio compartilhada e orquestração.

### DTOs

Para payloads claros entre controller, action e service.

### Enums

Altamente recomendado.

Exemplos:

* `FinancialEntryTypeEnum`
* `FinancialEntryStatusEnum`
* `InstallmentStatusEnum`
* `TransactionDirectionEnum`
* `TransactionTypeEnum`
* `ReconciliationStatusEnum`
* `BankTypeEnum`
* `RecurringFrequencyEnum`

### Policies

Autorização por empresa, módulo e ação.

---

# Estrutura no React + Inertia

## Módulos de interface

* Dashboard Financeiro
* Contas a Pagar
* Contas a Receber
* Transações Bancárias
* Conciliação
* Transferências
* Fluxo de Caixa
* Categorias
* Bancos
* Recorrências
* Relatórios

## Padrão recomendado de páginas

Cada módulo pode seguir:

* `Index.tsx`
* `Show.tsx`
* `Partials/Form.tsx`
* `Partials/Filters.tsx`
* `Partials/Table.tsx`
* `Partials/Actions.tsx`

## Exemplo de UX importante

### Contas a pagar / receber

* filtros por status, categoria, centro de custo, banco, período
* criação rápida
* baixa manual
* conciliar
* ver histórico
* anexos

### Conciliação

Tela em duas colunas:

* transações bancárias pendentes
* parcelas/títulos em aberto

Com sugestão automática por:

* valor
* proximidade de data
* descrição/documento

---

# Índices recomendados

## Para quase todas as tabelas

* índice por `company_id`

## Específicos

### `financial_entries`

* `(company_id, type, status)`
* `(company_id, due_date)`
* `(company_id, category_id)`
* `(company_id, cost_center_id)`
* `(source_type, source_id)`

### `financial_entry_installments`

* `(company_id, financial_entry_id)`
* `(company_id, due_date, status)`

### `bank_transactions`

* `(company_id, bank_id, transaction_date)`
* `(company_id, reconciliation_status)`
* `(origin_type, origin_id)`
* `imported_hash unique` quando fizer sentido

### `bank_statement_lines`

* `hash unique`
* `(company_id, bank_id, line_date)`

### `reconciliations`

* `(bank_transaction_id)`
* `(reconciliable_type, reconciliable_id)`

---

# Decisões importantes já definidas nesta spec

## 1. Não usar `contacts`

A responsabilidade por cliente/fornecedor fica para outra camada ou módulo, se necessário.

Se futuramente existir vínculo com terceiro, isso pode ser feito com:

* `counterparty_type`
* `counterparty_id`

ou até apenas campos descritivos no primeiro momento.

## 2. Não usar `financial_accounts`

No seu contexto, a entidade financeira base será `banks`.

## 3. Não usar `DECIMAL`

Todos os valores monetários serão `BIGINT`.

## 4. A modelagem é orientada por domínio, não por tela

Ou seja, as tabelas não existem para servir ao front isoladamente.
Elas existem para preservar consistência financeira.

---

# Estrutura mínima viável recomendada para começar

Se quiser iniciar sem exagerar, o MVP sólido seria:

* `financial_categories`
* `payment_methods`
* `financial_entries`
* `financial_entry_installments`
* `bank_transactions`
* `account_transfers`
* `reconciliations`
* `bank_statement_imports`
* `bank_statement_lines`
* `recurring_templates`

Com dependência de:

* `companies`
* `cost_centers`
* `banks`

---

# Conclusão

A melhor estrutura para esse sistema financeiro é baseada em quatro pilares:

1. **título financeiro** para obrigação
2. **transação bancária** para fato realizado
3. **transferência interna** como evento próprio
4. **conciliação** como elo entre previsto e realizado

Com isso, você consegue suportar corretamente:

* contas a pagar
* contas a receber
* baixa parcial
* múltiplas parcelas
* extrato bancário
* conciliação manual/automática
* fluxo de caixa previsto e realizado
* relatórios gerenciais confiáveis

Se a implementação respeitar essa separação, o sistema nasce com base forte para crescer sem virar um emaranhado de regras quebradas.
