# Token de Continuação — Dashboard de Vendas: Hierarquia de Regiões, Redundâncias e Tooltips Flutuantes Ricos

**Data:** 04/11/2026  
**Contexto:** Módulo `dashboard-vendas.php` e `VendasDashboardController.php` — Hierarquia restrita de regiões por usuário/gestor (ex: Michel), eliminação de redundâncias de projeção e sistema de tooltips modernos flutuantes.

---

## 1. Alterações Realizadas

### A. Hierarquia Estrita de Regiões por Usuário / Escopo Comercial (Backend)
- **Arquivo:** `v1/src/Controllers/VendasDashboardController.php`
- **Comportamento:**
  - O método `distribuicaoRegiao` agora filtra rigorosamente as macro-regiões: regiões que não possuem movimentação comercial real (`valor <= 0 && peso <= 0 && notas <= 0 && clientes <= 0`) dentro do escopo permitido do usuário são descartadas do array de retorno.
  - No caso do usuário Michel (`idusuario = 95`, gestor da Filial 1), as regiões de outra filial (RMC e AVI) ficam ocultas automaticamente, trazendo apenas as regiões ativas onde seus representantes e clientes atuam (**SUL**, **GRF**, **NOR**, **PLN**).
  - O método `projecaoFechamento` herda essa distribuição, projetando o fechamento exclusivamente sobre as regiões autorizadas e ativas.

### B. Gráfico Donut de Regiões Dinâmico (Frontend)
- **Arquivo:** `portal/assets/js/dashboard-vendas.js`
- **Comportamento:**
  - O método `renderizarGraficoRegiao()` agora filtra regiões com dados reais e exibe o rótulo `'Total Regiões'` no centro do gráfico donut, sem fixar estritamente 6 regiões quando o usuário tem um escopo menor.

### C. Eliminação de Hardcode e Redundâncias no HTML
- **Arquivo:** `portal/modules/dashboard-vendas.php`
- **Comportamento:**
  - **Cabeçalho de Regiões:** De `"DISTRIBUIÇÃO POR REGIÃO — 6 MACRO-REGIÕES UNIFICADAS"` para `"DISTRIBUIÇÃO POR REGIÃO DO ESCOPO"`.
  - **Filtro de Região nos Clientes Sem Compras (+30d):** O select antes continha as 6 opções hardcoded em HTML. Agora é 100% dinâmico com `<template x-for="reg in distribuicaoRegiao" :key="reg.regiao">`, renderizando apenas as regiões ativas do usuário logado.
  - **Diferenciação Clara das Projeções:**
    - Bloco Superior (Histórico/Tendência): `<i class="fa-solid fa-chart-line mr-1.5 text-indigo-600"></i> HISTÓRICO & TENDÊNCIA MULTIMENSAL (M-3 A M+1)`.
    - Bloco Inferior (Fechamento do Mês Vigente): `<i class="fa-solid fa-rocket text-amber-300"></i> PROJEÇÃO DO MÊS ATUAL (FECHAMENTO ESTIMADO)`.

### D. Sistema Universal de Tooltips Flutuantes Ricos (`#modernTooltipDash`)
- **Arquivos:** `portal/assets/js/dashboard-vendas.js` e `portal/modules/dashboard-vendas.php`
- **Comportamento:**
  - Substituídos os tooltips CSS estáticos com `-top-2` (que cortavam fora da viewport em telas menores ou ao rolar) pelo container flutuante `#modernTooltipDash` com posicionamento inteligente e cálculo dinâmico de coordenadas de borda (`window.innerWidth` / `window.innerHeight`).
  - Adicionado `data-tooltip="Título|Descrição"` com badges contextuais automáticos (`COMERCIAL`, `PROJEÇÃO`, `CARTEIRA`, `AUDITORIA`, `RENTABILIDADE`, `ESCOPO`, `FILTRO`) nos:
    - 6 mini-cards da Barra Executiva de Foco (Faturamento, Peso Líquido, Qtd, NFs & Clientes, Carteira Aberta, Dias Úteis).
    - 8 cards principais de KPIs (Faturamento, NFs & Volume, Ticket Médio, Ritmo Diário, Carteira, NFs Canceladas, Devoluções, Positivação).
    - Botões de períodos rápidos (Hoje, Este Mês, Mês Passado, Últimos 30d, Últimos 90d, Este Ano, Projeção Detalhada, Atualizar e Exportar CSV).

---

## 2. Arquivos para Deploy FTP

1. `v1/src/Controllers/VendasDashboardController.php`
2. `portal/assets/js/dashboard-vendas.js`
3. `portal/modules/dashboard-vendas.php`

---

## 3. Validações Realizadas
- `php -l v1/src/Controllers/VendasDashboardController.php` -> Sintaxe OK.
- `php -l portal/modules/dashboard-vendas.php` -> Sintaxe OK.
- `node -c portal/assets/js/dashboard-vendas.js` -> Sintaxe OK.
- Verificação no banco de dados com os parâmetros do usuário Michel (`idusuario = 95`): 4 regiões com movimentação retornadas, RMC e AVI suprimidas.
