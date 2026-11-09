# Token de Continuidade — Refatoração Verbas Flex & Correções de Metas (Admin e Marketing)

- **Data:** 2026-11-09
- **Status:** Concluído e Validado
- **Módulos:**
  - Verbas Flex (`portal/modules/verbas-flex.php`, `portal/assets/js/verbas-flex.js`, `v1/src/Controllers/VerbasFlexController.php`)
  - Painel de Administração de Metas (`portal/modules/admin/metas-financeiro.php`, `portal/modules/admin/metas-marketing.php`, `portal/modules/admin/components/header.php`, `portal/modules/admin/components/footer.php`)
  - Gateway de Rotas API (`index.php`)

---

## 1. O que foi feito
1. **Verbas Flex:**
   - Padronização rigorosa da nomenclatura de **"CEDEU" para "ENVIOU"** em rankings, balanço consolidado e extrato do colaborador.
   - Suporte nativo à consolidação de todas as filiais liberadas (`filial = 0`).
   - Cards de KPIs principais enriquecidos com `title` detalhado ao passar o mouse com explicação das regras e fórmulas.
   - Atualização completa do treinamento na base `sistema_manuais` (IDs 26 e 27) para versão 3.0 e geração de `docs/manual-verbas-flex.md`.
2. **Correção de Metas (Admin & Marketing):**
   - **Erro `fetchWithAuth is not defined` eliminado definitivamente:** Criação de bridge global de autenticação no `<head>` de `header.php` e no `footer.php` de admin.
   - **Correção do carregamento infinito em `metas-marketing.php`:**
     - Normalização das chamadas com query string em `apiMktFetch` e `apiFetch` para não duplicar `?` na URL.
     - Proteção defensiva no gateway `index.php` para separar `routePath` e query string.
     - Ajuste das datas no seletor de ciclos rápidos usando hora local (`formatLocalDate`) eliminando discrepância de fuso horário UTC.
     - Cálculo robusto de progresso de cada meta a partir do JSON de `valores`.
     - Inicialização assíncrona desacoplada de `carregarTiposMkt()` no `DOMContentLoaded`.

---

## 2. Arquivos para Deploy FTP
- `index.php`
- `portal/modules/admin/components/header.php`
- `portal/modules/admin/components/footer.php`
- `portal/modules/admin/metas-financeiro.php`
- `portal/modules/admin/metas-marketing.php`
- `portal/modules/verbas-flex.php`
- `portal/assets/js/verbas-flex.js`
- `v1/src/Controllers/VerbasFlexController.php`
- `docs/manual-verbas-flex.md`
