# Token de Continuidade — Módulo de Chamados & Suporte Inteligente (White-label, Dashboard & Permissões Granulares)

## 1. Resumo Executivo das Entregas
1. **Chamado #35 [CONCLUÍDO]: Permissões Granulares por Usuário & Visão Restrita (`v3.6`):**
   - **Visão Restrita por Usuário Comum:**
     - Em `ChamadosController::listar()`, `dashboard()` e `detalhes()`, se o usuário não tiver `chamados_ver_todos = 'S'`, o sistema restringe a listagem estritamente aos chamados abertos por ele próprio (`idcliforemp = :uid OR solicitante = :user OR usuario = :user`).
     - Usuário comum acompanha em tempo real o status, o atendente técnico (`atendente`), a evolução na timeline e a solução aplicada.
   - **Filtragem de Módulos Web na Abertura:**
     - Ao abrir chamado do *Portal Nutricional*, o dropdown de módulos web é filtrado dinamicamente para exibir exclusivamente os módulos aos quais o usuário possui permissão concedida.
   - **Matriz de Permissões no Painel Admin (`portal/modules/admin/permissoes.php`):**
     - Adicionada a seção **"Políticas do Módulo de Chamados & Suporte"**:
       - Toggle de Visibilidade: "Visualizar Todos os Chamados da Empresa" (desmarcado por padrão, ativando a visão restrita).
       - Política de Tipos Permitidos: "Pode abrir qualquer tipo de chamado" ou seleção seletiva dos sistemas autorizados (`PORTAL`, `EQUIPAMENTO`, `ERP_EMA`, `MERCOS`, `COBLI`, `LOGISOFT`, `EMAIL`, `SITE`, `PLANILHAS`, `SERVIDOR`, `OUTROS`).
     - No backend (`AdminController.php`), persistência das colunas `chamados_ver_todos` e `chamados_tipos_permitidos` na tabela `usuario`.
   - **Validação de Segurança na Criação:**
     - O backend valida no ato do salvamento se o tipo de chamado ou módulo informado é permitido para aquele perfil.

2. **Dashboard & Painel Executivo de KPIs (`modoExibicao = 'dashboard'`):**
   - Visão com KPIs de topo (Taxa de Resolução %, Fila Ativa, Críticos, Total Histórico), distribuição proporcional por sistema corporativo, matriz de criticidade & SLA, top módulos demandados e tabela de entregas recentes.

3. **White-Label Completo de TI:**
   - Remoção de nomes de IA e ícones de robô, padronizando a assinatura técnica e liderança em **`MARCON`** (`DevOps / TI`).

---

## 2. Arquivos para Deploy FTP (cPanel/Produção)
- `portal/modules/chamados.php`
- `portal/assets/js/chamados.js`
- `portal/modules/admin/permissoes.php`
- `v1/src/Controllers/ChamadosController.php`
- `v1/src/Controllers/AdminController.php`

---

## 3. Estado dos Chamados no Banco de Dados (PostgreSQL)
- **#30 [CRÍTICO]:** `ERP EMA: Saldo fantasma em (+) Qtd transfe na Transação 1003` -> Status 3 (Concluído por MARCON).
- **#31 [CRÍTICO]:** `EMA <-> MERCOS: Mapeamento da Arquitetura do Integrador (Filial 1)` -> Status 3 (Concluído por MARCON).
- **#32 [CRÍTICO]:** `PROJETO: Migracao Integrador Mercos ERP para o Portal Nutricional` -> Status 2 (Em Andamento / Roadmap 10/01/2027).
- **#33 [CRÍTICO]:** `PROJETO: Migracao e Aposentadoria do Portal DOX (Ema Studio)` -> Status 2 (Em Andamento).
- **#34 [CRÍTICO]:** `LOGISTICA: Conferencia de Descarregamento OC Modo Rapido` -> Status 3 (Concluído por MARCON, v3.5).
- **#35 [ALTA / EVOLUÇÃO]:** `[EVOLUCAO] Permissoes Granulares por Usuario no Modulo de Chamados & Visao Restrita` -> Status 3 (Concluído por MARCON, v3.6).
