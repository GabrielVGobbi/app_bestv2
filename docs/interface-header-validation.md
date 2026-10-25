# Cabeçalhos, ações e DataTable — 23/10/2026

Solicitação: padronizar o sistema pela ficha de usuário, corrigir o modal de novo
usuário, retirar repetição dos filtros e impedir que o resize arraste Ações.

## Implementação

- 41 páginas operacionais/showcases e o layout das três configurações usam
  `resources/js/components/dashboard/header.tsx`. Título, retorno, navegação e
  ações seguem a mesma composição; subtítulo é opcional e vazio por padrão.
- Clientes mostram somente o nome no cabeçalho e não imprimem `client.uuid`
  na ficha. Usuários mantêm o e-mail. Papéis não repetem a ação Editar.
- Ações de formulário, diálogos, configurações, notificações e autenticação
  receberam ícones; Cancelar/Fechar usa X. Estados de processamento e nomes
  acessíveis foram mantidos. Demonstrações puras de variantes e opções de
  seletores continuam representando os respectivos componentes.
- Diálogos usam `popover` opaco nos dois temas, altura limitada ao viewport e
  rolagem interna. O cadastro de usuário tem rótulos em português.
- Filtro rápido inativo mostra só o nome. Chips de filtros avançados aparecem
  na toolbar quando ativos; não há faixa inferior ou duplicação do filtro rápido.
- Ações nasce fixada à direita. Sua largura permanece protegida; exceção
  explícita continua possível com `actionsConfig.sticky=false`.
- O gerador CRUD produz o mesmo cabeçalho e ícones nas confirmações. Foram
  preservadas as alterações locais anteriores, inclusive identidade da tabela.

## Verificações

- PHPUnit: 226 testes, 1.667 assertions, sem falhas; cinco skips existentes
  de symlink nativo no Windows. Após os últimos ajustes, repetidos os quatro
  contratos afetados: 46 assertions, todos aprovados.
- TypeScript, build com as entradas reais e Pint dos cinco PHP afetados
  aprovados. O build omitiu o gerador Wayfinder e preservou as variáveis públicas
  locais, inclusive a marca Start kit. Assets integrados em `public/build`, sem
  exclusão dos chunks antigos usados por abas abertas; nenhum deploy.
- ESLint em 79 arquivos: 37 erros preexistentes, três avisos e zero erros novos,
  comparando mensagens com as versões anteriores. Persistem unused imports/
  parâmetro em E-mail/Permissões/Papéis e regras de estilo anteriores em
  FileAttachments/Seeder Generator. O aviso do TanStack/React Compiler permanece;
  dois primitivos UI são ignorados pela configuração existente.
- Chrome conectado, HTTP em `www2.start_kit.com.br`, banco MySQL descartável,
  sessão e APIs reais: 36 verificações, dez páginas de detalhes/edição/abas,
  seis listagens, temas claro/escuro e mobile 390×844. A demonstração de edição
  inline não possui coluna de ações. Configurações apresentou um único h1 e
  ícones de navegação. Modal também conferido em 320×480, com 448 px de altura,
  margem de 16 px e rolagem interna para conteúdo de 578 px.
- Cadastro: e-mail duplicado manteve modal/dados e apresentou validação; cadastro
  válido exibiu a senha temporária. Edição do papel sintético persistiu pela API.
  Nenhum botão de envio de mensagem foi acionado.
- Activity Log: um chip ativo na mesma linha dos controles; aumento e redução
  por ponteiro, teclado e reload mantiveram Ações com 80 px e mesma borda.
  Resize fez zero requests. O navegador confirmou contexto inseguro e ausência
  de `crypto.randomUUID`; nenhum gerador de identificadores foi alterado.
- No domínio original, sem a porta descartável: conferidos `/roles/3/edit`,
  a ficha de cliente indicada e o modal em `/users`. Foram apenas observações e
  abertura/fechamento de telas, sem gravações de cadastros existentes.
- A tentativa de abrir uma permissão sintética fora do catálogo recebeu 403;
  o percurso de permissões foi validado com a capacidade funcional existente.
  Não houve mudança do contrato de autoridade. Erros de extensão do Chrome
  foram observados separadamente; não houve erro próprio da aplicação nas
  páginas conferidas.

Evidências locais: `storage/framework/testing/interface/browser.json`,
`lint-final.json`, `header-chrome-http.png`, `client-chrome-http.png`,
`modal-chrome-http.png` e `modal-mobile-chrome.png`.

## Consistência e limites

O primeiro fechamento da fixture recusou o vínculo automático do usuário ao
papel `user`, previsto em `User::booted()`. A guarda foi corrigida para exigir
exatamente o novo usuário sintético, troca de senha obrigatória e somente esse
vínculo, conservando as contagens dos demais recursos. Essa correção não mudou
o código da aplicação. A repetição no Chrome com o build final confirmou o ícone
de login em repouso e o cadastro. O fechamento aprovou exatamente um usuário
sintético novo, um vínculo automático com `user` e contagens dos demais recursos
iguais. Os dois runtimes descartáveis foram encerrados e limpos.

A sentinela detectou alteração de `.env` durante a sessão; não foi executada
edição desse arquivo por esta implementação e a autoria não foi confirmada.
A versão atual foi preservada. Os demais arquivos/configurações protegidos e
helpers gerados permaneceram idênticos. Não afirmar preservação byte a byte de
`.env` a partir desta execução.

A varredura de cabeçalhos/ações é estática no sistema inteiro; os fluxos de
navegador são os enumerados acima. Não foram executadas todas as operações de
todos os módulos, exportações/envios reais, autenticação 2FA completa, SSR de
produção ou integração realtime. Não houve commit, push ou implantação.

## Complemento — lint e fluxos restantes, 23/10/2026

Os 37 erros apontados foram corrigidos nas seis telas: imports sem uso, prop sem
consumidor do painel de e-mails, imports de tipos, chaves e espaçamento. Nenhuma
regra foi desabilitada. A passagem final recebeu 78 caminhos: zero erros, um
aviso conhecido TanStack/React Compiler e dois avisos de arquivos UI ignorados
pela configuração existente. A execução indiscriminada na raiz foi interrompida
ao alcançar worktrees e bundles gerados; não se afirma lint limpo desses
artefatos locais ou de outros checkouts.

O Chrome revelou que Clientes aceitava `invalid-email`. StoreClientRequest e
UpdateClientRequest agora validam o formato; o campo usa `type=email`. O teste
novo reproduziu a falha no cadastro e na edição, e passou depois da correção,
pela API e pela tela, sem escrita inválida. Opcionalidade e unicidade foram
preservadas. Também foi corrigido o plural de notificações, conferido no Chrome
com duas não lidas.

### Execução observada no Chrome

41 verificações adicionais aprovadas com sessão e banco descartáveis no domínio
HTTP solicitado. Mais 12 telas técnicas/de componentes abertas sem overflow no
desktop. Design System tem um segundo h1 intencional na amostra tipográfica.

| Fluxo | Resultado observado |
|---|---|
| Login e logout | Credenciais inválidas recusadas, login válido, logout e redirecionamento de visitante em rota protegida |
| 2FA | Código inválido recusado; login com TOTP e recuperação; conta comum recebe 403 na administração de papéis |
| Clientes | Criar, cancelar cadastro, editar, cancelar exclusão e excluir registro descartável; formato inválido bloqueado no campo e e-mail duplicado mantém formulário com erro do servidor |
| Usuários | Edição persistida; novo cadastro exibe senha temporária em HTTP sem randomUUID; modal de 320×480 com 448 px, margens de 16 px e scrollHeight de 582 px |
| Papéis | Cancelar modal, criar papel sem vínculos, visualizar na tabela e excluir registro descartável |
| Permissões | Catálogo desconhecido recusado; criar capacidade conhecida sem vínculo, editar nome, recusar troca de slug e excluir; alteração que afeta autoridade própria recebe 403 |
| Configurações | Nome salvo e confirmado após reload; confirmação da senha existente libera Segurança; alternância claro/escuro |
| Notificações | Leitura individual e em lote atualiza contador; plural correto com duas não lidas |
| Exportações | Cancelar modal; CSV, XLSX e TSV concluídos, cada um com 35 registros e link no histórico; job real executado de forma síncrona no runtime isolado |
| Ferramentas | Logs e painel de e-mail; Seeder Generator atualiza catálogo vazio autorizado; referência de anexos alterna abas e consulta cliente válido com estado vazio |

As 12 telas estão enumeradas em `browser-followup.json`; a passagem confere
navegação/composição, sem afirmar execução de cada demonstração ou envio.

Houve respostas 419 por conflito do cookie XSRF entre portas no mesmo domínio
durante testes concorrentes. As ações afetadas foram repetidas após reload, sem
desabilitar CSRF. Requests recusados inicialmente pela lista finita da fixture
foram liberados somente para os percursos necessários. Não houve mudança de
privilégios da aplicação.

O clique em Baixar não concluiu: o Chrome exibiu `ERR_BLOCKED_BY_CLIENT`. A tela
interna de downloads também não está disponível pela política da ferramenta.
Não foi contornado o bloqueio nem alterada a segurança do navegador. Download
ponta a ponta permanece pendente de liberação no Chrome; os cinco testes HTTP
de download passaram. A guarda final confirmou três arquivos privados
concluídos, exatamente um usuário novo com somente o papel padrão e nenhuma
alteração de contagem nos demais recursos ou de vínculos diretos de permissões.
O runtime/MySQL descartável encerrou com código 0 e foi limpo.

### Verificações finais e evidências

- 59 testes PHPUnit aprovados, 617 assertions: Clientes (28/444), apresentação,
  perfil, autenticação e 2FA (26/164), download de exportação (5/9).
- TypeScript, build final, Prettier dos oito frontend e Pint dos cinco PHP desta
  continuação aprovados. Build preservou variáveis públicas, omitiu regeneração
  Wayfinder e integrou assets locais sem excluir chunks anteriores.
- `git diff --check` sem erros; avisos de CRLF em documentação alheia preservados.
- Evidências em `storage/framework/testing/interface/`: `lint-followup.json`,
  `browser-followup.json`, `client-validation-followup.png`,
  `exports-followup.png`, `modal-followup-mobile.png`, `runtime-followup.json`.

Não foram executados envios reais, troca de credenciais pelo navegador, execução
de seeders/geração de arquivos de aplicação pelo painel, worker externo, SSR de
produção ou realtime externo. Esses limites não são cobertos por um build ou
pela navegação das telas. Alterações simultâneas alheias foram preservadas.
Nenhum commit, push ou deploy.
