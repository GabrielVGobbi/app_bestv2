# Certificados e cache de views no Windows local

O PHP do terminal e o PHP usado pelo Apache/Laragon podem ter versões e arquivos
`php.ini` diferentes. `php --ini` identifica apenas a configuração do terminal.
Confira também `PHPRC` e `FcgidWrapper` na configuração do Apache.

Para `cURL error 60: unable to get local issuer certificate`, configure
`curl.cainfo` e `openssl.cafile` no `php.ini` do PHP que atende o navegador,
apontando para o arquivo PEM de autoridades certificadoras confiáveis do Laragon.
Mantenha a validação TLS habilitada. Faça backup do INI e recarregue os processos
PHP depois da alteração; uma verificação no terminal não atualiza os processos CGI.

Para falhas de `rename()` em `storage/framework/views`, confira permissões,
atributo de somente leitura e processos que possam estar usando os arquivos.
Não altere `vendor` nem amplie permissões sem evidência. Em uma janela local sem
requisições concorrentes, recicle os processos PHP e execute, na raiz do projeto,
com o mesmo PHP do servidor web:

```powershell
php artisan view:cache --no-interaction
```

Esse comando limpa e recompila o cache de views. Não limpa o cache de dados nem
altera o banco. A recompilação recupera o cache, mas não prova a origem de um
bloqueio intermitente do Windows.

## Verificação em 23/10/2026

- Apache atendia com PHP 8.4.27; o terminal usava PHP 8.3.36.
- O PHP 8.4 estava sem `curl.cainfo` e `openssl.cafile`. Configurá-los para o
  arquivo PEM existente do Laragon eliminou o erro TLS em verificações sem
  credenciais nos endpoints de autenticação e API do Spotify.
- O arquivo de view citado na falha era da própria tela de exceções. Suas
  permissões permitiam escrita e ele não estava como somente leitura. O processo
  responsável pelo bloqueio original não foi identificado.
- Processos CGI reciclados e cache recompilado com PHP 8.4; nenhum `.tmp` restante.
- Chrome no mesmo domínio HTTP: diagnóstico, artistas, playlists e nova busca
  por `Jazz` carregaram sem as duas exceções, com o Spotify em modo real.
- Não houve mudança de código da aplicação, desativação de TLS ou refresh do banco.
  Não foi feita prova de concorrência que garanta ausência de novos bloqueios.

## Disputa de arquivos investigada e tratada

A reprodução isolada confirmou o mecanismo: no Windows, um processo PHP com
`fopen($view, 'rb')` impede o `rename()` que substitui aquela view, embora a ACL
permita escrita e o arquivo não seja somente leitura. O compilador Blade usava
`Filesystem::replace()`, que tentava o rename uma vez e deixava o temporário em
caso de falha. Uma recompilação enquanto outro processo lê o PHP compilado pode,
portanto, transformar o erro original em outra exceção na própria tela de erros.
Não há registro suficiente para atribuir o evento histórico ao PHP, antivírus ou
outro processo específico. A diferença entre versões do PHP, por si só, não prova
a causa do bloqueio.

`AppServiceProvider` registra `WindowsViewFilesystem` apenas no Windows. A
alteração de comportamento se limita a substituições diretamente no diretório
configurado em `view.compiled`; outros destinos usam o método original do Laravel.
Ela mantém a gravação completa em temporário e o rename atômico, repetindo apenas
falhas Windows de acesso/compartilhamento (`code: 5` ou `code: 32`) em um destino
existente e gravável. As tentativas têm intervalo de 25 ms e limite de 1 segundo.
O temporário é removido também na falha; não se apaga ou trunca a view anterior.
Bloqueios permanentes e permissões inválidas continuam produzindo erro explícito.
O tratamento preserva o error handler anterior e não altera `vendor`, ACLs ou TLS.

Regressões em `tests/Feature/Operations/ViewCompilationTest.php` exercitam um
leitor PHP real em outro processo, bloqueio permanente, somente leitura, criação e
atualização de views, arquivos fora do cache e a tela de exceções com cache frio e
quente. O cenário com leitor de 250 ms falhou com o mesmo `code: 5` antes da
correção; também foi reproduzido o resíduo `.tmp` antes da limpeza em `finally`.

Para repetir a validação visual isolada, inicie na raiz do projeto, com PHP Windows:

```powershell
php -S 127.0.0.1:8899 tests/Fixtures/view-compilation-router.php
```

Abra `/probe` e `/exception` nesse servidor. `/probe` só responde com sucesso após
superar um bloqueio real de 250 ms e confirmar que não há temporários. `/exception`
produz deliberadamente HTTP 500 com `Isolated exception renderer validation` para
validar a tela de erros; esse 500 é esperado. O router aceita somente GET local
nesses dois caminhos, usa `TestEnvironment` com SQLite em memória, integrações
falsas, diretórios descartáveis próprios e limpeza ao término de cada requisição.
Não executa o kernel HTTP nem consulta o banco existente. Encerre o servidor após
a validação.

Validação executada após a correção: 6 testes / 25 assertions no PHP 8.4.27;
no PHP 8.3.36, 28 testes / 334 assertions de views e status do diagnóstico,
mais a regressão de somente leitura adicionada depois. Pint passou nos quatro
arquivos PHP envolvidos. No Chrome, `/probe` superou o bloqueio nativo e
`/exception` mostrou a exceção deliberada sem a falha secundária; o diagnóstico
no domínio HTTP original continuou abrindo. Servidor e leitor próprios encerrados.
A suíte global não foi executada. A tentativa de PHPStan focal foi recusada pela
guarda do projeto antes do bootstrap por exigir o runner `tests/analyse.php`;
não se alega análise estática aprovada nem se alterou a guarda/baseline.
