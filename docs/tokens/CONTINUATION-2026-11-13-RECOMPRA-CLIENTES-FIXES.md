# Continuation Token — Correções Críticas, Chamados & Treinamento Recompra

**Data:** 13/11/2026 08:54  
**Status:** 100% Concluído e Documentado  
**Chamados Registrados no Banco:**
- **Chamado #52 (Concluído):** `[Recompra & Clientes] Correção de Erros no Console, HTTP 500 em Filiais, Filtro de Filiais Ativas e Raio-X de Notas Faturadas`
- **Chamado #53 (Concluído):** `[Portal Geral] Restringir Toast de Instalação do Aplicativo (PWA) Exclusivamente para Dispositivos Mobile`

---

## 1. Ajustes Realizados e Validados

### A. Módulo Inteligência de Recompra (`recompra-clientes.php` e `recompra-clientes.js`)
1. **Botão Treinamento & Manual no Desktop:**
   - Adicionado no cabeçalho desktop com destaque em amarelo corporativo (`bg-amber-400 hover:bg-amber-300 text-slate-900 font-black`), linkando diretamente para `/portal/modules/treinamentos.php?modulo=recompra_clientes`.
2. **Documentação Completa no Nutricional Academy (`sistema_manuais`):**
   - Manual (ID 94) atualizado com documentação técnica e estratégica completa:
     - Visão Geral & Finalidade Corporativa.
     - Hierarquia de Filtros (Filial -> Gestor -> Representante -> Região).
     - Matriz de Risco em 4 quadrantes (30-45d, 46-60d, 61-90d, +90d).
     - Regra de Negócio: Faturamento 12m e Média Mensal (12m).
     - Raio-X do Cliente e exportação CSV.
3. **Eliminação dos Erros de Console (Alpine.js null ref):**
   - Inicialização segura de `clienteDetalhes` e proteção com `<template x-if="clienteDetalhes">` no modal.
4. **Resolução do HTTP 500 ao filtrar filiais:**
   - Removido `cl.idfilial` inexistente na tabela `cliente` do ERP EMA em `VendasDashboardController::clientesRisco`.
5. **Filiais Ativas e Baixadas:**
   - Em `VendasCuboController::listarFiliais`, filtro refinado para `inativo = 'N' AND idfilial > 0 AND nome NOT ILIKE '%BAIXADA%'`, mantendo o escopo do usuário via `UserScope`.
6. **Raio-X de Recompra com Notas Fiscais Faturadas:**
   - Query adicionada em `VendasDashboardController::getClienteDetalhes` buscando as últimas 20 NFs faturadas (`status = 2` e `idtransacao = 1`), com número, data, peso e valor, e chaves `valor_historico`, `peso_historico` e `cidade_uf`.

### B. Blindagem do Toast de Instalação PWA
- Restrito exclusivamente a smartphones e tablets com blindagem tripla em CSS (`style.css`), HTML (`header.php`) e JavaScript (`header.php`).

---

## 2. Arquivos Modificados para Deploy FTP
- `v1/src/Controllers/VendasDashboardController.php`
- `v1/src/Controllers/VendasCuboController.php`
- `portal/modules/recompra-clientes.php`
- `portal/assets/js/recompra-clientes.js`
- `portal/assets/css/style.css`
- `portal/estrutura/header.php`
- `docs/tokens/CONTINUATION-2026-11-13-RECOMPRA-CLIENTES-FIXES.md`
- `docs/CONTINUATION_TOKEN.md`
