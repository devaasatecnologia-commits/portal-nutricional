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

## Publicacao

Nao publicar por FTP sem confirmar o commit e as dependencias do ambiente.
Enviar somente os arquivos efetivamente commitados que correspondam ao deploy;
nao enviar `.env`, artefatos, arquivos temporarios, dumps nem alteracoes alheias.
