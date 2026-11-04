# CONTINUATION TOKEN: CUBO-VENDAS — REFORMULAÇÃO DE CARDS, COLUNAS E LIMPEZA DE TOOLTIPS

**Data:** 04/11/2026  
**Módulo:** `portal/modules/cubo-vendas.php` & `portal/assets/js/cubo-vendas.js`  
**Backend:** `v1/src/Controllers/VendasCuboController.php`

---

## 1. Contexto e Solicitações do Usuário Atendidas

1. **Reorganização dos 3 Cards Principais de Destaque (Hero KPIs):**
   - **Card 1:** **Venda Bruta (R$)** (`totals.valor_bruto` — faturados + carteira sem deduções), borda azul, ícone de nota faturada.
   - **Card 2:** **Devolução (R$)** (`totals.valor_devolvido`), borda vermelha/rosa, % sobre faturamento bruto.
   - **Card 3:** **Valor Líquido (R$)** (`totals.valor_liquido` — Venda Bruta menos Devoluções), borda verde esmeralda.

2. **Reorganização dos 4 Cards Operacionais (Cards Menores):**
   - **Card 1 Menor:** **Comissão Ajustada** (`totals.comissao`), borda âmbar, média % sobre receita líquida.
   - **Card 2 Menor:** **Peso & Quantidade (Dividido)**:
     - Lado Esquerdo: Peso Líquido em kg (`totals.peso`).
     - Lado Direito: Quantidade em un/volumes (`totals.quantidade`).
   - **Card 3 Menor:** **Nº Documentos** (`totals.qtd_documentos`), borda cinza ardósia.
   - **Card 4 Menor:** **Ticket Médio** (`totals.ticket_medio`), borda verde azulada.

3. **Ordem Exata das Colunas no Detalhamento (Tabela Principal):**
   - Dimensão Agrupadora (ex: Tipo (Pedido/NF/Devolução), Cliente, etc.)
   - **VENDA BRUTA** (`valor_bruto`)
   - **DEVOLUÇÃO** (`valor_devolvido`)
   - **VENDA LIQUIDA** (`valor_total`)
   - **COMISSÃO** (`comissao_total`)
   - **QUANTIDADE** (`quantidade_total`)
   - **PESO** (`peso_total`)
   - **% PARTICIPAÇÃO** (calculado sobre receita líquida total)

4. **Eliminação Cirúrgica de Tooltips Irrelevantes:**
   - Retirados todos os `data-tooltip` que cobriam a tela nos inputs de data, selects de filial, gestor, representante e agrupar por.
   - `initModernTooltipsCubo` agora escuta estritamente `[data-tooltip]` e não captura atributos `[title]`.
   - Removido `:title="doc.cliente"` da listagem do modal de detalhamento para não gerar popover redundante repetindo a razão social ou motivo da devolução.
   - Removido `title` redundante da listagem do ranking Top 10 Clientes.

---

## 2. Arquivos Modificados para Deploy FTP

1. `v1/src/Controllers/VendasCuboController.php`
2. `portal/modules/cubo-vendas.php`
3. `portal/assets/js/cubo-vendas.js`

---

## 3. Validação Executada
- PHP Lint: `php -l v1/src/Controllers/VendasCuboController.php` e `php -l portal/modules/cubo-vendas.php` sem erros.
- JS Lint: `node -c portal/assets/js/cubo-vendas.js` sem erros.

