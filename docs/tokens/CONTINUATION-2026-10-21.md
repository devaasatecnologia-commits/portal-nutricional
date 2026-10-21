# Handoff de continuidade - Dashboard de Vendas, Retorno de Pedidos e Especificação Produção MTR

**Data:** 2026-10-21  
**Repositório:** `devaasatecnologia-commits/portal-nutricional`  
**Substitui como fonte atual:** [CONTINUATION-2026-10-19.md](CONTINUATION-2026-10-19.md)  
**Token do Módulo MTR (Misturão):** `TOKEN-PRODUCAO-MTR-MISTURAO-20261021` (pausado, documentado em `docs/modulo_producao_mtr_especificacao.md`)  
**Não confirma:** schema, credenciais ou estado real de produção.

---

## 1. O que foi implementado e ajustado nesta data

### 1.1 Dashboard de Vendas (`portal/modules/dashboard-vendas.php`, `portal/assets/js/dashboard-vendas.js`, `v1/src/Controllers/VendasDashboardController.php`)
- **Correção de Erro 500 nos Modais dos Cards:**
  - Métodos `rastreioNfsFaturadas()`, `Carteira` e `Positivação` estavam anexando cláusulas `LEFT JOIN filial f` e `LEFT JOIN carteira_rep` após a cláusula `WHERE` de `nfFrom()` e `pedFrom()`.
  - As junções com `filial` foram movidas diretamente para o corpo de `nfFrom()` e `pedFrom()`, e a consulta de Positivação foi reestruturada via CTE (`vendas_rep` + `carteira_rep`).
  - Todos os 8 cards agora retornam HTTP 200 com resumo e rastreio completos.
- **Projeção de Vendas (Ajuste dos Meses):**
  - Conforme solicitação, os meses futuros projetados (nov, dez, jan) foram suprimidos do bloco de projeção no backend (`projecaoProximosMeses`), no frontend (`formatarProjecao`) e no template.
  - Exibe agora apenas os 3 meses anteriores fechados (`realizado`) e o mês corrente (`atual`, com faturamento realizado até hoje e projeção de fechamento).
- **Filtro de Representante:**
  - Corrigido o dropdown de clientes em risco (`filtroRiscoRep`): antes gravava o nome do representante enquanto o script comparava por ID numérico. Agora associa o ID `:value="rep.id"` e o método `clientesRiscoFiltrados()` suporta matching tanto por ID quanto por nome.
  - No backend (`clientesRisco`), ao selecionar um representante no filtro de escopo geral, filtra no SQL diretamente pela carteira/última compra (`AND COALESCE(cl.idvendedor, u.ult_rep) = :rep_escopo`).
- **Modal de Positivação da Carteira:**
  - Corrigida a normalização de `modalCardTipo` no JavaScript de `'Positivação'` para `'positivacao'`.
  - Ativados os 3 KPIs superiores dedicados: *Faturamento Positivado*, *Positivados / QT Carteira* e *% Positivação da Carteira*.
  - Ativada a tabela executiva específica de Positivação (*Representante*, *Valor Faturado*, *Clientes Positivados*, *QT Carteira*, *% Positivação* com barra percentual e `tfoot` com o total geral).
  - Ativada a tabela de rastreio de clientes positivados (*Cliente Positivado*, *Representante*, *NFs no Período*, *Última Compra*, *Valor Comprado*).
- **Rastreio de Documentos e Correção de Modais Sobrepostos (`z-index`):**
  - Adicionado `n.idcliforemp AS idcliente` em todas as consultas de rastreio (`rastreioNfsFaturadas`, `Carteira`, `Cancelados`, `Devoluções`, `Positivação`).
  - Corrigida a coluna *Documento* para ler `r.numero || r.documento || ('#' + r.id_doc)`, eliminando colunas vazias.
  - Removido clique acidental de linha inteira que abria modal de cliente com `idnfs` em vez de `idcliente`.
  - Isolado o clique para o nome do cliente (`verDetalhesCliente(r.idcliente, r.cliente)`).
  - Elevado o `z-index` dos modais secundários (`modalClienteOpen`, `modalProdutoOpen`, `modalRepresentanteOpen`, `modalFunilOpen` para `z-[70]`, e `modalRiscoOpen` para `z-[60]`). Ao abrir detalhes de cliente pelo rastreio, o modal sobrepõe perfeitamente o card (`z-50`) e exibe dados reais (histórico, notas, ticket e top 10 produtos).

### 1.2 Módulo Retorno de Cargas / Retorno de Pedidos (`portal/assets/js/retorno.js`, `v1/routes/api.php`, `v1/src/Controllers/RetornoController.php`)
- Ajustes na consulta e rota de busca de itens e notas do retorno de carga, permitindo auditoria e validação rápida por embarque.

### 1.3 Módulo Produção MTR (Misturão) — Pausado
- Regras levantadas e especificadas em documento técnico dedicado: `docs/modulo_producao_mtr_especificacao.md`.
- Token reservado para retorno: `TOKEN-PRODUCAO-MTR-MISTURAO-20261021`.

---

## 2. Arquivos para subir no FTP (Deploy em Produção)

Os seguintes arquivos devem ser enviados ao servidor de produção via FTP:

1. **`v1/src/Controllers/VendasDashboardController.php`**  
   *Caminho remoto:* `public_html/v1/src/Controllers/VendasDashboardController.php` (ou subdiretório correspondente da API)
2. **`v1/src/Controllers/RetornoController.php`**  
   *Caminho remoto:* `public_html/v1/src/Controllers/RetornoController.php`
3. **`v1/routes/api.php`**  
   *Caminho remoto:* `public_html/v1/routes/api.php`
4. **`portal/assets/js/dashboard-vendas.js`**  
   *Caminho remoto:* `public_html/portal/assets/js/dashboard-vendas.js`
5. **`portal/assets/js/retorno.js`**  
   *Caminho remoto:* `public_html/portal/assets/js/retorno.js`
6. **`portal/modules/dashboard-vendas.php`**  
   *Caminho remoto:* `public_html/portal/modules/dashboard-vendas.php`

> [!NOTE]
> Não há novas dependências de Composer nem novas classes criadas, portanto **não é necessário** rodar `composer dump-autoload` no servidor.

---

## 3. Próximos Passos (Amanhã)

1. **Módulo Transferência entre Filiais / Compra:**
   - Trabalhar no módulo `portal/modules/transferencia-compra.php`.
   - Revisar rotas e controllers de transferência e compras (`v1/src/Controllers/TransferenciaCompraController.php` ou correlatos).
   - Realizar os ajustes operacionais pendentes.
   - **Implementar novas situações e regras de negócio** solicitadas pelo usuário para o fluxo de transferências e ordens de compra.
