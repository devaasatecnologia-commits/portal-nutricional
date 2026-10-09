# Handoff de continuidade - revisao do modulo Frota

**Verificado em:** 2026-10-07
**Repositorio:** `devaasatecnologia-commits/portal-nutricional`
**Escopo:** revisao local de codigo, documentacao e telas acessiveis no navegador
**Nao confirma:** deploy, schema ou servicos de producao

## Regras para retomar

- Consulte [ARCHITECTURE.md](../ARCHITECTURE.md) e
  [VALHALLA_SETUP.md](../VALHALLA_SETUP.md) para o contexto tecnico desta area.
- Verifique branch, `git status --short` e diff. O checkout desta revisao tinha
  alteracoes preexistentes fora e dentro de Frota; nao use `git add .` nem
  reverta trabalho alheio.
- Tokens anteriores sao snapshots historicos, nao backlog confirmado. Confirme
  cada item no codigo e com os responsaveis antes de executar tarefas de
  producao, ERP, banco, Cobli ou notificacoes.
- Nunca inclua segredos, dados pessoais, cookies, dumps ou valores de producao
  neste arquivo.

## Estado confirmado no checkout

- A otimizacao de embarques usa matriz de custo rodoviario Valhalla com perfil
  de caminhao, ciclo de saida/retorno ao CD e refinamento da ordem. Nao deve
  haver fallback silencioso para distancia em linha reta. Valide a chave,
  disponibilidade e roteamento real em homologacao.
- As rotas da API Frota aplicam middleware de gestao nos endpoints
  administrativos; operacoes do motorista consultam a identidade do JWT e
  validam a atribuicao de embarque/entrega. Isso foi revisado estaticamente,
  nao por teste de penetracao.
- O app offline persiste a fila em IndexedDB, com fallback para localStorage,
  e oferece sincronizacao manual/automatica. Rascunhos de checkout e fotos ainda
  usam armazenamento local do navegador.
- O endpoint publico `/v1/frota/health` esta implementado. Ele informa estado
  interno de verificacoes sem detalhes de excecao; o cache local de Cobli gera
  log se nao puder ser serializado ou gravado. A existencia do endpoint nao
  comprova configuracao de monitor externo.
- `posicoesFrota()` usa cache de 30 segundos por veiculo. Quando ha cache miss,
  consulta os dispositivos sequencialmente; o paralelismo `curl_multi` dos
  tokens antigos nao esta implementado.
- O modo offline mostra estado de conexao e aviso, e o checkout do motorista
  contempla o tipo `aberto`. A tela de Acerto lista problemas, mas nao foi
  confirmado um destaque visual especifico para `aberto`.
- Na revisao visual local, Embarques, Gestao de Cargas, Acerto, Motorista
  Offline e Cadastro de Frota abriram. O contador de problemas de Gestao de
  Cargas foi corrigido para usar `total_problemas`; a referencia global
  inexistente de otimizacao em Embarques foi removida. Nenhuma acao de escrita
  de embarque foi executada durante essa validacao.
- A pagina inicial do Portal exibiu "Nenhum modulo encontrado" ao retornar,
  embora as URLs diretas das telas de Frota funcionassem. Esse comportamento
  precisa ser reproduzido com a conta/permissoes corretas antes de ser
  atribuido ao modulo.
- O service worker no checkout usa `frota-motorista-v49`; isso nao indica a
  versao instalada em homologacao ou producao.

## Pendencias historicas ainda aplicaveis ou a confirmar

Os itens abaixo nao devem ser descritos como concluidos apenas porque aparecem
em tokens de setembro:

1. **Monitoramento externo:** configurar/verificar UptimeRobot ou equivalente e
   Sentry com os responsaveis; nenhum estado de conta externa foi validado.
2. **Cobli/performance:** decidir se cache adicional para `roadmap()` e
   paralelismo de consultas tem beneficio e limites adequados. Medir latencia e
   comportamento parcial antes de implementar.
3. **Rate limit por motorista:** o middleware atual usa identificador de
   usuario/token/IP e limites gerais; nao ha politica especifica por motorista
   e operacao. Definir limites aprovados antes de alterar comportamento.
4. **Gestao visual:** adicionar ou descartar destaque explicito para problemas
   `aberto` na tela de Acerto; a exibicao atual de problemas nao foi validada
   como suficiente para o processo do gestor.
5. **Offline e armazenamento:** avaliar migracao dos rascunhos/fotos para
   IndexedDB com estrategia de quota, limpeza e recuperacao; a fila ja usa
   IndexedDB, mas isso nao cobre os rascunhos/fotos.
6. **Telemetria e retencao:** padronizacao de logs JSON, politica aprovada de
   retencao de posicoes Cobli e limpeza de logs/debug precisam de inventario e
   validacao operacional. Nao executar delecoes sem politica e aprovacao.
7. **Testes e schema:** nao foi identificada suite propria de integracao nem
   migration runner dedicado na raiz no momento desta revisao. Planejar testes
   de acerto, concorrencia, sincronizacao offline e integracoes; confirmar schema
   externo separadamente.
8. **Melhorias opcionais dos tokens:** card de veiculos em risco, SOS,
   notificacoes automaticas por WhatsApp, Web Push, TTS e skeletons completos
   sao propostas futuras, nao requisitos implementados. SOS/notificacoes/push
   dependem de aprovacao funcional, privacidade, fornecedor e operacao.
9. **Validacao do Portal:** reproduzir o estado sem modulos com as permissoes
   corretas e confirmar a navegacao pelo entrypoint.

## Validacoes executadas nesta revisao

- Navegacao local nas telas Frota e conferencias visuais sem persistir alteracoes.
- `php -l` nos controllers, services, rotas e paginas PHP Frota alterados.
- `node --check` nos JavaScript ajustados da revisao visual.
- `git diff --check`.
- PHPUnit nao estava instalado em `vendor/bin`; nao foi possivel executar suite
  automatizada. Nao foi identificada configuracao/test suite Frota neste checkout.

Antes de publicar, repetir lint PHP/JS sobre o conjunto commitado e testes
integrados em homologacao. As verificacoes locais nao confirmam schema,
credenciais, monitoramento, configuracao Valhalla/Cobli ou estado de deploy.

## Atualizacao: Auditoria de Carregamento

- A aba de carregamento da Auditoria usa `/v1/auditoria/carregamentos`, filtrado
  pelas datas escolhidas e incluindo embarques com cargas finalizadas. Isso
  substitui a consulta operacional limitada aos embarques ainda abertos e
  elimina chamadas de resumo repetidas por embarque.
- O detalhe retorna cada linha de `carregamento_subidas` separadamente, com
  usuario, data/hora, quantidade, doca, numero da subida e caminho da foto.
  Registros antigos sem subida usam o registro agregado de carregamento.
- Os JOINs dos operadores foram corrigidos para `usuario.idusuario`, que e o
  identificador persistido pelos controllers. A pagina local respondeu 200:
  no periodo testado havia 25 embarques; o detalhe de um deles retornou 25
  eventos de carga com operador, horario, foto e `id_subida`. A tela apresentou
  tambem eventos de separacao. Isso nao valida producao.
- Arquivos desta correcao: `portal/assets/js/auditoria.js`,
  `v1/routes/api.php`, `v1/src/Controllers/AuditoriaController.php`,
  `v1/src/Controllers/CarregamentoController.php` e
  `docs/ARCHITECTURE.md`. `CarregamentoController.php` ja tinha alteracoes
  locais extensas antes desta tarefa; revisar seu diff completo antes de
  qualquer staging.
- Sintaxe PHP/JS validada. Nao houve commit nem deploy desta correcao.
- A decisao funcional foi tomada: todos os estornos exigem motivo. A solucao
  local abaixo preserva subidas/fotos e grava snapshots de estorno; depende da
  migracao PostgreSQL antes de publicar a API.

## Atualizacao: dossie auditavel de embarque

- A Auditoria recebeu `/v1/auditoria/dossie/{idembarque}` para comparar previsto,
  separado e carregado por item; lista subidas, alocacao por pedido/item,
  operador, horario, doca, evidencia e estornos com motivo. A tela tem filtros
  para acima do separado, parcial/pendente e sem foto, ampliacao de foto e
  exportacao CSV do dossie.
- Novas confirmacoes gravam a alocacao de cada subida por pedido/item de pedido.
  O upload de evidencias exige `id_subida` e grava a ligacao direta da foto.
  Os endpoints de estorno agora registram motivo, operador, quantidade,
  timestamp e snapshot das alocacoes, reduzem a projecao operacional na mesma
  transacao e preservam as linhas de subida e fotos. Estornos individuais,
  totais e desfazer quantidade exigem motivo; o cancelamento automatico por
  falta de foto usa o motivo explicito "Foto nao registrada apos confirmacao
  da subida".
- Foi criada `docs/carregamento_auditoria_migration.sql`. Ela deve ser revisada,
  aplicada com backup em homologacao e aprovada antes do deploy. Nao foi
  executada neste checkout. A aplicacao nova exige as tabelas/colunas da
  migracao; nao publicar a API antes do schema.
- O backfill liga fotos antigas somente quando ha correspondencia exata de
  embarque, item e caminho. Alocacoes por pedido de subidas antigas nao sao
  inventadas: a tela as identifica como legadas sem rastreio exato. Estornos
  anteriores a migracao nao podem ser recuperados.
- Arquivos locais relacionados: `docs/carregamento_auditoria_migration.sql`,
  `docs/ARCHITECTURE.md`, `portal/assets/js/auditoria.js`,
  `portal/assets/js/carregamento.js`, `v1/routes/api.php`,
  `v1/src/Controllers/AuditoriaController.php`,
  `v1/src/Controllers/CarregamentoController.php` e
  `v1/src/Controllers/CarregamentoEstornoController.php`. O diff de
  `CarregamentoController.php` ja era extenso antes desta tarefa; revisar a
  alteracao completa antes de staging.
- Validacao estatica local: `php -l` passou nos tres controllers envolvidos e
  nas rotas; `node --check` passou nos dois JavaScripts; `git diff --check`
  passou nos arquivos focados. O diff-check global continua apontando whitespace
  em alteracoes preexistentes (incluindo `index.php` e trechos antigos do
  controller grande); nao foram normalizados.
- Em 2026-10-07, apos solicitacao para conferir a aplicacao manual da migracao,
  foi feita consulta somente de leitura pela conexao do proprio portal: banco
  `ema`, schema `public`. Na primeira verificacao, `carregamento_estornos` e
  `carregamento_subida_alocacoes` ainda nao existiam, embora as alteracoes em
  `carregamento_fotos` e parte dos triggers ja estivessem presentes.
- O endpoint do dossie passou a verificar as dependencias antes de consultar e
  responder HTTP 503 com `SCHEMA_AUDITORIA_INCOMPLETO` e a lista ausente. A UI
  agora exibe a mensagem retornada em vez do erro generico.

## Atualizacao: frescor das posicoes Cobli

- A revisao ao vivo autorizada em 2026-10-07 confirmou 18 vinculos ativos e
  conexao Cobli bem-sucedida. As consultas por posicao retornaram coordenadas
  para todos os veiculos observados; a idade variou durante a verificacao.
  Na ultima leitura da tela foram 3 posicoes com ate 5 minutos e 15 atrasadas,
  sem falhas de consulta nem vinculos sem coordenadas. Isso e um retrato da
  verificacao, nao garantia de disponibilidade continua.
- O mapa agora exibe hora da consulta separadamente da hora da posicao,
  frescor/idade e origem cache/Cobli no popup, destaca posicoes atrasadas e
  resume recentes, atrasadas e sem localizacao. A API inclui cada vinculo,
  inclusive sem posicao ou com erro, valida limites geograficos sem rejeitar
  latitude/longitude zero e informa status e idade calculados no servidor.
  Cinco minutos e um limite inicial de exibicao; confirmar contra a frequencia
  operacional de envio Cobli antes de tratar como SLA.
- A consulta ao endpoint de frota agora busca o cache recente de todos os
  veiculos em uma consulta SQL em lote, evitando uma leitura sequencial por
  veiculo. As consultas ainda podem levar varios segundos quando ha cache miss,
  pois as chamadas externas Cobli permanecem sequenciais; nao foi alterada a
  concorrencia sem conhecer os limites/quota da conta.
- O webhook Cobli agora aceita coordenadas zero, valida coordenadas, usa apenas
  horario de origem reconhecido (timestamp/time/occurredAt/occurred_at/
  eventTime/event_time/date) e ignora explicitamente posicao sem horario
  confiavel, em vez de carimbar o horario local como se fosse do GPS. Falhas de
  persistencia retornam HTTP 500; evento nao suportado ou incompleto e
  confirmado como ignorado. O log nao inclui placa nem trecho da assinatura.
- A leitura GET de `/v1/frota/cobli/frota/posicoes` tambem grava snapshots de
  posicao quando ha horario de origem e gera logs via integracao Cobli, conforme
  comportamento existente. Portanto, consultas de verificacao podem alterar
  tabelas de historico/log; nao foram alterados embarques ou outras operacoes
  logisticas.
- A medicao do endpoint apos a consulta em lote variou com os cache misses:
  HTTP 200 em 6,7 s com 7 misses e em 9,2 s com 11 misses. Nao ha benchmark
  controlado de cache-only; nao aumentar concorrencia antes de confirmar quota
  Cobli. O painel Cobli reportou 240 chamadas, 100% de sucesso, media 847 ms e
  maximo 2.824 ms na ultima hora; o ultimo erro historico pode ser anterior a
  essa janela.
- Arquivos desta etapa: `v1/src/Controllers/Frota/CobliController.php`,
  `portal/modules/frota/assets/gestao-cargas.js` e
  `portal/modules/frota/assets/gestao-cargas.css`, `docs/VALHALLA_SETUP.md`.
  Sintaxe PHP/JS validada, testes manuais de limites geograficos/timestamps
  passaram, endpoint Cobli respondeu HTTP 200 e tela local conferida. Nenhuma
  migration, commit ou deploy.
- Proximos passos: confirmar frequencia de telemetria e limite de frescor com
  a operacao; investigar as posicoes atrasadas por dispositivo sem divulgar
  coordenadas/dados pessoais; avaliar monitoramento e paralelismo somente apos
  confirmar limites Cobli. Valhalla continua sem instancia real validada, e
  nenhuma extracao/download regional foi iniciada.
- O webhook permanece pendente no checklist Cobli: o secret nao esta configurado
  e nao foi confirmado cadastro do endpoint no painel Cobli. O computador local
  nao tem Docker nem `VALHALLA_URL`; a fonte Geofabrik consultada oferece PBF
  nacional de ~1,9 GB, nao o recorte desejado. Valhalla depende de servidor,
  recurso, recorte, secret e configuracao externa antes de validacao fim a fim.
- O cliente `psql` nao esta disponivel. A verificacao do schema ocorreu usando
  PDO de leitura pela configuracao local; nenhuma migracao foi executada.
- Atualizacao posterior (2026-10-07 13:58): o usuario aplicou a migracao
  manualmente no banco. A conexao do portal confirmou em leitura `ema.public`,
  ambas as tabelas novas, `carregamento_fotos.id_subida`, a FK e os cinco
  triggers de protecao esperados. Consultas com a estrutura do dossie executaram
  sem erro para o embarque 9293: 23 itens no resumo, 25 subidas e zero estornos.
  Os avisos de `DROP TRIGGER IF EXISTS ... ignorando` foram os esperados na
  primeira execucao: o trigger ainda nao existia para apagar; os `CREATE
  TRIGGER` posteriores foram confirmados presentes no banco.
- Em 2026-10-07 14:00, depois de o usuario aplicar o restante da migracao e
  renovar a sessao, o portal confirmou todas as tabelas, coluna, FK e cinco
  triggers. Consultas ao banco para o embarque 9293 retornaram 23 itens, 25
  subidas e zero estornos.
- A tela autenticada da Auditoria foi testada no embarque 9293: endpoint HTTP
  200; dossie abriu sem mensagem de erro; totais previsto/separado/carregado
  foram 4757/4757/4757, com zero itens acima do separado; 23 itens e 25
  evidencias/fotos renderizadas. Os filtros foram exercitados (0 divergencias,
  0 itens sem foto e retorno dos 23 itens ao selecionar Todos); exportador CSV
  esta disponivel. Nenhuma operacao de escrita foi realizada.
- Lint PHP, `node --check` e `git diff --check` focal passaram depois da
  verificacao visual. Homologacao da Auditoria de leitura concluiu; estorno
  real permanece sem teste de escrita para nao alterar dados operacionais.
- O checkout tem outras alteracoes preexistentes e arquivos nao rastreados
  fora deste escopo. Nao usar `git add .`, nao limpar dados alheios e revisar o
  diff completo de `CarregamentoController.php` antes de staging.
- Nao fazer commit, deploy nem FTP sem aprovacao.
- Correcao em andamento apos o usuario relatar falha no estorno: o log PHP
  identificou que a exclusao de `pedido_item_carregamento` acionava a FK em
  cascata e tentava apagar `carregamento_subidas`, bloqueada pelo trigger de
  trilha imutavel. O erro ocorria dentro de transacao e o controlador faz
  rollback; nenhuma tentativa bem-sucedida foi executada nesta verificacao.
- `CarregamentoEstornoController::gravarEstorno()` agora preserva a linha
  operacional com `qt_carregada = 0` e limpa somente seu apontador de foto
  atual quando o saldo zera; fotos e subidas historicas permanecem. A
  finalizacao e os totais da Auditoria agora ignoram linhas com quantidade
  zerada. Os endpoints ativos de estorno usam esse controlador; metodos
  legados privados em `CarregamentoController` nao estao roteados.
- Lint PHP dos tres controladores e `node --check` passaram. `git diff --check`
  apontou espacos finais preexistentes em `CarregamentoController.php`; nao
  foram removidos para evitar alterar trechos fora desta correcao. A validacao
  fim a fim ainda requer um embarque de homologacao ou autorizacao explicita
  para efetuar uma escrita operacional.
- A pagina autenticada de Carregamento permaneceu acessivel e a requisicao
  `GET /carregamento/embarques` respondeu HTTP 200 apos a alteracao; nenhuma
  chamada de estorno foi enviada.
- Retomada da revisao Frota (2026-10-07 14:57): no Acerto, os registros de
  problema ja traziam `status_problema`, mas a interface nao o apresentava.
  `acerto-embarque.js` e `acerto-embarque.css` agora destacam separadamente
  problemas pendentes como "Em aberto", os em analise, resolvidos e cancelados,
  incluindo contagens de abertos/em analise no bloco de problemas. `node
  --check` e `git diff --check` focal passaram. Na tela autenticada, a lista
  carregou 8 embarques e o detalhe de um acerto com problemas mostrou os
  badges "Em aberto" sem erros de console relacionados; nenhuma escrita foi
  realizada. A instalacao real do Valhalla permanece bloqueada por servidor,
  recursos e endpoint nao configurados; nao houve download de mapa.
- Na fila offline de `motorista-offline.js`, as mutacoes agora sao serializadas
  e aplicadas ao conteudo atual da fila, evitando que duas acoes simultaneas
  sobrescrevam uma a outra. A sincronizacao preserva acoes novas adicionadas
  enquanto a rede estava processando o snapshot antigo; todos os pontos de
  enfileiramento aguardam confirmacao da persistencia. `node --check` e
  `git diff --check` focal passaram. A verificacao nao simulou uma acao offline
  real nem escreveu na fila do navegador; validar esse fluxo em homologacao
  continua pendente.
- Em 2026-10-08, o usuario reportou violacao de unicidade em
  `carregamento_subida_alocacoes (id_subida, iditempedido)` ao registrar uma
  subida. A leitura ao banco confirmou que `carregamento_subidas.id = 334` nao
  existe, consistente com rollback da tentativa. A consulta ao catalogo do
  PostgreSQL confirmou que a chave primaria de `pedido_item` e composta por
  `(idpedido, iditempedido)`, portanto `iditempedido` sozinho nao e
  identificador global. A restricao da tabela de alocacoes estava incompleta.
  `confirmarItem()` agora agrega a separacao por pedido/item/embarque para nao
  repetir a mesma linha em joins; a migration foi corrigida para usar a chave
  composta `(id_subida, idpedido, iditempedido)` e substituir a restricao antiga.
  O calculo de estornos agora compara ambas as partes da chave do item. A falha
  detalhada passa a ser registrada no log PHP; a resposta HTTP nao expoe a
  excecao SQL. `php -l` passou e a consulta corrigida foi executada em
  transacao somente de leitura com parametros vazios. A varredura global de
  duplicidades foi interrompida por custo de leitura. Nenhum INSERT, estorno ou
  mudanca operacional foi realizado. A nova migration precisa ser aplicada ao
  banco antes de repetir o carregamento; validar com embarque de homologacao
  antes de considerar o fluxo fim a fim.
- Em 2026-10-09 o usuario aplicou a migration (restricao composta) e a subida
  voltou a funcionar. O erro generico "Nao foi possivel registrar a subida do
  item." (`FALHA_REGISTRO_CARREGAMENTO`) ocorreu enquanto a restricao antiga
  ainda existia; o log PHP (`[Carregamento] Falha ao confirmar item`) confirmou
  a mesma causa.
- Filial 6: `CarregamentoController` (listagem, `confirmarItem`, finalizacao) e
  `SeparacaoController` (listagem e duas travas) usam
  `(gerou_nf = 'S' OR idfilial = 6)`; a filial 1 continua exigindo NF. Nao
  alterado: `Frota/ImportadorController.php` (linha ~1909), que mantem o filtro
  original. Validado apenas com `php -l`; sem teste com embarque real da filial 6.
- Coletor de codigo de barras: `separacao.js` ganhou `focarBipe()` e ambos os
  scripts (`separacao.js`, `carregamento.js`) devolvem o foco a
  `#barcode-input` quando o ultimo `.swal2-container` e removido (MutationObserver
  com atraso de 80 ms). O campo segue `readonly` para nao abrir o teclado do
  celular. Validado com `node --check`; falta teste no celular com o coletor.
- Commit `bf21456` reune este trabalho. Ficaram fora, por serem sensiveis ou
  nao revisados: `phpinfo.php`, `docs/SECRETS_ACCESS.md`, `.env.example` e os
  tres `GUIA_ENTREGA_CEO_PROGRAMADOR.*`. Sem push.
- Pendencias de continuidade: testar filial 6 e foco do coletor em homologacao;
  retomar Valhalla (aguardando dados do servidor Linux/Docker); monitoramento
  externo, rate limit por motorista, consultas Cobli paralelas, rascunhos/fotos
  offline em IndexedDB, testes de integracao, retencao de posicoes Cobli e o
  "Nenhum modulo encontrado" no Portal.

## Publicacao

### Atualizacao 2026-10-09 (tarde): sessao, inatividade e avisos remotos

- Carregamento: avisos de separacao remota (`detectarAlteracaoRemota`) usavam
  `Swal.fire`, que substitui o modal aberto (ex.: quantidade/foto da subida).
  Agora usam `avisoRemoto()` (toast proprio, nao bloqueante) e a sincronizacao
  recheca `isProcessing`/`Swal.isVisible()` depois do `await`, adiando a
  aplicacao para a proxima rodada de 5 s.
- Token JWT: 2 h -> 4 h em `AuthController` (login e `logoutAll`).
- Inatividade: `portal/assets/js/inatividade.js`, carregado por
  `estrutura/header.php`. Apos 20 min sem eventos de uso, overlay proprio
  (nao Swal) exige motivo (min. 5 caracteres); estado em localStorage e
  sincronizado entre abas; login limpa o estado. Exclui `login.php` e
  `motorista-offline` (motorista em rota). Motivo enviado a
  `POST /v1/auth/inatividade` (`AuthController::registrarInatividade`), que grava
  em `portal_inatividade` se existir (migration
  `docs/portal_inatividade_migration.sql`, a aplicar manualmente) ou em
  `error_log` ate la. `focarBipe()` nao age enquanto o overlay existe.
- Testado no navegador local: bloqueio, validacao, liberacao e HTTP 200 do
  endpoint. Nao testado: expiracao real do token, varias abas, coletor no celular.
- Observacao: o token continua sem renovacao automatica; ao expirar (4 h) o
  usuario volta ao login.

Nao publicar por FTP sem confirmar o commit e as dependencias do ambiente.
Enviar somente os arquivos efetivamente commitados que correspondam ao deploy;
nao enviar `.env`, artefatos, arquivos temporarios, dumps nem alteracoes alheias.

### Auditoria de atividade por usuário (2026-10-09, tarde)
- Nova aba **USUÁRIOS** em `portal/modules/auditoria.php` / `auditoria.js`: totais por usuário (separação, subidas, estornos, inatividade, última atividade) + modal com timeline filtrável (Separação, Carregamento, Estorno, Inatividade, Acesso).
- API: `GET /v1/auditoria/usuarios` e `GET /v1/auditoria/usuarios/{idusuario}` (`AuditoriaController`, período `inicio`/`fim`, máx. 500 eventos, módulo `auditoria`).
- Motivo de inatividade: `POST /v1/auth/inatividade` grava `modulo` (derivado da tela) em `portal_inatividade`.
- **Pendente (usuário):** aplicar `docs/portal_inatividade_migration.sql`. Até lá a aba mostra aviso e os motivos ficam só no `error_log`.
- Próximo: estender a outros módulos (depósito etc.) enviando a atividade para a mesma trilha; avaliar renovação automática do token.
## Atualização: contador de inatividade, item a mais, bipe global e Retorno por embarque
- inatividade.js: aviso regressivo nos últimos 60 s. Token JWT = 4 h.
- Item a mais na subida: migration `docs/carregamento_subida_extras_migration.sql` (APLICAR); `CarregamentoController::uploadFoto`, `getSubidas`; exibido na Auditoria (badge/linha "a mais").
- Bipe global: `portal/assets/js/bipe-foco.js` (header.php) mantém `#barcode-input` focado; Desembarque/Retorno sem `readonly`.
- Retorno por embarque: `GET /v1/retorno-embarque/destinos` e `/v1/retorno-embarque/{id}` (RetornoController + api.php). Painel em retorno.php/retorno.js: carregado (+extras) x devolvido no acerto x destinado x pendente; botão DESTINAR usa `v1/retorno/movimentar` com observação `EMB #id`. Destino 16688 (TROCAS FORNECEDOR) adicionado. Pedidos abertos hoje: 10117→155045, 16595→152056, 16596→152057, 16688→155024.
- PENDENTE/ATENÇÃO: as rotas `/v1/retorno/*` existem só em produção (não estão no api.php local); validar o painel em produção. Comparativo só mostra "devolvido" após acerto com `quantidade_devolvida`.

## Revisão do módulo Retorno (2026-10-09)
- Segurança RetornoController: upload valida mime real e gera extensão/nome aleatório (antes usava extensão do cliente); destino/origem restritos a 10117/16595/16596/16688; idusuario vem do JWT; FOR UPDATE nas linhas de pedido_item; validação de tipo/AJUSTE.
- `docs/retorno_carga_embarque_migration.sql` (APLICAR): coluna `retorno_carga.idembarque`; comparativo usa a coluna (fallback: observação `EMB #id`).
- Próximos: inatividade 10 min + códigos de ineficiência (aguardando lista), Desembarque x Depósito (endereçamento -> Separação), módulo Manutenção de frota (Cobli).

## Retorno - correção do bipe (causa raiz)
- config.js define apiFetch(endpoint, options); retorno.js usava (acao, metodo, body) -> POST virava GET (405). retorno.js agora tem apiFetch local (mesmo padrão de desembarque.js).
- Rotas locais /retorno/* e /auditoria-estoque/* adicionadas em v1/routes/api.php (em produção podem já existir noutro arquivo: não sobrescrever às cegas).
- Testado na tela real: bipe 402/4003/EAN/ref 03144/inexistente, foto, lista do embarque 9304 (37 itens), seleção de item.
