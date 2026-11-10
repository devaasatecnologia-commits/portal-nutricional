# Token de Continuidade — Central de Chamados Multi-Sistemas com IA & Backlog Real (Onda 1)

**Data/Hora:** 10/11/2026 - 11:45
**Autor:** Antigravity (Tech Lead & Arquiteto Sênior)
**Status:** 100% Concluído e Validado

---

## 1. O que foi Concluído nesta Etapa
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
