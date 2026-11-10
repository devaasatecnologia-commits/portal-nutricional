# Token de Continuidade — Módulo de Chamados & Suporte Inteligente (White-label & Dashboard Executivo)

## 1. Resumo Executivo das Entregas
1. **White-Label Completo de TI & Engenharia:**
   - Remoção de qualquer menção a nomes de IA ("Antigravity") ou avatares de robô (`fa-robot`) da interface, timeline e banco de dados.
   - Atribuição direta a **`MARCON`** com badges oficiais `DevOps / TI` e identidade visual de Engenharia (`fa-laptop-code`, `fa-code`, `fa-microchip`).
   - No modal de detalhes: botão atualizado para **"Copiar Dossiê Técnico para IDE"** (`copiarBriefingDevOps()`) e terminal intitulado **"Terminal de Engenharia / DevOps"**.
   - No PostgreSQL: chamados e operações de timeline higienizados com `usuario = 'MARCON'` e `atendente = 'MARCON'`.

2. **Novo Dashboard & Painel Executivo de KPIs (`modoExibicao = 'dashboard'`):**
   - **Cards de Topo Executivos:**
     - Taxa de Resolução (%) calculada dinamicamente com base nos chamados concluídos.
     - Fila Ativa de TI (chamados abertos e em análise técnica).
     - Alerta de Atenção Imediata (Incidentes Críticos em aberto).
     - Total de Incidentes Gerenciados na Base de Conhecimento.
   - **Painéis Gráficos e Distribuições:**
     - Barras de progresso proporcionais por Sistema Corporativo (PORTAL, MERCOS, ERP_EMA, COBLI, etc.).
     - Matriz de Criticidade & SLA (Crítica, Alta, Média, Baixa) com cartões coloridos.
     - Top Módulos com maior incidência de manutenção.
   - **Tabela Executiva de Entregas Recentes:**
     - Tabela das últimas soluções aplicadas em produção, com versão entregue (`v3.5`), atendente `MARCON` e botão para abrir o dossiê técnico.

3. **Arquitetura & Backend (`v1/src/Controllers/ChamadosController.php`):**
   - Rota `/v1/chamados/dashboard` enriquecida com agregação SQL direta de `por_sistema`, `por_criticidade`, `top_modulos` e `resolvidos_recentes`.

---

## 2. Arquivos para Deploy FTP (cPanel/Produção)
- `portal/modules/chamados.php`
- `portal/assets/js/chamados.js`
- `v1/src/Controllers/ChamadosController.php`

---

## 3. Estado dos Chamados Estratégicos no Banco de Dados
- **#30 [CRÍTICO]:** `ERP EMA: Saldo fantasma em (+) Qtd transfe na Transação 1003` -> Status 3 (Concluído por MARCON).
- **#31 [CRÍTICO]:** `EMA <-> MERCOS: Mapeamento da Arquitetura do Integrador (Filial 1)` -> Status 3 (Concluído por MARCON).
- **#32 [CRÍTICO]:** `PROJETO: Migracao Integrador Mercos ERP para o Portal Nutricional` -> Status 2 (Em Andamento / Roadmap 10/01/2027).
- **#33 [CRÍTICO]:** `PROJETO: Migracao e Aposentadoria do Portal DOX (Ema Studio)` -> Status 2 (Em Andamento).
- **#34 [CRÍTICO]:** `LOGISTICA: Conferencia de Descarregamento OC Modo Rapido` -> Status 3 (Concluído por MARCON, v3.5).
