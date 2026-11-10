# Continuation Token — Auditoria Mercos, Resolução Financeiro & Treinamentos

**Data:** 10/11/2026 09:55  
**Branch:** `main`  
**Status:** Validado e pronto para deploy FTP

---

## 1. Síntese das Ações Executadas

### A. Módulo Financeiro (`portal/modules/financeiro.php`)
1. **Erro de Rate Limit HTTP 429 ("Muitas requisições. Tente novamente em 59 segundos"):**
   - **Causa:** Ocorrido em 09/11 às 17:37:07 quando o painel carregava em paralelo `dashboard`, `metas/progresso`, `historico-kpi` e lista de usuários sob o limite compartilhado do RateLimitMiddleware.
   - **Solução:** Aplicada no commit `571c235` com chaveamento isolado por rota (`user:ID:path:ROTA`) e teto ampliado para 200 req/min no dashboard (padrão 300). Tabela resetada e testada hoje com HTTP 200.
2. **Treinamento e Documentação:**
   - Manual completo na versão **3.3** salvo em `docs/manual-financeiro.md` e persistido na base de conhecimento (`sistema_manuais` ID 19).
   - Botão direto `Treinamento & Manual` com ícone de capelo ativo no topo do módulo direcionando para `portal/modules/treinamentos.php?modulo=financeiro`.

### B. Empréstimos Recorrentes de R$ 3.000,00 (Verbas Flex)
- Concorrência identificada com o motor de 4 minutos do ERP legado. Como o agendamento foi desativado no ERP, o fluxo passa a ser controlado 100% pelo portal e crons oficiais.

### C. Auditoria Verba Flex & Anaildo Pereira
1. **Correção do - R$ 0,00:**
   - Alterada a seleção de `b.valor_tirado` (cobertura do gestor) para `ABS(b.valor)` (bonificação real faturada) nas queries de auditoria e adicionado badge verde `Saldo Próprio (Débito Direto)`.
   - Saldo do Anaildo no Mercos confirmado em R$ 190,13.
2. **Filtro de Ativos no Mercos:**
   - Dropdown lista apenas representantes com conta ativa no Mercos (`excluido != true`).
3. **Card Hero Dinâmico no Topo:**
   - Exibe o Saldo Atual no Mercos em Tempo Real (individual se selecionado um representante, ou soma da equipe se selecionado "Todos"), com botão de atualização instantânea ao vivo.
4. **Chips Rápidos de Filtro:**
   - Atalhos para Débitos (-), Créditos (+), Bonificações, Transferências e Flex Mínimo.

---

## 2. Arquivos para Deploy FTP (cPanel)

1. `v1/src/Middleware/RateLimitMiddleware.php`
2. `v1/src/Controllers/FinanceiroController.php`
3. `v1/src/Controllers/VerbasFlexController.php`
4. `v1/src/Controllers/CronRotinasController.php`
5. `v1/routes/api.php`
6. `portal/modules/financeiro.php`
7. `portal/assets/js/financeiro.js`
8. `portal/modules/verbas-flex.php`
9. `portal/assets/js/verbas-flex.js`
10. `docs/manual-financeiro.md`
