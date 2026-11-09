# Token de Continuidade — Treinamentos e Documentação dos Módulos para Gestores

- **Data:** 2026-11-09
- **Status:** Concluído e Validado
- **Módulos Atendidos:**
  1. **Financeiro & Controladoria (`portal/modules/financeiro.php`, `v1/src/Controllers/FinanceiroController.php`, `v1/src/Middleware/RateLimitMiddleware.php`):**
     - Corrigido o bloqueio HTTP 429 ("Muitas requisições. Tente novamente em 59 segundos") ajustando o rate limit para escopo por rota (`user:ID:path:ROTA`) e ampliando o teto da rota para 200 req/min. Tabela de rate limits zerada.
     - Corrigida a duplicação dos cards de metas nas queries `getMetas` e `getMetasComProgresso` ocasionada por filiais id=0 duplicadas no schema legado.
     - Botão de acesso ao treinamento integrado no header desktop e mobile.
     - Manual atualizado para versão 3.3 no banco (`sistema_manuais` ID 19) e documentação em `docs/manual-financeiro.md`.
  2. **Pedido de Amostra (`portal/modules/pedido-amostra.php`, `portal/assets/js/pedido-amostra.js`):**
     - Botão de acesso ao treinamento integrado no hero do módulo.
     - Implementado visualizador de fotos ampliada em alta resolução com SweetAlert e zoom interativo no catálogo de itens e no modal de rastreamento.
     - Manual atualizado para versão 3.0 no banco (`sistema_manuais` ID 25) e documentação em `docs/manual-pedido-amostra.md`.
  3. **Cubo de Vendas (`portal/modules/cubo-vendas.php`):**
     - Botão de acesso ao treinamento integrado no header desktop e mobile.
     - Manual atualizado para versão 3.0 no banco (`sistema_manuais` ID 23) e documentação em `docs/manual-cubo-vendas.md`.
  4. **Estoque com Previsão (`portal/modules/estoque-previsao.php`):**
     - Botão de acesso ao treinamento integrado no hero header.
     - Enriquecimento dos 6 cards de KPI com atributo `title` detalhado com quebra de linha para orientação rápida do usuário.
     - Manual atualizado para versão 3.0 no banco (`sistema_manuais` ID 12) e documentação em `docs/manual-estoque-previsao.md`.

---

## Arquivos para Deploy FTP
- `v1/src/Middleware/RateLimitMiddleware.php`
- `v1/src/Controllers/FinanceiroController.php`
- `portal/modules/financeiro.php`
- `portal/modules/pedido-amostra.php`
- `portal/assets/js/pedido-amostra.js`
- `portal/modules/cubo-vendas.php`
- `portal/modules/estoque-previsao.php`
- `docs/manual-financeiro.md`
- `docs/manual-pedido-amostra.md`
- `docs/manual-cubo-vendas.md`
- `docs/manual-estoque-previsao.md`
