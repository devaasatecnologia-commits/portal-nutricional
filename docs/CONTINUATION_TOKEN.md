# TOKEN DE CONTINUIDADE ATUAL
Token ativo: `docs/tokens/CONTINUATION-2026-11-19-ETAPA2-DRP-SERVER.md`
Data de geração: 2026-11-24
Módulo principal: Chamado #80 v1.3 (Programa 365 Comercial T27 + Loader Global do Portal) & Chamado #32 (Mercos Sandbox Homologação)
Status: Chamado #80 v1.3 concluído e validado no `ema` (`status = 3`) com PDF e manual v1.3 em `sistema_manuais`; Chamado #32 aguardando e-mail/transcrição da Mercos para envio da lista de entidades e liberação de Sandbox.

## Arquivos alterados/criados para Deploy FTP (cPanel Produção)
1. **Loader Global de Carregamento (Todo o Portal — `PortalLoader`):**
   - `portal/assets/js/config.js` *(barra superior animada + overlay `"Carregando dados, aguarde..."` em todas as chamadas `fetch`/`apiFetch` e navegações internas de módulos)*

2. **Chamado #80 v1.3 (Programa 365 Comercial - T27):**
   - `v1/src/Services/Programa365Service.php` *(data de chegada/importação do pedido `COALESCE(pp.data, p.data, n.dataemissao)` igual Mercos, 5 Faixas detalhadas e 52 PINs oficiais)*
   - `v1/src/Controllers/Programa365Controller.php` *(Auto-Soma inicial `participante_id = 0` por escopo e Modo Surpresa `ocultar_ranking_geral` / `liberar_ranking_gestor`)*
   - `v1/routes/api.php` *(rota `POST /v1/programa-365/admin/config-visibilidade`)*
   - `portal/modules/programa-365.php`
   - `portal/assets/js/programa-365.js`
   - `portal/uploads/chamados/chamado_80_programa_365_t27.pdf`

3. **Entregas anteriores do ciclo (caso ainda não subidas por FTP):**
   - **Chamado #70 (Depósito / FIFO):** `v1/src/Services/EnderecoItemService.php`, `v1/src/Controllers/DepositoController.php`, `portal/assets/js/gestao-deposito.js`, `portal/assets/js/desembarque.js`, `portal/uploads/chamados/chamado_70_deposito.pdf`
   - **Verba Flex Cloud:** `v1/src/Services/MercosFlexService.php`, `v1/src/Services/SecretVault.php`
   - **Chamado #32 (Dossiê Mercos):** `portal/uploads/chamados/chamado_32_manual_estrategico_mercos.pdf`
