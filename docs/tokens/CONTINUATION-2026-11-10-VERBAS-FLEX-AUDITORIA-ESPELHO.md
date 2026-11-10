# Continuation Token — Auditoria Mercos, Treinamentos & Manuais v3.5

**Data:** 10/11/2026 10:43  
**Branch:** `main`  
**Status:** 100% Documentado, Treinamento Atualizado no Banco e Pronto para Deploy FTP

---

## 1. Treinamentos e Manuais Atualizados

### A. Módulo Verbas Flex (`portal/modules/verbas-flex.php`)
- **Versão:** 3.5 — Novembro/2026
- **Base de Conhecimento (`sistema_manuais`, ID 26):** Atualizado com:
  - Explicação do **Card Hero de Saldo Mercos em Tempo Real** (individual ou equipe ao vivo).
  - Guia de uso dos **Chips Rápidos de Filtro** e exportação Excel.
  - Regra de ouro: **Débito Direto (Saldo Próprio)** vs **Cobertura pelo Gestor (Via Gestor)**.
  - Parâmetros oficiais de Flex Mínimo (Filial 1: R$ 3.000 / Tiago MOV | Filial 6: R$ 1.000 / Tales).
  - Esclarecimento técnico sobre o flex de pedidos na nuvem Mercos vs bridge local `/saldo_flex`.
- **Arquivo Físico:** [`docs/manual-verbas-flex.md`](file:///c:/xampp/htdocs/API/docs/manual-verbas-flex.md) atualizado.
- **Botão Direto:** Ativo no cabeçalho direcionando para `portal/modules/treinamentos.php?modulo=verbas-flex`.

### B. Módulo Financeiro (`portal/modules/financeiro.php`)
- **Versão:** 3.3 — Novembro/2026
- **Base de Conhecimento (`sistema_manuais`, ID 19):**
  - Indicadores executivos (IAG, IAP, Recuperação e Perda >60d).
  - Painel de Metas com Semáforos e filtros de ciclo.
  - Governança de Metas no Admin (`metas-financeiro.php` restrito a administradores).
  - Isolamento de Rate Limit por rota (`user:ID:path:ROTA`) e teto de 200 req/min.
- **Arquivo Físico:** [`docs/manual-financeiro.md`](file:///c:/xampp/htdocs/API/docs/manual-financeiro.md) e [`docs/manual-financeiro-metas.md`](file:///c:/xampp/htdocs/API/docs/manual-financeiro-metas.md).
- **Botão Direto:** Ativo no cabeçalho direcionando para `portal/modules/treinamentos.php?modulo=financeiro`.

---

## 2. Lista Completa de Arquivos para Deploy FTP (cPanel)

1. `v1/src/Services/MercosFlexService.php`
2. `v1/src/Controllers/VerbasFlexController.php`
3. `v1/src/Controllers/CronRotinasController.php`
4. `v1/src/Controllers/FinanceiroController.php`
5. `v1/src/Middleware/RateLimitMiddleware.php`
6. `v1/routes/api.php`
7. `portal/modules/verbas-flex.php`
8. `portal/assets/js/verbas-flex.js`
9. `portal/modules/financeiro.php`
10. `portal/assets/js/financeiro.js`
11. `docs/manual-verbas-flex.md`
12. `docs/manual-financeiro.md`
13. `docs/manual-financeiro-metas.md`
