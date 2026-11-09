# Continuation Token — Inteligência de Recompra Reativa e Manual de Treinamento

- **Data/Hora:** 09/11/2026 - 11:36
- **Branch:** main
- **Status:** Concluído e Validado

---

## 1. O que foi solicitado
1. **Inteligência de Recompra & Clientes Ativos Sem Compras (+30/+60/+90 dias):**
   - Revisão completa de valores, queries e operação.
   - Atualização dinâmica e reativa nos 4 cards de faixas (30-45d, 46-60d, 61-90d, +90d) ao aplicar filtros de Representante e Região.
   - Detalhes e diagnósticos exibidos ao passar o mouse (`:title`) nos cards, incluindo quantidade no filtro vs total, faturamento histórico (12m), volume físico (Kg), ticket médio por cliente e ação comercial recomendada para a faixa.
   - Cards também replicados e totalmente interativos no modal de tela cheia.
2. **Manual & Treinamento:**
   - Atualização do registro do manual no PostgreSQL (`sistema_manuais`, ID 24) para incorporar as 7 Macro-Regiões Oficiais, a regra estrita da Região 18 (RED) e a Inteligência de Recompra com detalhes no hover.
   - Correção do `TreinamentoController.php` para normalização bidirecional de slug com hífen e underline (`dashboard-vendas` <-> `dashboard_vendas`), corrigindo o erro 404 ao clicar em "Treinamento & Manual".
3. **Alinhamento do Módulo:**
   - Remoção de textos defasados citando "6 Regiões" na exportação CSV e no modal.

---

## 2. Arquivos para Deploy FTP
```text
portal/assets/js/dashboard-vendas.js
portal/modules/dashboard-vendas.php
v1/src/Controllers/TreinamentoController.php
```

*Nota de Banco:* A tabela `sistema_manuais` (ID 24) foi atualizada no banco PostgreSQL com os novos JSONs e textos do manual.
