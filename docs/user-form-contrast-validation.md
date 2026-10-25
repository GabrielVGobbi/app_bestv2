# Formulário de usuário — contraste, 23/10/2026

## Revisão após feedback sobre bordas

As bordas iniciais foram consideradas pesadas pelo usuário. No claro, agora
usam `muted-foreground/40`, sem sombra; labels permanecem em foreground.
A borda do tema escuro foi preservada. A modal recebeu `outline-hidden` para
evitar o contorno nativo duplo quando o contêiner recebe foco, preservando cores
forçadas. O foco dos campos continua com borda teal e ring de 2px.

Esta revisão foi conferida na rota real `http://www2.start_kit.com.br/users`,
com o servidor novamente disponível, Chrome desktop e tema claro: abertura da
modal, clique no título (foco do contêiner), campos em repouso e Tab até E-mail.
Nenhum cadastro foi enviado. Build, ESLint dos dois arquivos e teste de
renderização passaram. Evidência: `storage/framework/testing/user-form/report/borders-refined.png`.
Os índices de contraste abaixo pertencem à primeira versão, não à borda suavizada.
Mobile e escuro não foram repetidos nesta revisão.

## Primeira versão (histórico)

Nome e E-mail agora usam o mesmo FormField, com indicação de obrigatório,
rótulos 14px/600 em foreground e bordas mais definidas. O componente compartilhado
atende a modal da listagem e as páginas de criação e edição. A alteração não
muda validação, envio, valores iniciais ou componentes globais.

## Verificações

- Fixture de renderização `tests/Fixtures/Frontend/interface-presentation.mjs`:
  passou, incluindo associação dos labels, obrigatoriedade, valores e erros.
- ESLint do formulário, TypeScript e build da aplicação: passaram. Build local
  preservou chunks antigos e omitiu a regeneração de Wayfinder.
- Chrome, HTTP em `www2.start_kit.com.br:5189`, usando a fixture
  `tests/Fixtures/Frontend/user-form-browser.html` com os componentes reais:
  temas claro/escuro, desktop, 390×844, foco via Tab e mensagens de erro.
  Largura de scroll e viewport no mobile: 390px, sem overflow horizontal.
- Cores computadas: rótulos iguais nos dois campos, 14px e peso 600.
  Contraste calculado com as cores do navegador e fundo composto:
  labels claro 15,07:1, escuro 12,63:1; bordas em repouso claro 4,66:1,
  escuro 3,98:1. Foco manteve a cor/ring do Input.

Evidências: `storage/framework/testing/user-form/report/light.png`, `dark.png`
e `mobile-errors.png`. Para repetir a fixture, usar a configuração Vite
`tests/Fixtures/Frontend/datatable-browser.vite.ts` com input no HTML acima,
outDir `storage/framework/testing/user-form/build`, depois preview HTTP local.

## Limites

O domínio da aplicação na porta habitual recusou conexão durante a verificação;
nenhum processo httpd/nginx estava disponível na consulta local. A validação
visual foi feita na fixture isolada, não na rota integrada `/users`.
Não houve cadastro, acesso a banco, envio de mensagens ou alteração de serviços.

A tentativa inicial de servir a fixture pelo modo dev encontrou erro de export
`c` de `react_compiler-runtime` no cache de dependências. O preview compilado
funcionou; esse problema do modo dev não foi corrigido. Também foram observados
erros de extensões do Chrome, separados da interface.
