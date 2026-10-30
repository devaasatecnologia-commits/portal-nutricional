# Token de Continuidade — Dashboard Executivo de Vendas v3.0 (Especialista) + Central de Treinamento Sincronizada

**Data:** 30/10/2026  
**Módulo:** `portal/modules/dashboard-vendas.php` (`dashboard-vendas`)  
**Status:** ✅ Concluído, Testado e Documentado no Modo Treinamento

---

## 1. Resumo Executivo das Melhorias Aplicadas

### A. Foco Triplo Permanente: Valor (R$), Peso Líquido (Kg/Ton) e Quantidade (Un/NFs)
- **Regra de Peso Líquido Alinhada ao Cubo de Vendas:**
  - `PESO = COALESCE(ni.pesoliquido, i.pesoliquido, 0) * ni.qt` nas NFs de saída (`nfs_item`) e `PESO_DEV` nas devoluções (`nfe_item`).
- **Barra de Escopo no Topo + 8 Cards Principais:**
  - Todos os 8 cards principais exibem simultaneamente **Valor (R$)**, **Peso Líquido (Kg / Ton)** e **Quantidade (Unidades / Documentos / Clientes)**.
  - **Hover Tooltips Explicativos:** Ao passar o mouse sobre qualquer card, abre-se um card flutuante detalhando a regra de negócio e a fórmula exata do indicador.
  - **100% Rastreáveis + Exportação Excel:** Clicar em qualquer card abre o modal analítico com 3 abas (*Visão Executiva por Representante*, *Rastreio de Documentos* e *Evolução Diária*), exibindo Peso, Quantidade e Valor, com botão **"Exportar Excel (.CSV)"**.

### B. Distribuição por Região — 6 Macro-Regiões Unificadas (`SUL`, `PLN`, `GRF`, `AVI`, `NOR`, `RMC`)
- Implementado o helper `regiaoMacroSql()` no `VendasDashboardController.php`, consolidando todas as 29 sub-regiões e rotas cadastradas no ERP nos **6 Polos Regionais Oficiais**:
  1. `SUL` — Sul Catarinense
  2. `GRF` — Grande Florianópolis
  3. `AVI` — Vale do Itajaí & Litoral
  4. `NOR` — Norte & Planalto Norte
  5. `PLN` — Planalto Serrano & Alto Vale
  6. `RMC` — Curitiba & Litoral PR
- Nova seção visual com **Gráfico Donut Interativo** (alternando entre *Por Valor R$* e *Por Peso Kg*) + **6 Cards Regionais Clicáveis e Rastreáveis** (ao clicar em qualquer região, abre-se o modal de auditoria filtrando representantes, evolução diária e NFs daquela região).

### C. Gráfico de Evolução de Vendas Multimétrica & Multiperíodo
- **Seletor de Métrica:**
  - `Combinado (R$ + Kg)` em duplo eixo (Colunas de Faturamento R$ + Linha suave de Peso Líquido Kg)
  - `Valor (R$)`
  - `Peso Líquido (Kg)`
  - `Quantidade (Un)`
  - `Nº de NFs`
- **Seletor de Agrupamento Temporal:**
  - `Auto`, `Diário`, `Semanal` e `Mensal` (alimentados por `evolucao.series_periodo` no backend).
- **Resumo no Cabeçalho do Gráfico:** Exibe Média por período (R$ e Kg) e Pico de vendas.

### D. Rankings Ampliados para Top 10 + Tipografia Ajustada sem Cortar Nomes
- **Top 10 Produtos**, **Top 10 Clientes** e **Top 10 Representantes** (`LIMIT 10`).
- Removido o truncamento prematuro (`max-w-[180px] truncate`) e aplicada tipografia `text-xs font-bold leading-snug break-words`, exibindo o nome completo do produto, cliente e representante junto de **Valor (R$)**, **Peso (Kg)** e **Quantidade (Un / NFs)**.

### E. Projeção de Fechamento Aprimorada + Modal Expandido + Exportação Excel (.CSV)
- **Cálculo Aprimorado:**
  - `Realizado até Hoje` + `(Média por Dia Útil × Dias Úteis Restantes)` = **Projeção Linear de Fechamento** (em R$, Peso Kg, Quantidade Un e NFs).
  - **Potencial Máximo com Carteira:** Soma a Carteira de Pedidos em Aberto do mês à projeção.
  - **Tendência M+1:** Calcula a projeção do próximo mês por média móvel ponderada por dia útil.
- **Modal Executivo Expandido (`modalProjecaoOpen`):**
  - 3 Abas: *Por Representante*, *Pelas 6 Regiões Unificadas* e *Comparativo Mensal (M-3 a M+1)*.
  - Botão **"Exportar para Excel (.CSV)"** (`exportarProjecaoExcel()`) gerando planilha completa com todas as seções.

### F. Substituição da "Margem por Produto" pelo Painel "Inteligência de Recompra & Clientes Sem Compras (+30 / +60 / +90 dias)"
- Removido o relatório estático de Margem por Produto do dashboard.
- Implementado o painel executivo de **Clientes Ativos Sem Compras (+30 dias)**:
  - **4 Cards de Faixa de Inatividade Clicáveis:** `30 a 45 dias`, `46 a 60 dias`, `61 a 90 dias` e `+90 dias` (com total de clientes, valor histórico R$ e peso histórico Kg).
  - **Filtros Combinados:** Por Representante, pelas 6 Regiões Unificadas (`SUL`, `GRF`, `AVI`, `NOR`, `PLN`, `RMC`), por Faixa de Inatividade e Busca por nome/código/cidade.
  - **Correção de `NFs Hist.` + Novos Campos:** Corrigido o cálculo de `compras_anteriores` (que antes aparecia zerado) para `COUNT(DISTINCT n.idnfs)` na janela histórica de 12 meses, adicionando também `Peso Hist. (Kg)` e `Qtd. Hist. (Un)`.
  - **Exportação Direta para Excel (.CSV)** (`exportarClientesRiscoExcel()`).

### G. Modo Treinamento & Manual Atualizado em Paralelo
- Adicionado botão **"Treinamento & Manual"** no cabeçalho desktop e mobile de `dashboard-vendas.php` apontando para `/portal/modules/treinamentos.php?modulo=dashboard-vendas`.
- Registro `dashboard-vendas` na tabela `sistema_manuais` atualizado para a **Versão 3.0** com todos os novos KPIs, fórmulas, ações e relatórios.

---

## 2. Arquivos Modificados (Lista para Deploy FTP)
1. `v1/src/Controllers/VendasDashboardController.php`
2. `v1/src/Controllers/TreinamentoController.php`
3. `v1/src/Controllers/CronRotinasController.php` *(correção do dry-run de `historico_kpi`)*
4. `portal/modules/dashboard-vendas.php`
5. `portal/assets/js/dashboard-vendas.js`
6. `portal/assets/js/treinamentos.js`

