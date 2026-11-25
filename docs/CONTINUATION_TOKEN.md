# TOKEN DE CONTINUIDADE ATUAL
Token ativo: `docs/tokens/CONTINUATION-2026-11-24-emergencia-csrf-flex.md` (unificado com `docs/tokens/CONTINUATION-2026-11-19-ETAPA2-DRP-SERVER.md`)
Data de geração: 2026-11-25
Módulos principais:
- **Chamado #82 (Hotfix Emergencial CSRF / Verba Flex — Solicitante: TIAGO):** erro `403 CSRF token inválido` após `21:00 BRT` corrigido (`222ef73`), unificado na `main` local com todos os recursos de Novembro (`PortalLoader`, B2B `/v1/portal-cliente/` e 2FA WhatsApp Admin), documentado com PDF e sincronizado via `git push origin main`.
- **Chamado #80 v1.3 (Programa 365 Comercial T27 + Loader Global do Portal):** Auto-Soma inicial, faturamento pela data de chegada/importação do pedido (`COALESCE(pp.data, p.data, n.dataemissao)` igual Mercos), Modo Surpresa no Ranking Geral, 52 PINs e `PortalLoader` global.
- **Chamado #32 (Mercos Sandbox Homologação):** aguardando e-mail/transcrição da Mercos para envio da lista de entidades e liberação de Sandbox.

---

## Arquivos Obrigatórios para Deploy FTP Hoje (cPanel Produção)

> **ATENÇÃO CRÍTICA DE SINCRONIA:** Como o hotfix de ontem à noite via celular (`AndFTP`) partiu da `origin/main` anterior (26/10), é **obrigatório reenviar hoje os 3 arquivos do item 1** já unificados para manter o hotfix do CSRF **junto** com o `PortalLoader`, a isenção B2B (`/v1/portal-cliente/`) e o 2FA WhatsApp do Admin!

1. **Segurança CSRF Unificada + Loader Global do Portal (Chamado #82 + #80):**
   - `v1/src/Middleware/CsrfMiddleware.php` *(CSRF `America/Sao_Paulo` + `UTC` + isenção `/v1/portal-cliente/` e `/v1/frota/`)*
   - `portal/assets/js/config.js` *(CSRF `America/Sao_Paulo` + `uid` via JWT + `PortalLoader` global `"Carregando dados, aguarde..."`)*
   - `portal/modules/admin/components/footer.php` *(CSRF `America/Sao_Paulo` + 2FA WhatsApp administrativo)*
   - `portal/uploads/chamados/chamado_82_hotfix_csrf_flex.pdf`

2. **Chamado #80 v1.3 (Programa 365 Comercial - T27):**
   - `v1/routes/api.php`
   - `v1/src/Services/Programa365Service.php`
   - `v1/src/Controllers/Programa365Controller.php`
   - `portal/modules/programa-365.php`
   - `portal/assets/js/programa-365.js`
   - `portal/uploads/chamados/chamado_80_programa_365_t27.pdf`

3. **Entregas anteriores da semana (caso ainda não subidas por FTP):**
   - **Chamado #70 (Depósito / FIFO):** `v1/src/Services/EnderecoItemService.php`, `v1/src/Controllers/DepositoController.php`, `portal/assets/js/gestao-deposito.js`, `portal/assets/js/desembarque.js`, `portal/uploads/chamados/chamado_70_deposito.pdf`
   - **Verba Flex Cloud:** `v1/src/Services/MercosFlexService.php`, `v1/src/Services/SecretVault.php`
   - **Chamado #32 (Dossiê Mercos):** `portal/uploads/chamados/chamado_32_manual_estrategico_mercos.pdf`
