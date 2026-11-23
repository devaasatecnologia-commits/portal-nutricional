# TOKEN DE CONTINUIDADE ATUAL
Token ativo: `docs/tokens/CONTINUATION-2026-11-19-ETAPA2-DRP-SERVER.md`
Data de geração: 2026-11-23
Módulo principal: Chamado #70 (Gestão de Depósito / Desembarque / FIFO) & Chamado #80 (Programa 365 Comercial - T27)
Status: Chamados #70 e #80 concluídos, validados no `ema` e fechados (`status = 3`) com PDFs e manuais em `sistema_manuais`.

## Arquivos alterados/criados para Deploy FTP (cPanel Produção)
1. **Chamado #70 (Depósito, Desembarque & FIFO):**
   - `v1/src/Services/EnderecoItemService.php`
   - `v1/src/Controllers/DepositoController.php`
   - `v1/routes/api.php`
   - `portal/assets/js/gestao-deposito.js`
   - `portal/assets/js/desembarque.js`
   - `portal/uploads/chamados/chamado_70_deposito.pdf`
   - *(Banco `ema` já migrado e populado: `docs/deposito_enderecamento_migration.sql` -> `item_endereco`, `secao_enderecos`)*

2. **Chamado #80 (Programa 365 Comercial - T27):**
   - `v1/src/Services/Programa365Service.php`
   - `v1/src/Controllers/Programa365Controller.php`
   - `v1/routes/api.php`
   - `portal/modules/programa-365.php`
   - `portal/assets/js/programa-365.js`
   - `portal/uploads/chamados/chamado_80_programa_365_t27.pdf`
   - *(Banco `ema` já migrado: `docs/programa_365_migration.sql` -> `p365_*` + `sistema_modulos` `programa-365`)*
