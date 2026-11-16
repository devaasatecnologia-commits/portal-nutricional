# Handoff de Continuidade — CHAPD/PDI A4, Chamados Governance/Dashboard, Movimentação Flex Live & Trava XML/Desembarque (2026-11-16)

## 1. Resumo das Entregas da Sessão (16/11/2026)

### 1.1 C.H.A.P.D & Plano de Ação PDI 5W2H (`Chamado #61` — v2.2)
- **Ciclo de Vida e Travas de Edição:**
  - `AvaliacaoChapdController.php` e `v1/routes/api.php`: adicionadas rotas e validações (`POST /finalizar`, `DELETE /{id}`, `POST /excluir`, `POST /planos-acao/finalizar-impressao`, `POST /planos-acao/fechar`) com bloqueio HTTP `422` após finalização/impressão/fechamento.
  - Fluxo `C.H.A.P.D`: permite salvar rascunho para edição contínua e finalizar com bloqueio antes da impressão para assinatura (colaborador, avaliador/gestor e RH/testemunha).
  - Fluxo `PDI / 5W2H`: ciclo em 3 estágios (`Em Elaboração` -> `Impresso` -> `Fechado`), com visualizar/editar/excluir antes da impressão e fechamento formal pós-impressão.
- **Ampliação de Limites no Banco e Layouts A4 PDF Premium:**
  - Resolvido `SQLSTATE[22001]: String data, right truncated` (`VARCHAR(50)`) ampliando campos descritivos para `TEXT` e opções para `VARCHAR(255)` em PostgreSQL/`ensureSchema()`.
  - Implementada impressão A4 Retrato (C.H.A.P.D) e Paisagem (PDI 5W2H) com foto 3x4, ficha completa, `data_admissao_fmt`, 5 pilares C.H.A.P.D, textos integrais e 3 campos de assinatura.
  - Manuais `avaliacao-chapd` (`v2.1`) e `plano-acao-chapd` (`v2.0`) atualizados no banco.

### 1.2 Central de Chamados & IA (`Chamado #63` — v3.7)
- **Governança TI Liberado vs Solicitante:**
  - Alteração de estágio Kanban/Stepper, prioridade, criticidade e conclusão restritas exclusivamente à equipe de TI liberada (`HTTP 403` no backend em `ChamadosController.php` e bloqueios na UI em `chamados.php` / `chamados.js`).
  - Solicitantes mantêm permissão para visualizar, acompanhar, comentar, anexar prints e editar título/mensagem.
  - Liberada seleção/alteração de solicitante pelo Admin/TI na Abertura Expressa, Modal Novo e Modal Detalhes (`resolverDadosSolicitante`), com migração histórica dos chamados de `MARCON` e `INTERNO`/`.` para `ALAN` (`idcliforemp = 5166`) e adição da regra permanente em `GEMINI.md`.
- **Correção de Upload de Prints/Anexos:**
  - Removido `'Content-Type': 'application/json'` forçado em `portal/assets/js/config.js` quando o body é `FormData`; `chamados.js` (`uploadAnexo` e `selecionarArquivoAnexo`) e `ChamadosController.php` padronizados com envio Base64 + multipart.
- **Dashboard Executivo de Rastreamento:**
  - Adicionados `ranking_solicitantes`, `por_status`, `por_categoria`, `performance_atendentes`, `usuarios_solicitantes` e MTTR em `ChamadosController::dashboard()`, com gráficos e tabela analítica de rastreio por solicitante.

### 1.3 Movimentação de Flex (`Chamado #3` — v2.5 — Commit `47d450d`)
- Implementado cache local anti rate-limit (TTL 20s / anti-burst 3s) em `v1/src/Services/MercosFlexService.php` e espelhamento em `public.saldo_flex`.
- Unificado extrato de 4 origens com filtros por gestor e saldo Mercos Live em `v1/src/Controllers/FlexMovimentacaoController.php`, `portal/modules/movimentacao-flex.php` e `portal/assets/js/movimentacao-flex.js`.

### 1.4 Trava XML -> Desembarque, Busca no Coletor e Regularização OC `#4019` (`Chamado #65` — v2.1)
- **Trava Obrigatória de Conferência XML antes do Desembarque:**
  - `v1/src/Controllers/XmlController.php`:
    - `atualizarConferencia`: chama `marcarConferidoPortal()` marcando `conferido_portal = 'S'` em `aps_oc_conferencia` (preservando `usuario` se já estiver em status `2` ou `3`) e sincroniza registros únicos em `aps_oc_conferencia_item`, `aps_oc_conferencia_item_lote` e `oc_item_lote` via `sincronizarQuantidadeLoteUnico()`.
    - `getOrdensCompra` e `consultaOC`: detectam conferência XML via `aps_oc_conferencia.conferido_portal = 'S'` ou `oc_operacao` (`PORTAL`), mantendo OCs com XML conferido visíveis para revisão até finalização física (`status <> 3`).
  - `v1/src/Controllers/DesembarqueController.php`:
    - Novo helper `isConferenciaXmlConcluida(int $idoc): bool`.
    - `getOrdensCompra`: lista apenas OCs com Conferência XML concluída, retornando `xml_conferido = 'S'`.
    - `getItens`, `buscarItem`, `confirmarItem` e `finalizarConferencia`: retornam **HTTP `409`** se a OC não tiver concluído a Conferência XML.
    - `confirmarItem`: trata transição `status 1 -> 2` mantendo `conferido_portal = 'S'` e utiliza helper `sincronizarLoteConferencia()` para sobrescrever/somar lotes em `aps_oc_conferencia_item_lote` e `oc_item_lote` sem filtrar `quantsaldo > 0`.
  - `portal/assets/js/desembarque.js` e `portal/modules/desembarque.php`: selo **`XML OK`** nos cards de OC, aviso quando não houver OCs liberadas e mensagem orientativa.
- **Correção da Busca de Item (`buscar-item`) no Gateway `?api_route=`:**
  - `index.php`: passou a atualizar `$_SERVER['QUERY_STRING']` e `$_SERVER['REQUEST_URI']` removendo `api_route` quando a rota contém query params internos (`?codigo=...`), corrigindo leitura de `$request->getQueryParams()` no Slim.
  - `DesembarqueController::buscarItem`: fallback em `$_GET['codigo']` e suporte a busca por **ID do item**, **Referência** (com/sem zero à esquerda) e **Código de Barras ativo (`TRIM(idbarra)`)**.
  - `portal/assets/js/desembarque.js`: `apiFetch` converte `?` interno em `&` no modo `?api_route=` e exibe `e.message` real no `Swal`.
- **Banco de Dados (`OC #4019` vs `NF #35018`):**
  - Backfill de `aps_oc_conferencia.conferido_portal = 'S'` em 134 registros históricos.
  - Estorno/limpeza completa do desembarque residual da **OC `#4019`** (`oc_item_lote` e `oc_operacao` DOX removidos, mantendo os registros da Conferência XML `2943`, `2944`, `2952`) para nova bipagem no coletor com os saldos já ajustados para a NF `#35018` (`960`, `5280`, `546`, `1170`, `480`).
  - Manuais `desembarque` e `xml` atualizados em `sistema_manuais` para **`v2.1`**.

---

## 2. Arquivos Alterados para Deploy FTP (após commit `47d450d`)
- `GEMINI.md`
- `index.php`
- `portal/assets/js/avaliacao-chapd.js`
- `portal/assets/js/chamados.js`
- `portal/assets/js/config.js`
- `portal/assets/js/desembarque.js`
- `portal/assets/js/plano-acao-chapd.js`
- `portal/modules/avaliacao-chapd.php`
- `portal/modules/chamados.php`
- `portal/modules/desembarque.php`
- `portal/modules/plano-acao-chapd.php`
- `v1/routes/api.php`
- `v1/src/Controllers/AvaliacaoChapdController.php`
- `v1/src/Controllers/ChamadosController.php`
- `v1/src/Controllers/DesembarqueController.php`
- `v1/src/Controllers/XmlController.php`

*(Observação: os 4 arquivos do Chamado `#3` — `MercosFlexService.php`, `FlexMovimentacaoController.php`, `movimentacao-flex.php`, `movimentacao-flex.js` — já foram commitados anteriormente em `47d450d`; incluir também no FTP caso o servidor remoto ainda não tenha recebido esse deploy.)*
