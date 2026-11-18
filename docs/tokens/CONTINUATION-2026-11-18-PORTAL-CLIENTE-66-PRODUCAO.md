# CONTINUATION TOKEN — 2026-11-18

## Resumo da Entrega em Produção (Chamado #66 — Portal do Cliente & Parceiro)
- **Cloudflare Turnstile:** Integrado via `SecretVault` (`TURNSTILE_SITE_KEY`, `TURNSTILE_SECRET_KEY`), validado via `cURL` e compartilhado nas abas Entrar / Primeiro Acesso / Solicitar Cadastro.
- **2FA por E-mail & WhatsApp (Evolution API):**
  - SMTP cPanel corrigido (`SMTPOptions`, `Hostname`, `Sender`, `MAIL_PASS` via `SecretVault`).
  - Driver `evolution` adicionado em `WhatsAppService` + `SecretVault` (`EVOLUTION_API_URL`, `EVOLUTION_API_KEY`, `EVOLUTION_INSTANCE`), conectado via Cloudflare Tunnel (`https://whats.nutricionalbr.com` -> `localhost:8080`, Node 22 + PM2 + AutoStart no Windows).
  - Isenção de `/v1/portal-cliente/` em `CsrfMiddleware` (autenticação via Bearer JWT) e ajuste SSL cPanel (`CURLOPT_SSL_VERIFYPEER => false`) em `WhatsAppService`.
  - Testado e aprovado ao vivo em produção (`https://api.nutricionalbr.com/cliente`) com envio de código 2FA via WhatsApp e E-mail!
- **Portas de Entrada Separadas (Zero Confusão):**
  - **Clientes:** `/cliente` (`cliente/index.php` -> `portal-cliente.php?modo=cliente`)
  - **Representantes:** `/representante` (`representante/index.php` -> `portal-cliente.php?modo=representante`)
  - **Gestores / Admin / Usuários:** `/portal/login.php` (limpo, apenas equipe interna) -> módulo Portal do Cliente no menu.

## Lista Completa de Arquivos (Deploy FTP — Chamado #66)
- `v1/routes/api.php`
- `v1/src/Controllers/PortalClienteController.php`
- `v1/src/Middleware/JwtMiddleware.php`
- `v1/src/Middleware/CsrfMiddleware.php`
- `v1/src/Services/SecretVault.php`
- `v1/src/Services/WhatsAppService.php`
- `portal/modules/portal-cliente.php`
- `portal/assets/js/portal-cliente.js`
- `portal/login.php`
- `cliente/index.php`
- `representante/index.php`
- `portal/uploads/chamados/manual_portal_cliente_hierarquia_2fa_chamado_66.pdf`
