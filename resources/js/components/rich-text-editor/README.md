# RichTextEditor

Editor controlado sobre Tiptap. `value` aceita HTML e `onChange` publica HTML persistível. Um valor externo diferente do último conteúdo serializado é aplicado mesmo com foco, sem emitir outro `onChange`. O eco do próprio callback não substitui o documento nem o histórico.

Um reset que repete exatamente o valor atual não é distinguível de um eco React. Por exemplo, um documento composto somente por preview local pode continuar com `value=""`; definir `""` novamente não sinaliza uma nova operação. Para descartar esse editor, o consumidor pode remontá-lo com uma chave de formulário. Não existe detecção implícita desse caso nem um novo contrato de reset.

## Controles acessíveis

A área editável é um textbox multilinha, com nome padrão `Editor de texto`. O consumidor pode fornecer `id`, `aria-label` ou `aria-labelledby`; o composer usa seu label visível como nome do editor rico. IDs internos dos dialogs são próprios de cada instância, gerados por `useId`, e não dependem da API UUID do navegador.

A toolbar tem nome e botões alcançáveis por Tab, com foco visível e `aria-pressed` somente nos controles que alternam formatação. Esses estados acompanham as transações e a seleção do Tiptap. A seleção de arquivo usa um botão nativo, ativável por Enter/Espaço. Os dialogs possuem descrições e labels associados. Read-only desabilita alterações e encerra dialogs abertos, preservando o descarte de imagens pendentes.

`useClipboard` mantém `[copiedText, copy]` e o resultado booleano: sucesso somente depois de `writeText` concluir. Uma nova tentativa limpa o sucesso anterior; resultados antigos ou posteriores à desmontagem não publicam estado/feedback. API ausente, inválida ou rejeitada produz orientação visível para cópia manual, sem incluir o conteúdo copiado ou a exceção. Não há fallback de permissão ou `execCommand`. O CodeBlock usa o mesmo hook e conserva o feedback de sucesso por aproximadamente dois segundos, com cancelamento do timer anterior.

As verificações dos controles inventariados ficam em `tests/Fixtures/Frontend/ui-accessibility-*`, com CSS real compilado, Clipboard sintético e respostas HTTP finitas. Não equivalem a uma auditoria WCAG de toda a aplicação nem à confirmação de 2FA; teclado real e protocolo observado são registrados no recibo UI-009.

## Imagens e descarte

`onImageUpload(file)` mantém sua assinatura e deve retornar a URL persistente da imagem. O editor não revoga URLs fornecidas pelo callback. O transporte e o cancelamento do pedido no servidor pertencem ao consumidor; descartar um resultado tardio não desfaz um upload já aceito.

Sem callback, arquivos podem aparecer como preview local. URLs `blob:` não entram no HTML emitido. O editor libera somente as URLs que ele próprio criou, ao substituir/cancelar uma prévia, remover a imagem, reiniciar o conteúdo ou desmontar. Um resultado de callback ou URL digitada que use `blob:` não ganha ownership nem se torna persistível.

Uploads pendentes pertencem à instância e à geração do documento/dialog. Reset externo, cancelamento, troca de arquivo, read-only e desmontagem impedem uma inserção tardia. Edições ordinárias mapeiam a posição pendente através das transações do documento. Undo/redo continuam avançando o histórico de texto, mas imagens locais já descartadas não reaparecem com uma URL revogada. Para preservá-las entre edições, use um callback de upload persistente.

## Comentários e tema

O composer da Timeline converte texto literal para HTML escapado e HTML para texto com fronteiras de blocos, listas e quebras. A conversão preserva texto e espaços internos, sem prometer markup idêntico após roundtrip. `trim` permanece na decisão de envio e no contrato já existente de submissão plain. O parsing intermediário usa conteúdo inerte de `template`, sem inserir HTML para interpretar seu texto.

Isso não substitui sanitização ou autorização: os serviços/resources da Timeline continuam responsáveis pelo contrato SEC-010. Imagens permanecem desabilitadas no composer. Os estilos locais usam os tokens completos de cor do projeto diretamente; `app.css` permanece inalterado.

Sem DOM, o cálculo de disponibilidade do botão conserva a estimativa SSR anterior por remoção de tags. Ela não interpreta HTML complexo, não converte conteúdo submetido e não é uma fronteira de segurança; a conversão interativa usa o template inerte no navegador.

As regressões de interação ficam em `tests/Fixtures/Frontend/ui-editor-lifecycle-*`, com componentes reais, React Compiler/StrictMode, PNG próprio e callbacks finitos. Não executam uploads nem endpoints reais; a execução observada e seus limites pertencem ao recibo de UI-007.
