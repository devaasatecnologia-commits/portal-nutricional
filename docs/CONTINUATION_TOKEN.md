# TOKEN DE CONTINUIDADE ATUAL
Token ativo: `docs/tokens/CONTINUATION-2026-11-24-emergencia-csrf-flex.md`
Data de geração: 2026-11-25 (10:15 BRT)
Módulos principais:
- **Chamado de Fotos HFS (#83 — solicitante ALAN):** porta `2053` sem acesso externo após a blindagem; fotos migradas para `https://fotos.nutricionalbr.com/fotos/` via Cloudflare Tunnel `Servidor-Principal` (`https://localhost:2053`, No TLS Verify). `window.FOTOS_BASE_URL` (config.js) + env `FOTOS_BASE_URL` (PHP) + fallback global de imagem.
- **Chamado #66 v1.5 (Portal Cliente B2B):** código de barras Code-128C da chave na DANFE + `GET /v1/portal-cliente/nota/{idfilial}/{idnfs}/xml` (XML original assinado → ponte opcional `NFE_XML_BRIDGE_*` → fallback nfeProc 4.00 do banco) + botões Baixar XML.
- **Chamado #82, #80 v1.3, #66 v1.4:** já em produção.

## PENDÊNCIAS DE PERÍMETRO (ação do Alan)
1. Remover no roteador a regra NAT **`Regra HTTPS 2053`** e manter a `2053` fechada para entrada externa no Firewall do Windows (`SERVER`). Só depois de subir o FTP e validar as fotos.
2. **DOX:** a `2053` NÃO será cedida. Responder à empresa pedindo: direção do fluxo, protocolo, IP(s) fixo(s) e dados trocados; então criar endpoint dedicado (`dox.nutricionalbr.com` + Service Token) ou usuário BD somente leitura com IP liberado. (O `ema` já tem `dox_bi_comercial_merge` e `vw_mat_dox_bi_vendas`.)

## Arquivos para Deploy FTP (sobrescrever)
**Fotos (15):** `portal/assets/js/config.js`, `auditoria-estoque.js`, `auditoria.js`, `carregamento.js`, `desembarque.js`, `estoque-previsao.js`, `monitor.js`, `pedido-amostra.js`, `portal-cliente.js`, `retorno.js`, `separacao.js`; `v1/src/Controllers/AmostraPedidoController.php`, `AuditoriaController.php`, `FluxoEcomerceController.php`, `ModuleConfigController.php`, `RetornoController.php`.
**Portal Cliente v1.5:** `v1/routes/api.php`, `v1/src/Controllers/PortalClienteController.php`, `portal/modules/portal-cliente.php`, `portal/assets/js/portal-cliente.js`.
**PDFs:** `portal/uploads/chamados/chamado_66_portal_cliente_v15.pdf` e `chamado_83_fotos_tunnel.pdf` (ou o número do chamado criado).
</Content>
