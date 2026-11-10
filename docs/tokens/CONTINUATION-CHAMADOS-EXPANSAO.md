# Token de Continuidade — Central de Chamados Multi-Sistemas com IA & Backlog Real (Onda 1)

**Data/Hora:** 10/11/2026 - 14:04
**Autor:** Antigravity (Tech Lead & Arquiteto Sênior)
**Status:** Correção de Schema Aplicada! Colunas observacao e motivo alteradas para TEXT.

---

## 1. O que foi Concluído nesta Etapa
1. **Correção de Truncamento de Texto Longo (SQLSTATE[22001]):**
   - *Causa:* As colunas legadas `observacao` (`varchar(200)`) e `motivo` (`varchar(600)`) na tabela `chamado` estouravam erro quando o usuário digitava relatos detalhados.
   - *Correção no PostgreSQL:*
     - `ALTER TABLE chamado ALTER COLUMN observacao TYPE text;`
     - `ALTER TABLE chamado ALTER COLUMN motivo TYPE text;`
     - `ALTER TABLE chamado ALTER COLUMN solicitante TYPE varchar(80);`
     - `ALTER TABLE chamado ALTER COLUMN atendente TYPE varchar(80);`
   - *Validação:* Testada inserção de chamado com mais de 2.200 caracteres com sucesso absoluto (Status 200).

2. **Chamado #29 Concluído com Sucesso (`crons` v3.5):**
   - **Correção Crítica de Roteamento:** O card do módulo no menu apontava para `modules/crons.php` (inexistente, gerando 404). Criado o arquivo `portal/modules/crons.php` com redirecionamento limpo e atualizada a URL em `sistema_modulos` para `modules/admin/crons.php`.
   - **Permissões Granulares:** Rota protegida em `v1/routes/api.php` atualizada para aceitar `new ModulePermissionMiddleware(['admin', 'crons'])`, permitindo acesso com perfil específico.
   - **Painel & Telemetria:** Painel administrativo validado com 4 KPIs (10 jobs cadastrados, 9 ativos, 71 execuções concluídas hoje com zero falhas).
   - **Manual de Treinamento no BD:** Atualizado `sistema_manuais` (ID 28) para `v3.5` detalhando o dispatcher unificado (`/v1/cron/dispatcher`), testes dry-run e rotinas automáticas.
   - **Conclusão via CLI:** Registrada entrega técnica pelo conector Antigravity e status atualizado para 3 (Concluído).

2. **Chamado #2 Concluído com Sucesso (`transferencia-flex` v3.5):**
   - Checklist de 5 passos aplicado (interface, advisory locks, rate limit, manual ID 27 v3.5 e deploy).
   - **Checklist de 5 passos aplicado e homologado:**
     - *Passo 1 (Interface):* 4 KPIs com tooltips modernos flutuantes, cockpit de transferência em 3 colunas (DE -> VALOR -> PARA), atalhos rápidos de valores (+50, +100, etc.), cálculo de saldo pós-operação em tempo real e botão direto de "Treinamento & Manual".
     - *Passo 2 (Backend & Concorrência):* `pg_try_advisory_lock` contra transferências simultâneas concorrentes do mesmo emissor, estorno automático em caso de falha de comunicação entre pontas e auditoria transparente com registro do operador e IDs.
     - *Passo 3 (Resiliência):* Filtro estrito de representantes ativos no ERP e no Mercos, com respeito a `dash_filiais` e `dash_gestores`.
     - *Passo 4 (Manual no Banco):* Atualizado `sistema_manuais` (ID 27) para `v3.5` com os 4 KPIs descritos, travas de segurança e FAQs.
     - *Passo 5 (Encerramento & CLI):* Sintaxe PHP 100% validada (`php -l`) e chamado finalizado via conector Antigravity CLI com registro de resolução na timeline.

2. **Próximo da Fila:**
   - **Chamado #3:** `[Onda 1] [Movimentação de Flex] Integrar Histórico Auditado com Saldo Mercos Live e Gestores` (`movimentacao-flex`).
1. **Multi-Sistemas Corporativos Concluído no Backend e Banco de Dados:**
   - Adicionadas com sucesso as colunas `tipo_sistema` (VARCHAR 50), `subtipo_item` (VARCHAR 120) e `equipamento_detalhe` (VARCHAR 150) na tabela `chamado`.
   - Controller (`ChamadosController.php`) atualizado nos métodos `dashboard`, `listar`, `triarIa`, `criar` e `detalhes`.
   - Limpeza total de registros de testes legados.

2. **Motor de IA Triagem Multi-Sistemas (`ChamadosIaService.php`):**
   - Heurística avançada identificando 10 sistemas (`PORTAL`, `ERP_EMA`, `MERCOS`, `COBLI`, `LOGISOFT`, `EMAIL`, `SITE`, `EQUIPAMENTO`, `PLANILHAS`, `SERVIDOR`, `OUTROS`).
   - Geração de dossiê técnico especializado por tipo de sistema (ex: checagem de IP/spooler para impressoras, PABX para ramais, SEFAZ/A1 para Logisoft CTe/MDFe, regras fiscais de CFOP para o ERP Ema).
   - Sanitização estrita para compatibilidade com WIN1252.

3. **Frontend Completo (`portal/modules/chamados.php` & `portal/assets/js/chamados.js`):**
   - Seletor de sistemas no modal com botões interativos e campos dinâmicos por sistema.
   - Filtro por Sistema na barra superior.
   - Cards do Kanban e Tabela de Lista enriquecidos com badges de Sistema, Subtipo e Criticidade.
   - Respeito à hierarquia de permissões dos usuários ao listar módulos web.

4. **Povoamento Oficial do Backlog Real (As 4 Ondas do Plano Diretor):**
   - Removidos todos os chamados simulados/exemplos de testes.
   - Cadastrados exatamente os **29 módulos estratégicos do Plano Diretor**, divididos nas 4 Ondas:
     - **Onda 1 (Núcleo Financeiro, Verbas & Vendas):** `#1` Verbas Flex (Concluído), `#2` Transferência de Flex (Em Análise), `#3` Movimentação de Flex, `#4` Migração de Carteira, `#5` Controladoria Financeira, `#6` Análise de Carteira 360, `#7` Dashboard de Vendas, `#8` Cubo Vendas.
     - **Onda 2 (Logística & Operação Física):** `#9` Separação WMS, `#10` Carregamento, `#11` Monitor Logístico, `#12` Auditoria de Estoque, `#13` Estoque com Previsão, `#14` Inventário Geral, `#15` Transferência Matriz-Filial.
     - **Onda 3 (Frota, Expedição, Motoristas & Fiscal):** `#16` Gestão de Frotas, `#17` Embarques, `#18` Retorno de Carga, `#19` Acerto de Embarque, `#20` App do Motorista PWA, `#21` Conferência XML, `#22` Consulta Saldo.
     - **Onda 4 (Marketing, RH, Treinamentos & TI):** `#23` CRM Clientes 360, `#24` Metas de Marketing, `#25` Admin Marketing, `#26` Avaliação C.H.A.P.D, `#27` Plano de Ação PDI, `#28` Nutricional Academy, `#29` Motores Cron.
   - Cada chamado possui seu parecer técnico preliminar (dossiê) focado no Checklist de 5 passos do especialista.

5. **Manual de Usabilidade do Módulo Atualizado:**
   - Registrado em `sistema_manuais` (ID 93) com o guia dos 10 sistemas corporativos e da triagem de IA.

---

## 2. Arquivos para Deploy FTP
- `v1/src/Controllers/ChamadosController.php`
- `v1/src/Services/ChamadosIaService.php`
- `portal/modules/chamados.php`
- `portal/assets/js/chamados.js`
