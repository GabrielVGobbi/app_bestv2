# Paridade visual Bionexis, 20/10/2026

> Histórico (20 a 21/10/2026). Tokens e composição atuais estão em [DESIGN.md](../../DESIGN.md); diagnóstico que motivou a troca em [visual-identity-audit.md](visual-identity-audit.md).

## Escopo autorizado

Copiar somente o visual. Preservar módulos, dados, rotas e autoridade do Start Kit.
O [DESIGN.md](../../DESIGN.md) é o contrato canônico; os adaptadores Claude/Cursor
e AGENTS apontam para ele.

## Diagnóstico

O dashboard do Start Kit continha os placeholders originais do Laravel. Os
primitivos de botão/card e vários tokens já eram iguais aos do Bionexis, mas o
shell tinha outra composição, textos de sidebar com opacidade reduzida, estado
ativo sem contraste e cápsula de módulos diferente. Não havia DESIGN.md na raiz.
A correção anterior de fontes locais foi preservada.

## Implementação

- Sidebar e navegação derivadas dos componentes visuais da referência, adaptadas
  ao contrato `type: section` e `modulesMenuPosition` deste projeto. Preservada a
  detecção de rota ancestral do Start Kit. Sem copiar serviços ou autorização.
- Sidebar 240/80px, logo sem cápsula extra, seções estáticas, links de alto
  contraste, usuário no rodapé e recolhimento no cabeçalho de 64px.
- Sidebar mobile com Sheet existente, Escape, foco contido/retornado e fechamento
  na navegação ou na transição para desktop.
- Abas de módulos sem cápsula externa; dropdown responsivo. Notificações, tema,
  conta e painel de ZIP continuam usando os providers existentes.
- Fundo molecular copiado para assets locais; temas e movimento reduzido.
- Dashboard compacto: saudação, filtro de módulos, resumo, quatro indicadores,
  faixa de atalhos e painéis em 7+5 colunas. Usa os recursos disponíveis em `nav`,
  sem números comerciais inventados; lista inicialmente seis recursos e expande.
- Documentação de navegação reconciliada com o contrato visual.

## Evidência e reprodução

Na raiz do projeto, executar `npm run build` antes do teste. O teste é opt-in:

```powershell
$env:DESIGN_PARITY_BROWSER_TESTS = '1'
$env:DESIGN_PARITY_NODE_BINARY = (Get-Command node).Source
# Definir DESIGN_PARITY_PLAYWRIGHT_MODULE como o caminho absoluto do index.mjs
# do Playwright já instalado na máquina; não alterar as dependências do produto.
php artisan test --compact tests/Unit/Frontend/DesignParityTest.php
```

O runner cria um servidor HTTP temporário em `127.0.0.1`, porta livre, e usa o
hostname local `www2.start_kit.com.br`. Serve os assets reais de `public/build`
e imagens locais. O HTML Inertia v3 e respostas de notificações são sintéticos.
Todos os outros destinos e WebSockets são bloqueados e registrados como falha;
nenhuma requisição é encaminhada ao Laravel ou a dados existentes. O processo,
servidor e contexto Chrome são fechados ao terminar.

Saída: `output/playwright/design-parity/report.json`, capturas desktop claro/escuro,
mobile e menu mobile. O PHPUnit verifica o resultado e os diagnósticos; o script
também confere a fonte efetivamente renderizada via CDP.

## Verificações e limites

O contrato cobre sidebar/header nas medidas da referência, ausência dos antigos
placeholders, Geist local, filtros, expansão da lista, recolhimento persistente,
atalhos acessíveis, tema escuro, conta, menu mobile/Escape/retorno de foco,
variantes header-logo/flat, navegação vazia, link ativo e ausência de overflow,
erros ou chamadas a serviços reais. Desktop 1884×1000, mobile 390×844 e variantes
em 1440×900.

Resultado final observado: **25 verificações Chrome aprovadas**, PHPUnit
**1 teste / 8 assertions**, zero erros de navegador, zero destinos bloqueados ou
mutações tentadas, 318 requisições de assets atendidas pelo servidor descartável.
Build da aplicação, TypeScript, ESLint focal, Prettier e Pint do teste passaram.
Capturas finais de desktop claro/escuro e mobile/menu foram inspecionadas.

Durante a validação, o estado de navegação vazia revelou um acesso a
`activeModule.items`; foi corrigido para uma lista vazia e mantido como regressão.
O primeiro harness usou markup Inertia v2 e depois resposta HTML inteiramente
interceptada, que disparava a proteção de rede local do Chrome no reload. O runner
final usa markup v3 e servidor HTTP descartável, sem desativar proteções do browser.

Não validado: sessão autenticada real do usuário, conteúdo privado do Bionexis,
comparação automática pixel a pixel entre dashboards autenticados ou E2E do
backend. A sessão de automação do Bionexis redireciona para login; a referência
visual foi a captura enviada e o código local que serve o domínio. Não se alega
cópia dos módulos de negócio, nem paridade de textos/dados entre os produtos.

Nenhuma migration, operação em banco, dependência, envio, commit ou implantação.
