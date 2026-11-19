# CONTINUATION TOKEN — 2026-11-19 (ETAPA 1)

## Resumo da Entrega (Chamado #74 + Evolução v1.2 do Chamado #66)
1. **2FA por WhatsApp Unificado em Todo o Portal Interno (`usuario_seguranca.tipo_2fa`):**
   - Coluna `tipo_2fa` (`totp` | `whatsapp` | `ambos`) criada no PostgreSQL `ema` e integrada em `SegurancaUsuario::config()` e `docs/segredos_migration.sql`.
   - `AuthController::login()` e `portal/login.php` enviam/validam códigos OTP de 6 dígitos via Evolution API (`https://whats.nutricionalbr.com`) no login de Admins, Gestores e Usuários internos (modos `whatsapp` automático e `ambos` com envio sob demanda e cooldown de 60s).
   - `AdminSegredosController`: `statusWhatsapp()` agora retorna `tem_2fa` e `tipo_2fa`; admins configurados com `tipo_2fa = 'whatsapp'` e sem TOTP validam ações sensíveis via WhatsApp sem perder a dupla checagem TOTP + WhatsApp para quem tem autenticador ativo.
   - Rotas `GET /v1/auth/whatsapp/status` (nova) + `POST /v1/auth/whatsapp/enviar` e `Meu2faController::status()` liberadas para qualquer perfil autenticado, permitindo que Usuários e Gestores não-admin acompanhem seu status em `/portal/modules/2fa.php`.

2. **Gestão de Representantes no Admin (2FA + Criação de Login + Permissões de Módulos + Hierarquia de Carteira Própria `dash_representantes`):**
   - Coluna `usuario.dash_representantes` (`VARCHAR(500)`) criada no PostgreSQL `ema` e integrada em `UserScope::resolverRepresentantes()`.
   - Em `Admin > Usuários` (`portal/modules/admin/usuarios.php`), o botão **`+ Novo Usuário / Representante`** permite selecionar qualquer Representante Comercial ativo do ERP (`GET /v1/admin/representantes`), criar seu login no Portal (`POST /v1/admin/usuarios/criar` com `permiteportal = 'S'` e `permitepex = 'N'`, sincronizando também a senha em `b2b_portal_contas`) e abrir `Admin > Permissões de Módulos` (`portal/modules/admin/permissoes.php`).
   - Tanto em `Admin > Usuários` quanto em `Admin > Permissões de Módulos`, a seção **Representantes Vinculados (Carteira de Clientes)** permite definir a hierarquia do representante seguindo estritamente os seus próprios clientes (`cl.idvendedor` / `idvendrepre`) em todos os módulos comerciais (`PortalClienteController`, `VendasDashboardController`, `VendasCuboController`, `AnaliseCarteiraController`, `MarketingController`, `VerbasFlexController`, `AmostraPedidoController`).
   - Painel **Representantes & Contas B2B** em `portal/modules/admin/segredos.php` (`GET /v1/admin/seguranca-b2b`, `POST /v1/admin/seguranca-b2b/{id}`) gerencia o 2FA WhatsApp/E-mail de representantes e clientes B2B.

3. **Entrada Direta (SSO) no Portal do Cliente B2B & Versão Exclusiva Mobile:**
   - `portal/assets/js/portal-cliente.js` (`init()`) agora entra direto no módulo quando o usuário já está logado no Portal Interno (`authToken` de Admin, Gestor ou Representante) ou possui `b2bToken` ativo, sem parar na tela de login. Se um usuário interno ou representante digitar seu `username` + senha na tela de login do módulo, `PortalClienteController::login()` e `verificar2fa()` autenticam e sincronizam ambas as sessões.
   - Removido `ALTER TABLE` de runtime em `PortalClienteController::atualizarPreferencias2fa()`.
   - Interface mobile dedicada (`md:hidden`) em `portal/modules/portal-cliente.php` mantendo as tabelas no desktop (`hidden md:block`): banner de boletos pendentes/vencidos, grade rápida de 4 atalhos, cards individuais para Boletos / Notas Fiscais / Pedidos, dados cadastrais colapsáveis, barra inferior fixa estilo app, toast não-bloqueante e modais fullscreen.
4. **UX Cliente v1.3, Integração CRM ↔ Portal B2B e Constituição da IA (`NUT-CORE-001`):**
   - Boletos exibem NF (`numeronf` clicável para DANFE) e Pedido (`pedidos`); Notas Fiscais e Pedidos exibem vínculo mútuo, destaque roxo `BONIFICAÇÃO` e vermelho/rosa `CANCELADA` (excluindo NFs canceladas do `total12m`); Pedidos em aberto bloqueados exibem aviso laranja `"Em Análise Financeiro — Qualquer dúvida entrar em contato"` + WhatsApp Financeiro.
   - Botões WhatsApp (Financeiro e Representante) enviam mensagem contextualizada com código do cliente, razão social e nº do documento.
   - Integração bidirecional CRM (`portal/modules/marketing/clientes.php`) ↔ Portal B2B (`portal-cliente.php?sessao=portal&idcliente={id}`), restrita a perfis internos (`escopo.perfil !== 'CLIENTE'`).
   - Formalizadas `docs/CONSTITUICAO_IA_NUTRICIONAL.md` e `docs/tokens/NUT-CORE-001.md` (com Artigo 10º de portabilidade/sucessão da conta pessoal para conta corporativa da Nutricional e `ADR-001`..`ADR-004`).

## Chamados & Manuais Atualizados
- **Chamado #74 (`Concluído`):** `[Seguranca 2FA WhatsApp & Responsivo Mobile] ...` (solicitante/atendente `ALAN`, 5 operações registradas, PDF `portal/uploads/chamados/manual_2fa_whatsapp_portal_mobile_chamado_74.pdf`, Manual `admin` v2.1).
- **Chamado #66 (`Em Correção` — aguardando teste final `/representante`):** operação `#16` registrada + PDF atualizado (`portal/uploads/chamados/manual_portal_cliente_hierarquia_2fa_chamado_66.pdf` v1.3) + Manual `portal-cliente` v1.3.

## Lista Exata de Arquivos para Deploy FTP (cPanel)
- `v1/routes/api.php`
- `v1/src/Utils/UserScope.php`
- `v1/src/Services/SegurancaUsuario.php`
- `v1/src/Controllers/AdminController.php`
- `v1/src/Controllers/AuthController.php`
- `v1/src/Controllers/AdminSegredosController.php`
- `v1/src/Controllers/AdminSegurancaUsuariosController.php`
- `v1/src/Controllers/Meu2faController.php`
- `v1/src/Controllers/PortalClienteController.php`
- `v1/src/Controllers/VendasDashboardController.php`
- `v1/src/Controllers/VendasCuboController.php`
- `v1/src/Controllers/AnaliseCarteiraController.php`
- `v1/src/Controllers/MarketingController.php`
- `v1/src/Controllers/VerbasFlexController.php`
- `v1/src/Controllers/AmostraPedidoController.php`
- `portal/login.php`
- `portal/index.php`
- `portal/modules/2fa.php`
- `portal/modules/portal-cliente.php`
- `portal/modules/marketing/clientes.php`
- `portal/assets/js/portal-cliente.js`
- `portal/modules/admin/segredos.php`
- `portal/modules/admin/usuarios.php`
- `portal/modules/admin/permissoes.php`
- `portal/modules/admin/components/footer.php`
- `portal/uploads/chamados/manual_portal_cliente_hierarquia_2fa_chamado_66.pdf`
- `portal/uploads/chamados/manual_2fa_whatsapp_portal_mobile_chamado_74.pdf`

## Banco de Dados (já aplicado no servidor Postgres `ema`)
- `ALTER TABLE usuario_seguranca ADD COLUMN IF NOT EXISTS tipo_2fa VARCHAR(20) NOT NULL DEFAULT 'totp';`
- `ALTER TABLE usuario ADD COLUMN IF NOT EXISTS dash_representantes VARCHAR(500) DEFAULT NULL;`

## Próximo Passo (Etapa 2)
- Acesso administrativo ao servidor Windows `SERVER` (`200.53.15.132`) / túnel seguro para auditoria de processos, plano formal DRP/Backup anti-ransomware (`ema` 86 GB + `evolution` + `DE_PARA_MERCOS.FDB` + HFS + segredos) e monitoramento automático no Cron.
