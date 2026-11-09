# Continuidade — Módulo Financeiro: Metas Personalizadas, Gestão Admin e Histórico Temporal

Data: 09/11/2026
Módulo: Financeiro & Controladoria (`portal/modules/financeiro.php`, `portal/modules/admin/metas-financeiro.php`)

---

## 1. Contexto e Motivação
- Criação do ecossistema de **Metas Financeiras Personalizadas** (no mesmo padrão das Metas do Marketing), abrangendo:
  - Inadimplência Geral (IAG %)
  - Atraso Crítico +30d (IAP %)
  - Taxa de Recuperação de Crédito (%)
  - Perda Potencial >60d (%)
  - Carteira Vencida Máxima (R$)
- Implementação da **Área Admin de Metas Financeiras** (`portal/modules/admin/metas-financeiro.php`), com CRUD completo, filtros e badges.
- Reformulação do **Histórico de Performance & Auditoria Temporal** com filtros de períodos rápidos (`Hoje`, `7 dias`, `Este Mês`, `Mês Passado`, `30 dias`, `Este Ano`, `Todos`, `Personalizado`), filtro por dia da semana (`Todos os Dias` ou dia específico) e cards de **Auto Soma & Médias Apuradas** (Média Simples, Média Ponderada por Volume, Auto Soma de Vencidos, Média Diária de Exposição e Meta Corporativa com linha de referência no gráfico).
- Atualização do Manual e Treinamento do Financeiro (ID 19 em `sistema_manuais`) para a Versão 3.0.

---

## 2. Arquitetura e Alterações Realizadas

### A. Banco de Dados (PostgreSQL)
- Tabela `fin_metas` criada via migration:
  - Campos: `id`, `tipo_indicador`, `titulo`, `descricao`, `idfilial`, `valor_meta`, `direcao` (`menor_melhor` / `maior_melhor`), `tolerancia_alerta`, `data_inicio`, `data_fim`, `status` (`ativa`, `pausada`, `concluida`, `cancelada`), `created_by`, `created_at`, `updated_at`.
  - Migration salva em: `docs/migrations/20261109_create_fin_metas.sql`.

### B. Backend API (`v1/src/Controllers/FinanceiroController.php` & `v1/routes/api.php`)
- Novas rotas protegidas em `/v1/financeiro`:
  - `GET / POST /v1/financeiro/metas` (`getMetas`): lista metas com filtros de status, tipo e filial.
  - `POST /v1/financeiro/metas/salvar` (`salvarMeta`): cria ou edita metas financeiras.
  - `POST /v1/financeiro/metas/excluir` (`excluirMeta`): exclusão de meta com integridade.
  - `GET / POST /v1/financeiro/metas/progresso` (`getMetasComProgresso`): calcula o valor realizado atual e o percentual de atingimento da meta com semáforo (Verde / Amarelo / Vermelho).
- Endpoint `getHistoricoKpi` aprimorado:
  - Aceita `periodo` rápido, intervalo `data_inicio` / `data_fim`, `dia_semana` (`todos` ou 0-6), `tipo` e `filtro_id`.
  - Retorna `serie` (pontos no tempo), `resumo` (auto soma e médias aritméticas/ponderadas) e `meta` (meta de referência ativa com traço horizontal no Chart.js).

### C. Telas do Portal & Frontend
- **Nova Tela Admin:** `portal/modules/admin/metas-financeiro.php` com interface completa para criação, alteração, pausar/ativar e exclusão de metas financeiras. Foco padrão em **Metas Ativas** com pílulas de status rápidas e cards clicáveis.
- **Nova Tela Admin Marketing:** `portal/modules/admin/metas-marketing.php` integrando a gestão de metas e alimentação do marketing ao painel administrativo.
- **Hub Superior de Governança de Metas:** Alternância instantânea entre Metas do Financeiro e Metas do Marketing no topo da área Admin.
- **Menu Lateral do Admin (`portal/modules/admin/components/sidebar.php`):** Links para `Metas do Financeiro` e `Metas do Marketing`.
- **Controle de Acesso:** Botão "GERENCIAR METAS" em `portal/modules/financeiro.php` renderizado estritamente para administradores (com guarda defensiva de redirecionamento no Admin).
- **Dashboard Financeiro (`portal/modules/financeiro.php`):**
  - Botão de acesso rápido "GERENCIAR METAS" no header (apenas para Admin).
  - Seção executiva "METAS CORPORATIVAS ATIVAS & VIGENTES" com barras de progresso e semáforos, filtrando rigorosamente apenas metas ativas no período atual.
  - Nova área "HISTÓRICO & AUDITORIA TEMPORAL (IAG / IAP / RECUPERAÇÃO)" com botões de períodos rápidos, inputs para datas personalizadas e os 6 cards de Auto Soma e Médias Apuradas.
- **Script Frontend (`portal/assets/js/financeiro.js`):**
  - Implementados `verificarPermissaoAdmin()`, `loadMetasProgresso()`, `setPeriodoHistorico()`, `togglePeriodoPersonalizado()`, `aplicarDatasPersonalizadas()`.
  - Chart.js atualizado para desenhar a linha pontilhada da meta corporativa como guia visual direto.

### D. Separação de Metas Marketing (Portal vs Admin) e Alimentação Rápida
- **Portal Comum (`portal/modules/marketing/metas.php`):**
  - Transformado em cockpit exclusivo para **ALIMENTAÇÃO DIÁRIA DE METAS**.
  - Exibe na cara **apenas as metas ativas vigentes** (`filtroStatus: 'ativa'`).
  - Painel de **Entrada Rápida / Alimentação Diária**:
    - Seleção de data (`Hoje`, `Ontem`, input nativo).
    - Tabela direta com metas ativas, valores acumulados e inputs para cada meta.
    - Botão único `[ Salvar Todos os Lançamentos ]` com gravação em lote.
  - Removidos modais e banners pesados de calibração/criação do portal comum.
  - Link de atalho `⚙️ ADMINISTRAR METAS` exclusivo para administradores no header.
- **Admin de Metas Marketing (`portal/modules/admin/metas-marketing.php`):**
  - Centraliza todo o gerenciamento: CRUD de metas, modelos/tipos, vigência, status e projeção inteligente.
  - Exibição inicial focada em **Metas Ativas**.
  - **Banner de Projeção Inteligente de Novo Ciclo (`#banner-projecao-ciclo`):**
    - Só aparece aberto na cara automaticamente quando as metas do ciclo atual **já venceram** (`data_fim < hoje` ou sem ativas).
    - Se houver metas ativas vigentes em andamento, o banner fica recolhido e um botão elegante `[ ✨ Projeção de Ciclo ]` fica disponível no topo para acionamento sob demanda.
    - Criação de novo ciclo em 1 clique ou via modal de calibração manual (`#modalAjusteProjecaoAdmin`).
- **Backend (`v1/src/Controllers/MetaBuilderController.php`):**
  - Método `alimentarMeta` atualizado para receber tanto `id_meta_instancia` quanto `id_meta`, garantindo compatibilidade total com os lançamentos em lote do portal e admin.

---

## 3. Validação Executada
- `php -l portal/modules/financeiro.php` -> Sintaxe OK.
- `php -l portal/modules/admin/metas-financeiro.php` -> Sintaxe OK.
- `php -l portal/modules/admin/metas-marketing.php` -> Sintaxe OK.
- `php -l portal/modules/marketing/metas.php` -> Sintaxe OK.
- `php -l portal/modules/admin/components/sidebar.php` -> Sintaxe OK.
- `php -l v1/src/Controllers/FinanceiroController.php` -> Sintaxe OK.
- `php -l v1/src/Controllers/MetaBuilderController.php` -> Sintaxe OK.
- `php -l v1/routes/api.php` -> Sintaxe OK.
- `node -c portal/assets/js/financeiro.js` -> Sintaxe OK.
- Banco de Dados: `sistema_manuais` (ID 19) atualizado para Versão 3.1.
- Documento de Treinamento gerado em `docs/manual-financeiro-metas.md`.

---

## 4. Arquivos para Deploy FTP
1. `v1/routes/api.php`
2. `v1/src/Controllers/FinanceiroController.php`
3. `v1/src/Controllers/MetaBuilderController.php`
4. `portal/modules/financeiro.php`
5. `portal/assets/js/financeiro.js`
6. `portal/modules/marketing/metas.php`
7. `portal/modules/admin/metas-financeiro.php`
8. `portal/modules/admin/metas-marketing.php`
9. `portal/modules/admin/components/sidebar.php`
10. `docs/migrations/20261109_create_fin_metas.sql`
11. `docs/manual-financeiro-metas.md`
12. Tabela `fin_metas` no PostgreSQL
13. Tabela `sistema_manuais` (registro ID 19 atualizado)
