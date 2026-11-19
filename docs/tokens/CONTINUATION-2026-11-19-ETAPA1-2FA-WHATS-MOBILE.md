# CONTINUATION TOKEN — 2026-11-19 (ETAPA 1)

## Resumo da Entrega (Chamado #74 + Evolução v1.2 do Chamado #66)
1. **2FA por WhatsApp Unificado em Todo o Portal Interno (`usuario_seguranca.tipo_2fa`):**
   - Coluna `tipo_2fa` (`totp` | `whatsapp` | `ambos`) criada no PostgreSQL `ema` e integrada em `SegurancaUsuario::config()` e `docs/segredos_migration.sql`.
   - `AuthController::login()` e `portal/login.php` enviam/validam códigos OTP de 6 dígitos via Evolution API (`https://whats.nutricionalbr.com`) no login de Admins, Gestores e Usuários internos (modos `whatsapp` automático e `ambos` com envio sob demanda e cooldown de 60s).
   - `AdminSegredosController`: `statusWhatsapp()` agora retorna `tem_2fa` e `tipo_2fa`; admins configurados com `tipo_2fa = 'whatsapp'` e sem TOTP validam ações sensíveis via WhatsApp sem perder a dupla checagem TOTP + WhatsApp para quem tem autenticador ativo.
   - Rotas `GET /v1/auth/whatsapp/status` (nova) + `POST /v1/auth/whatsapp/enviar` e `Meu2faController::status()` liberadas para qualquer perfil autenticado, permitindo que Usuários e Gestores não-admin acompanhem seu status em `/portal/modules/2fa.php`.

2. **Gestão de Representantes B2B no Painel Admin (`Admin > Segredos & 2FA`):**
   - Criados endpoints `GET /v1/admin/seguranca-b2b` e `POST /v1/admin/seguranca-b2b/{id}` em `AdminSegurancaUsuariosController`, além de selos `ADMIN`, `GESTOR` e `REP` na tabela de usuários internos e seleção de `Método 2FA` tanto em `segredos.php` quanto no modal de `usuarios.php`.
   - Painel **Representantes & Contas B2B** em `portal/modules/admin/segredos.php` lista contas `b2b_portal_contas` e todos os Representantes ativos do ERP (`cliforemp`), com atalho "Usar ERP" para preencher o telefone, escolha de canal (`whatsapp` / `email`) e pré-provisionamento automático em `b2b_portal_contas`.

3. **Versão Exclusiva Mobile (Cliente, Representante, Gestor e Admin) & Higiene Técnica:**
   - Removido `ALTER TABLE` de runtime em `PortalClienteController::atualizarPreferencias2fa()`.
   - `portal/modules/portal-cliente.php` e `portal/assets/js/portal-cliente.js` receberam interface mobile dedicada (`md:hidden`) mantendo as tabelas intactas no desktop (`hidden md:block`): banner prioritário de boletos pendentes/vencidos, grade rápida 4 atalhos, cards individuais para Boletos / Notas Fiscais / Pedidos, dados cadastrais colapsáveis, barra inferior fixa estilo app, toast não-bloqueante ao copiar códigos e modais fullscreen.
   - `portal/index.php`: adicionados atalhos na sidebar para `Carteira & Boletos` (`portal-cliente.php?sessao=portal`) e `Segurança 2FA` (`2fa.php`).

## Chamados & Manuais Atualizados
- **Chamado #74 (`Concluído`):** `[Seguranca 2FA WhatsApp & Responsivo Mobile] ...` (solicitante/atendente `ALAN`, PDF `portal/uploads/chamados/manual_2fa_whatsapp_portal_mobile_chamado_74.pdf`, Manual `admin` v2.1).
- **Chamado #66 (`Em Correção` — aguardando teste final `/representante`):** operação `#15` registrada + PDF atualizado (`portal/uploads/chamados/manual_portal_cliente_hierarquia_2fa_chamado_66.pdf`) + Manual `portal-cliente` v1.2.

## Lista Exata de Arquivos para Deploy FTP (cPanel)
- `v1/routes/api.php`
- `v1/src/Services/SegurancaUsuario.php`
- `v1/src/Controllers/AuthController.php`
- `v1/src/Controllers/AdminSegredosController.php`
- `v1/src/Controllers/AdminSegurancaUsuariosController.php`
- `v1/src/Controllers/Meu2faController.php`
- `v1/src/Controllers/PortalClienteController.php`
- `portal/login.php`
- `portal/index.php`
- `portal/modules/2fa.php`
- `portal/modules/portal-cliente.php`
- `portal/assets/js/portal-cliente.js`
- `portal/modules/admin/segredos.php`
- `portal/modules/admin/usuarios.php`
- `portal/modules/admin/components/footer.php`
- `portal/uploads/chamados/manual_portal_cliente_hierarquia_2fa_chamado_66.pdf`
- `portal/uploads/chamados/manual_2fa_whatsapp_portal_mobile_chamado_74.pdf`

## Banco de Dados (já aplicado no servidor Postgres `ema`)
- `ALTER TABLE usuario_seguranca ADD COLUMN IF NOT EXISTS tipo_2fa VARCHAR(20) NOT NULL DEFAULT 'totp';` (documentado em `docs/segredos_migration.sql`).

## Próximo Passo (Etapa 2)
- Acesso administrativo ao servidor Windows `SERVER` (`200.53.15.132`) / túnel seguro para auditoria de processos, plano formal DRP/Backup anti-ransomware (`ema` 86 GB + `evolution` + `DE_PARA_MERCOS.FDB` + HFS + segredos) e monitoramento automático no Cron.
