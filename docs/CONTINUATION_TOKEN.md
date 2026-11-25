# TOKEN DE CONTINUIDADE ATUAL
Token ativo: `docs/tokens/CONTINUATION-2026-11-24-emergencia-csrf-flex.md`
Data de geração: 2026-11-25
Módulos principais:
- **Chamado #66 v1.4 (Portal do Cliente B2B & Contábil — Correção NCM/DANFE + Revisão Geral + Ajuste Loader Global):** NCM fiscal real (`classificacaofiscal`), DANFE completa (Duplicatas, ST, Transportador/Volumes, CST, Unid, Cidade/CEP da Filial), escopo Gestor via `vw_gestor_repre`, e `PortalLoader` restrito estritamente a ações do usuário (sem disparar em notificações/chat/timers).
- **Chamado #82 (Hotfix Emergencial CSRF / Verba Flex — Solicitante: TIAGO)** & **Chamado #80 v1.3 (Programa 365 Comercial T27):** já publicados e validados em produção hoje cedo (`api.nutricionalbr.com`).
- **Chamado #32 (Mercos Sandbox Homologação):** aguardando e-mail/transcrição da Mercos.

---

## Arquivos Alterados Agora para Deploy FTP (cPanel Produção)
1. **Loader Global Silencioso em Background (`config.js`):**
   - `portal/assets/js/config.js` *(só abre em ações reais do usuário e abertura inicial; ignora `/notificacoes`, `/chat/`, `/mensagens`, `/inatividade` e qualquer `setInterval` automático)*

2. **Portal do Cliente B2B v1.4 + NCM Contábil (`Chamado #66`):**
   - `v1/src/Controllers/PortalClienteController.php` *(NCM real via `classificacaofiscal`, Unidade, CST 3 dígitos, Duplicatas, Cidade/CEP/Fone Filial e `vw_gestor_repre`)*
   - `v1/src/Controllers/ContabilController.php` *(mesma correção de NCM real via `classificacaofiscal` e Unidade no espelho/relatório contábil)*
   - `portal/modules/portal-cliente.php` *(layout completo da DANFE com Duplicatas, Base/Valor ST, Transportador/Volumes, NCM/Unid no modal de itens e cache-busting independente)*
   - `portal/assets/js/portal-cliente.js` *(duplicatas na DANFE, 2FA pré-preenchido, busca por NF/Pedido em Boletos e Pedidos, zero `alert()`)*
   - `portal/uploads/chamados/chamado_66_portal_cliente_v14.pdf`
