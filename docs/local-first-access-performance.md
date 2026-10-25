# Primeiro acesso: Apache, assets e MySQL local — 23/10/2026

Complementa a [varredura anterior](local-page-performance-audit.md).

## O que foi comprovado

### Assets sem compressão

O Apache local não carregava `mod_filter` nem `mod_deflate`. Com o cache de rede
do Chrome desabilitado, os JS/CSS retornavam HTTP 200 sem `Content-Encoding` e
sem política de cache. O dashboard respondeu HTML em 61 ms e atingiu
DOMContentLoaded em 567 ms nessa amostra com o PHP já aquecido.

Os maiores arquivos tinham 477.236 bytes de JS, 300.431 bytes de outro chunk e
208.299 bytes de CSS. Eles eram transferidos integralmente, além dos demais
chunks e fontes. Isso é um custo real de primeira visita, independente do banco.

### Cache de tabelas do MySQL saturado

Durante a rodada seguinte, `/email-logs` reproduziu TTFB de 2265 ms. No Debugbar,
o request correspondente (`01M5MR4EQF17P6VA1WMEA0BA75.json`) registrou 1196 ms,
dos quais aproximadamente 1099 ms no `SELECT COUNT(*) FROM email_logs`. Os dois
relógios não são equivalentes; não se atribui toda a diferença ao COUNT.

O MySQL 8.0.30 local estava com `table_open_cache=256`, 249 tabelas abertas,
2484 aberturas/misses e 2181 expulsões por excesso de capacidade em 879 segundos
de uptime. O limite de arquivos era 7048; o buffer pool apresentava aproximadamente
99,4% de acertos e não foi aumentado. O excesso de expulsões comprova pressão no
cache de tabelas. É compatível com custo de reabertura, mas não prova que cada
milissegundo do COUNT ou do pico histórico tenha essa origem: não houve trace
individual de espera de disco/metadata locks.

## Ajustes aplicados

1. `public/.htaccess`: compressão negociada de JS/CSS por MIME, com proteção
   `IfModule` para servidores sem os módulos. HTML e JSON privados não entram
   nessa regra.
2. Assets públicos existentes com hash de oito caracteres do Vite, sob
   `/build/assets/`, recebem `Cache-Control: public, max-age=31536000, immutable`
   somente quando a resposta é HTTP 200. Manifest, respostas privadas e erros
   continuam com a política da aplicação. Um novo build muda o hash/URL.
3. Apache local: habilitados apenas os módulos instalados `filter_module` e
   `deflate_module` em
   `D:/laragon/bin/apache/httpd-2.4.54-win64-VS16/conf/httpd.conf`.
4. MySQL local: `table_open_cache` aumentado de 256 para 2048, em runtime e em
   `D:/laragon/bin/mysql/mysql-8.0.30-winx64/my.ini`, sem reiniciar o banco,
   refresh, alteração de schema/dados funcionais ou mudança de privilégios.
   O ajuste é do servidor MySQL local compartilhado, não exclusivo de uma conta
   nem do banco deste projeto.

O Apache foi recarregado após checagem de sintaxe. A instalação do Laragon usa
processos de console: `httpd -k restart` recusou com serviço `Apache2.4` inexistente.
Foi usado então o reinício dos processos locais identificados pela combinação
de executável e relação pai/filho, seguido de `Start-Process -WindowStyle Hidden`.
O usuário autorizou restabelecer o serviço para validar. Nenhum MySQL existente
foi encerrado.

## Validação real

Chrome autenticado no HTTP original, com cache de rede desabilitado, sem limpar
sessão, preferências ou armazenamento da conta:

- Primeiro dashboard após reciclar Apache/PHP: **TTFB 585 ms e DOMContentLoaded
  830 ms**. Esse é um processo PHP novo; não é um reboot do Windows ou do MySQL.
- 44 assets: **556.605 bytes transferidos para 1.507.477 bytes descomprimidos**.
  O JS principal passou a transferir 148.663 bytes e o CSS 31.593 bytes, incluindo
  o overhead contabilizado pelo navegador. Os mesmos arquivos/URLs do build
  foram comparados; não foi necessário recompilar o frontend.
- JS/CSS retornaram `Content-Encoding: gzip`, `Vary: Accept-Encoding` e cache
  imutável. A compressão segue a negociação do cliente.
- Após o ajuste do MySQL, uma rodada entre nove telas, mantendo o cache de rede
  desabilitado, teve os resultados abaixo. Os números não são P95 nem ensaio de
  carga; o banco e o PHP já haviam atendido requests anteriores.

| Tela | TTFB (ms) | DOMContentLoaded (ms) |
| --- | ---: | ---: |
| E-mails, abertura da rodada | 64 | 320 |
| Dashboard | 55 | 270 |
| Usuários | 111 | 379 |
| Papéis | 53 | 301 |
| Permissões | 59 | 309 |
| Clientes | 77 | 331 |
| Atividades | 77 | 328 |
| Exportações | 62 | 299 |
| Ferramentas | 68 | 334 |
| E-mails, retorno após as demais telas | 70 | 302 |

- Contadores do MySQL no início/fim dessa janela: tabelas abertas **255 → 353**,
  aberturas **2494 → 2592**, hits **40559 → 42714** e expulsões **2185 → 2185**.
  Assim, 98 novas aberturas foram retidas sem novas expulsões. Os contadores são
  globais e podem incluir tráfego concorrente de outros projetos locais.
- API autenticada de notificações HTTP 200, acesso direto HTTP 401 e asset
  inexistente com formato de hash HTTP 404 mantiveram `no-cache, private`, sem
  compressão de HTML/JSON pela nova regra.
- Cache normal restaurado no Chrome: 44 de 44 assets do dashboard vieram do
  cache, com `transferSize=0`. A alteração temporária de diagnóstico no navegador
  não ficou ativa.
- `httpd -t`: Syntax OK; módulos confirmados em `httpd -M`. Avisos de DocumentRoot
  ausente de outros projetos são anteriores e foram preservados.
- `my_print_defaults` e consulta read-only confirmaram `table_open_cache=2048`.
- `git diff --check`, sintaxe PHP e Pint do fixture de diagnóstico passaram.
  Não houve nova suíte de aplicação: os ajustes de comportamento ativo são de
  configuração e foram verificados no Apache/MySQL/Chrome reais.

## Perfil isolado e alternativas rejeitadas

`tests/Fixtures/cold-start-profile.php` permite inspecionar etapas de bootstrap
no PHP CLI ou CGI. Exige `COLD_START_PROFILE=1` e o contrato seguro
`APP_ENV=testing`, `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, `DB_URL` vazio.
Use `APP_RUNNING_IN_CONSOLE=false` para reproduzir o ciclo HTTP. Aceita
`COLD_START_TELESCOPE=1` para criar somente as tabelas de Telescope no SQLite em
memória. O perfil isola storage/cache/integrações pelo `TestEnvironment` existente,
valida a página de login e verifica ausência de HTTP externo, Mail e filas.

Exemplo, a partir da raiz do projeto e com essas variáveis de teste explícitas:

```powershell
php-cgi -q -f tests/Fixtures/cold-start-profile.php
```

As execuções finais CGI/PHP 8.4.27 retornaram HTTP 200: resposta em 190 ms com
Telescope desligado e 127 ms com ele ligado no perfil. Essas amostras reutilizam
bytecode compartilhado; a diferença não é comparação causal entre os modos.
O perfil recusa ausência de opt-in e ambiente `local` antes do bootstrap.
Nenhuma chamada de `opcache_reset()` ou flush de banco foi usada.

O OPcache web já estava habilitado, sem reinícios por OOM e com memória geral
livre. A área de strings internadas chegou ao limite de 8 MiB na inspeção inicial,
mas isso não foi comprovado como causa do pico e nenhum ajuste de PHP INI foi
mantido. Um experimento separado de `opcache.file_cache` demorou 5,8 s na primeira
população e 583/406 ms nas seguintes; foi rejeitado como solução para o primeiro
acesso. Esse diretório não é usado pela configuração ativa.

O fixture inicialmente verificava o nome do componente por texto literal, o que
falhou com a barra escapada pelo JSON. A validação foi corrigida para decodificar
o JSON do script Inertia; passou depois. Uma execução com caminho relativo também
falhou ao localizar o fixture; as verificações finais usaram caminho absoluto.
Nada disso é contado como resultado de performance válido.

## Recuperação e limites

Backups locais preservados antes das alterações:

- `httpd.conf.before-start-kit-compression-20261023`, ao lado do `httpd.conf`.
- `my.ini.before-start-kit-table-cache-20261023`, ao lado do `my.ini`.

Para reverter, restaure os arquivos de backup e recarregue o Apache. O valor
runtime do MySQL pode ser restaurado com `SET GLOBAL table_open_cache=256` em
uma sessão administrativa local; a mudança do arquivo vale no próximo início.
Não aplique esses valores a outro servidor sem medir sua capacidade.

Não se certificam ausência de todo pico futuro, LCP/INP, carga, produção, boot do
Windows/MySQL, disco totalmente frio, API Spotify real ou configurações Nginx/IIS.
O pico histórico antes do roteamento de 1709 ms não foi atribuído com certeza a
um único mecanismo. A correção cobre os dois custos comprovados nesta rodada;
os relógios de TTFB, DOMContentLoaded, Debugbar e SQL são distintos.

A revisão automática rejeitou a remoção recursiva do cache experimental com a
razão `blocked by policy`. Restaram 945 arquivos `.bin` (23.958.392 bytes) sob
`C:/Users/GABRIELGOBBI/AppData/Local/Temp/start-kit-tests-bed4f477228a97599eec7faf8853d7fc`.
Não há processo próprio usando esse cache, ele não está habilitado na aplicação,
e não se tentou contornar o bloqueio. Requer limpeza manual. O resíduo MySQL
registrado no relatório anterior não foi tocado neste lote.

Referências primárias: [compressão Apache](https://httpd.apache.org/docs/2.4/mod/mod_deflate.html),
[headers condicionais](https://httpd.apache.org/docs/2.4/mod/mod_headers.html),
[cache de tabelas MySQL 8](https://dev.mysql.com/doc/refman/8.0/en/table-cache.html)
e [configuração de OPcache](https://www.php.net/manual/en/opcache.configuration.php).
