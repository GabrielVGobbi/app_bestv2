# Carregamento de papéis no Windows local — 23/10/2026

## Diagnóstico

O registro Telescope `a30d2853-5709-4d21-9ccb-095a5123341f`, de `/roles`,
levou 4.531 ms. As quatro consultas somaram 28,34 ms, sem duplicatas.
O controller apenas autoriza a leitura e entrega `app/roles/index`; a tabela
consulta `/api/v1/roles` separadamente após a montagem.

O mesmo registro contém POST para `http://127.0.0.1:13714/render`, sem resposta,
e evento `Inertia\Ssr\SsrRenderFailed`. Não havia listener na porta 13714.
Havia bundle em `bootstrap/ssr/app.js`, nenhum `public/hot` nem cache de
configuração. O `.env` local omitia `INERTIA_SSR_ENABLED`; o fallback histórico
de `config/inertia.php` é `true`. Assim, a abertura HTML tentava um serviço SSR
ausente antes de retornar o fallback cliente. O tempo individual da chamada
HTTP não foi registrado pelo Telescope; não se atribui a ela numericamente
todo o tempo restante.

## Correção

Acrescentado `INERTIA_SSR_ENABLED=false` somente ao `.env` local, conforme o
[modo cliente suportado](stabilization/ops-optional-runtime.md). Não foi
necessário limpar cache de configuração. Nenhum código, dependência, asset,
consulta, regra de autorização ou padrão de outras instalações foi alterado.
O `.env.example` já declara essa opção como `false`.

Para usar SSR, é necessário habilitá-lo explicitamente e manter o serviço
correspondente disponível; possuir apenas o bundle não inicia o processo.

## Verificação e limites

- Configuração carregada pelo PHP com Dotenv e Application, sem iniciar
  providers ou banco: `inertia.ssr.enabled` resolveu para booleano `false`.
- Chrome autenticado no domínio HTTP original: três aberturas completas de
  `/roles`, com TTFB de 140, 131 e 256 ms; DOMContentLoaded de 343, 275 e 364 ms.
  Os tempos do Chrome incluem transporte e não equivalem ao tempo do Telescope.
- Primeira abertura após o ajuste: Telescope registrou `/roles` em 138 ms,
  API de papéis em 102 ms e notificações em 97 ms. O novo registro
  `a30d29cb-d03b-4e11-9838-d00c6b226830` não contém chamada HTTP nem evento SSR.
- Tabela exibiu quatro de quatro registros carregados. A verificação foi
  somente de leitura na interface existente; sem criar, editar ou excluir
  registros funcionais, sem refresh de banco ou chamadas a integrações reais.
- Logs consultados continham erros de uma extensão AdBlock, não da aplicação.
- Não houve build ou suíte PHPUnit: alteração restrita à configuração local.
  Não se validaram produção, concorrência/carga, todas as páginas ou serviço SSR
  ativo. A amostra curta não prova eliminação de todo pico futuro.
