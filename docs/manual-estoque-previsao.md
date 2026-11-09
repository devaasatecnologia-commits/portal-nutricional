# Manual do Especialista — Estoque com Previsão de Chegada v3.0

## 1. Visão Geral e Propósito
O módulo de **Estoque com Previsão** (`portal/modules/estoque-previsao.php` e `v1/src/Controllers/EstoquePrevisaoController.php`) unifica os saldos de estoque físico, os pedidos de venda em carteira e as ordens de compra a caminho entre a Matriz (SC) e a Filial (PR).

---

## 2. A Fórmula da Disponibilidade
Para qualquer produto cadastrado:
- **Estoque Físico:** Total armazenado nos galpões da Matriz SC e Filial PR.
- **Carteira Reservada:** Bloqueio decorrente de pedidos de venda em aberto ou parcialmente faturados (Status 1 e 4 no ERP).
- **Livre Disponível:** $\text{Físico} - \text{Reservado}$ (mercadoria disponível para faturamento imediato).
- **Previsão Compras (OCs):** Mercadorias compradas de fornecedores com Ordens de Compra autorizadas e a caminho.
- **Estoque Futuro:** $\text{Livre Disponível} + \text{Previsão de Compras}$ (projeção de curto prazo).

---

## 3. Os 6 Cards de KPI com Interatividade e Title Detalhado
1. **Estoque Físico Total:** Saldo real em armazém.
2. **Carteira Reservada:** Bloqueio por pedidos de venda.
3. **Livre Disponível:** Volume liberado para pronta entrega.
4. **Previsão Compras:** Compras em trânsito com fornecedores.
5. **Estoque Futuro:** Estoque projetado após recebimento das compras.
6. **Críticos / Zerados:** Alerta de itens com estoque livre $\le 10$ unidades.

> **Dica Executiva:** Clicar em qualquer um dos cards filtra automaticamente a tabela de produtos para exibir apenas os itens relacionados àquele KPI.

---

## 4. Alternância entre Peso (Kg / Ton) e Peças (Un)
A barra superior permite alternar instantaneamente a unidade de medida dos 6 cards de topo entre **Peso (Kg/Ton)** e **Unidades (Peças)**, adequando a análise para logística de carga ou equipe comercial.
