# Continuidade — Padronização de Tooltips Modernos Flutuantes (CRM, Análise de Carteira & Regiões)

Data: 09/11/2026
Módulos: 
- CRM de Clientes (`portal/modules/marketing/clientes.php`)
- Análise de Carteira & Inadimplência (`portal/modules/analise-carteira.php`, `portal/assets/js/analise-carteira.js`)
- Dashboard de Vendas (`portal/modules/dashboard-vendas.php`)

---

## 1. Contexto e Motivação
- Padronização do sistema universal de tooltips ricos flutuantes (`#modernTooltipDash`) inspirado no Dashboard de Vendas.
- Eliminação de tooltips nativos do navegador (`title`), substituindo por badges coloridas temáticas, ícone de ajuda `fa-circle-question`, títulos destacados e quebras de linha preservadas (`whitespace-pre-line`).

---

## 2. Implementações Realizadas

### A. Dashboard de Vendas (`portal/modules/dashboard-vendas.php`)
- Substituído `:title` nos cards de macro-regiões por `:data-tooltip="reg.regiao + ' — ' + reg.nome + '|Sub-regiões integradas no ERP:\n• ' + (reg.subregioes || '').split(', ').join('\n• ')"`.
- Adicionado ícone `fa-circle-question` ao lado da porcentagem de cada card de região.
- Inserida a classe `whitespace-pre-line` em `#tooltipDashDesc` para renderizar perfeitamente as sub-regiões em lista com marcadores (`•`).

### B. Análise de Carteira & Inadimplência (`portal/modules/analise-carteira.php`, `portal/assets/js/analise-carteira.js`)
- Adicionados tooltips ricos nos 5 cards principais de KPIs:
  - Total Vencido, IAG (Índice de Atraso Global), Títulos Vencidos, Carteira À Vencer, Carteira Total.
- Adicionados tooltips nas faixas de atraso (30 Dias, 60 Dias, +60 Dias) e nos botões de períodos rápidos.
- Implementado container `#modernTooltipDash` com `whitespace-pre-line` e função `initModernTooltipsDash()` com categorização inteligente de badges (`COBRANÇA`, `RISCO / IAG`, `CRÉDITO`, `CARTEIRA`, `FILTRO`).

### C. CRM de Clientes (`portal/modules/marketing/clientes.php`)
- Atualizados os 6 cards da Stats Row (Visão Executiva CRM):
  - Total Carteira CRM, Clientes Fechados, Em Negociação, Pipeline Financeiro, Taxa de Conversão, Agenda & Disciplina Comercial.
- Adicionados tooltips nos seletores de período rápido (`Hoje`, `Este Mês`, `Mês Passado`, `30 dias`, `Este Ano`, `Todos`, `Compra`, `Cadastro`, `Personalizado`).
- Adicionado container `#modernTooltipDash` antes do footer.
- Implementada a função `initModernTooltipsDash()` com badges de CRM (`FECHAMENTO`, `OPORTUNIDADE`, `PIPELINE`, `CONVERSÃO`, `AGENDA & METAS`, `CARTEIRA`, `FILTRO`, `DETALHE`).

---

## 3. Validação Executada
- `php -l portal/modules/marketing/clientes.php` -> Sintaxe OK.
- `php -l portal/modules/analise-carteira.php` -> Sintaxe OK.
- `php -l portal/modules/dashboard-vendas.php` -> Sintaxe OK.
- `node -c portal/assets/js/analise-carteira.js` -> Sintaxe OK.
- `node -c portal/assets/js/dashboard-vendas.js` -> Sintaxe OK.

---

## 4. Arquivos para Deploy FTP
1. `portal/modules/marketing/clientes.php`
2. `portal/modules/analise-carteira.php`
3. `portal/assets/js/analise-carteira.js`
4. `portal/modules/dashboard-vendas.php`
