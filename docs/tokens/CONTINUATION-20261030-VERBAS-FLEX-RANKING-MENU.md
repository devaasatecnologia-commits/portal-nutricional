# Token de Continuação — Verbas Flex: Pílulas do Ranking, Tooltips e Menu Superior

- **Data / Hora:** 30/10/2026 17:38 (BRT)
- **Módulos Afetados:** `portal/modules/verbas-flex.php`, `portal/assets/js/verbas-flex.js`, `portal/assets/js/transferencia-flex.js`
- **Contexto:**
  1. As pílulas de visão do ranking ("Quem Mais Cedeu", "Quem Mais Recebeu" e "Balanço da Equipe") não trocavam os dados na tela ao clicar.
  2. O menu superior `#abas` estava quebrando a linha devido ao excesso de opções (7 itens no total).
  3. Os tooltips modernos ao passar o mouse sobre os cards piscavam ou sumiam ao mover o cursor sobre os textos internos.

---

## 1. Alterações Executadas

### A. Pílulas do Ranking (`#visoesRanking`)
- Criada a função `trocarVisaoRanking(visao)` com amarração nos listeners do `DOMContentLoaded` e adicionado `onclick="trocarVisaoRanking(...)"` inline com `pointer-events-none` nos filhos (`<i>` e `<span>`).
- Ao clicar nas 4 visões:
  - `bonificacoes`: Lista os maiores consumidores de verba em notas fiscais de bonificação com link para extrato de notas.
  - `transferencias_cedidas`: Lista quem mais transferiu saldo flex no Mercos para outros colegas.
  - `transferencias_recebidas`: Lista quem mais recebeu saldo flex no Mercos.
  - `balanco`: Renderiza a tabela analítica 360° consolidada com cálculo de saídas (bonificações + transferências cedidas), entradas (transferências recebidas) e saldo líquido individual.

### B. Menu Superior (`#abas`)
- Removidas as abas secundárias `Movimentação` e `API Mercos` da barra principal `#abas`.
- Barra `#abas` enxuta e alinhada com as opções de gestão comercial essenciais:
  1. `Painel & Dash`
  2. `Ranking Consumo`
  3. `Consumo Bonificações`
  4. `Créditos / Empréstimos`
  5. `Transferências` (link para `transferencia-flex.php`)
- `Movimentação` e `Status Mercos` reposicionados como botões elegantes e discretos no Hero Banner superior para quem tem permissão administrativa (`movimentacao-flex` / `admin`).
- CSS de `.vf-tabs` otimizado com `flex-wrap: nowrap` e `min-width: max-content` para garantir que o menu nunca mais quebre de linha em nenhuma resolução.

### C. Tooltips Modernos e Suaves
- Corrigida a lógica do `mouseout` em `verbas-flex.js` e `transferencia-flex.js` checando `fromTarget.contains(e.relatedTarget)` para evitar que o tooltip feche ao mover o mouse entre filhos internos do card.
- Calculado posicionamento vertical com inversão dinâmica (`translate(-50%, 20px)`) caso o cursor esteja próximo ao topo da tela, impedindo que o tooltip fique fora do viewport.

---

## 2. Arquivos para Deploy FTP
- `portal/modules/verbas-flex.php`
- `portal/assets/js/verbas-flex.js`
- `portal/assets/js/transferencia-flex.js`

