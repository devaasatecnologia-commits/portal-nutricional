# Resumo de Continuidade — Módulo Movimentação de Verba Flex (Mercos)

## 1. Contexto e Demanda
- **Objetivo:** Criar um módulo novo e urgente para movimentação manual de saldo Flex de representantes comerciais na API Mercos (`/saldo_flex`), permitindo:
  1. Adicionar saldo (Crédito direto) com observação obrigatória.
  2. Remover saldo (Débito) validando saldo suficiente no representante.
  3. Cobertura opcional via Gestor da filial (Checkbox "Tirar saldo do gestor"):
     - Se marcado e o representante não possuir saldo suficiente (ou estiver zerado/negativo), a diferença necessária é retirada do Gestor da filial como empréstimo.
     - Filial 1 (SC): Gestor `12335` (TIAGO MOV).
     - Filial 6 (PR): Gestor `15816` (TALES FERNANDO DE JESUS BINDE).
     - Regra de 3 etapas no Mercos:
       * Etapa 1: Gestor `-diferenca` (`"Emprestimo para o representante {nome} para movimentação : {obs}"`).
       * Etapa 2: Representante `+diferenca` (`"Emprestimo para movimentação : {obs}"`).
       * Etapa 3: Representante `-valor` (`"{obs}"`).

---

## 2. Implementações Realizadas
- **Banco de Dados (PostgreSQL):**
  - Tabela `movimentacoes_flex_mercos_log` criada com índices de performance para auditoria e histórico completo.
  - Registro do módulo `movimentacao-flex` na tabela `sistema_modulos` (Ordem 93, Setor Comercial).
- **Backend API:**
  - Controller `v1/src/Controllers/FlexMovimentacaoController.php`:
    - `GET /v1/flex-movimentacao/contexto`
    - `GET /v1/flex-movimentacao/representantes`
    - `GET /v1/flex-movimentacao/saldo`
    - `GET /v1/flex-movimentacao/historico`
    - `POST /v1/flex-movimentacao/movimentar`
  - Rotas registradas em `v1/routes/api.php` protegidas por `ModulePermissionMiddleware(['movimentacao-flex', 'transferencia-flex', 'verbas-flex'])`.
- **Frontend Portal:**
  - `portal/modules/movimentacao-flex.php`: Interface modular moderna (Hero Header, Cards de KPI, Seletor de Representante, Cockpit de Movimentação, Box do Gestor, Pré-visualização Dinâmica e Abas de Equipe e Histórico).
  - `portal/assets/js/movimentacao-flex.js`: Lógica client-side com validação reativa, simulação das etapas em tempo real, formatação monetária e confirmação via SweetAlert2.
  - Abas de navegação cruzada adicionadas em `verbas-flex.php` e `transferencia-flex.php`.

---

## 3. Documentação & Manual
- **Documentação Técnica Padrão:** `docs/modulos/MOD-MOVIMENTACAO-FLEX.md` criada com o padrão arquitetural corporativo da Nutricional (Objetivo, Diagrama Mermaid, Regras e Fórmulas, Tabela de Auditoria, Endpoints API e Manual Passo a Passo).
- **Manual Integrado na Interface:** Modal "Manual & Regras" integrado nativamente ao cabeçalho de `portal/modules/movimentacao-flex.php` para consulta rápida do operador.

---

## 4. Arquivos Modificados / Criados para Deploy FTP
- `v1/src/Controllers/FlexMovimentacaoController.php` (NOVO)
- `portal/modules/movimentacao-flex.php` (NOVO)
- `portal/assets/js/movimentacao-flex.js` (NOVO)
- `docs/modulos/MOD-MOVIMENTACAO-FLEX.md` (NOVO)
- `v1/routes/api.php`
- `portal/modules/verbas-flex.php`
- `portal/modules/transferencia-flex.php`

