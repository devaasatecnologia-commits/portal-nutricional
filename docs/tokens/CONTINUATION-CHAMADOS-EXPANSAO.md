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

4. **Povoamento do Backlog Real (Onda 1 do Plano Diretor) e Exemplos Corporativos:**
   - 10 chamados reais inseridos:
     - `#1`: `[PORTAL] [Verbas Flex] Homologação e Deploy FTP de Bonificações e Extrato Live Mercos` (Concluído)
     - `#2`: `[PORTAL] [Transferência de Flex] Padronização de Cards KPIs e Alinhamento com Auditoria Mercos` (Em Análise)
     - `#3`: `[PORTAL] [Movimentação de Flex] Integrar Histórico Auditado com Saldo Mercos Live e Gestores` (Em Correção)
     - `#4`: `[PORTAL] [Migração de Carteira] Blindagem de Duplicidade e Transição Segura de Clientes` (Aberto)
     - `#5`: `[PORTAL] [Análise de Carteira] Otimização de Performance e Auditoria 360 de Inadimplência` (Aberto)
     - `#6`: `[PORTAL] [Dashboard de Vendas & Cubo] Tooltips Flutuantes Modernos e Otimização de CTEs` (Aberto)
     - `#7`: `[EQUIPAMENTO] [Impressora] Impressora Térmica de Etiquetas da Expedição com atolamento no rolo` (Aberto)
     - `#8`: `[ERP_EMA] [Faturamento] Erro de validação de CFOP ao faturar pedido de transferência intercompany` (Aberto)
     - `#9`: `[LOGISOFT] [MDF-e] Rejeição SEFAZ de MDFe por CTe anterior não encerrado` (Aberto)
     - `#10`: `[EQUIPAMENTO] [Ramal] Ramal 204 da Sala de Vendas mudo para ligações externas` (Aberto)

5. **Manual de Usabilidade do Módulo Atualizado:**
   - Registrado em `sistema_manuais` (ID 93) com o guia dos 10 sistemas corporativos e da triagem de IA.

---

## 2. Arquivos para Deploy FTP
- `v1/src/Controllers/ChamadosController.php`
- `v1/src/Services/ChamadosIaService.php`
- `portal/modules/chamados.php`
- `portal/assets/js/chamados.js`
