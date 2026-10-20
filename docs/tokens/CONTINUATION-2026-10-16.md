# Handoff de continuidade - 2FA por usuário (TOTP) e aviso no portal

**Data:** 2026-10-16
**Repositorio:** `devaasatecnologia-commits/portal-nutricional`
**Substitui como fonte atual:** [CONTINUATION-2026-10-15.md](CONTINUATION-2026-10-15.md)
(continua valido para cofre de segredos, 2FA do admin, motor de crons e o
hotfix de deploy).
**Nao confirma:** schema, credenciais ou estado real de producao.

## O que foi implementado

1. **2FA por TOTP para qualquer usuário** (antes só admin conseguia ativar).
   - `Services/Totp.php` inalterado (RFC 6238, SHA-1, 6 dígitos, 30 s).
   - `AdminSegredosController`: `iniciar/confirmar/desativar` deixaram de exigir
     admin — operam sempre sobre `$u['idusuario']` do token. A barreira admin
     continua em `listar/salvar/remover/revelar/revelarApiToken/auditoria/
     enviarWhatsapp/statusWhatsapp`.
   - `Controllers/Meu2faController.php` (novo): herda do `AdminSegredosController`
     e expõe `GET /v1/2fa/status`, `POST /v1/2fa/iniciar`, `/confirmar`,
     `/desativar` — **fora do grupo `/v1/admin`**, para qualquer usuário
     autenticado.
   - `v1/routes/api.php`: novo grupo `/v1/2fa` dentro do bloco protegido
     (JWT + Blacklist + CSRF).

2. **Ativação no próprio login** (`portal/login.php`).
   - Se o backend devolver `requer_cadastro_2fa: true`, abre **modal Swal**
     (senha → segredo → código) com botão **"Deixar para depois"** nos dois
     passos. Não redireciona mais para outra tela.
   - Se o backend devolver `requer_2fa: true` (usuário já tem 2FA), mostra o
     campo "Código do autenticador" e reenvia o `codigo2fa`.
   - Ao ativar com sucesso, atualiza `userData.requer_cadastro_2fa = false` no
     `localStorage`.

3. **Aviso persistente no portal** (`portal/estrutura/header.php`).
   - Banner amarelo logo após `<body>`, visível em qualquer tela do portal
     enquanto `userData.requer_cadastro_2fa === true`.
   - Botão **"Ativar agora"** → `portal/modules/2fa.php`.
   - Botão **"×"** fecha só nesta sessão (`sessionStorage`); reaparece no
     próximo login.
   - **Não bloqueia acesso.** O backend não impede o login enquanto o 2FA não
     está cadastrado (decisão consciente: evitar lockout).

4. **Tela enxuta `portal/modules/2fa.php`** (auto-atendimento).
   - Sem sidebar, sem menu, sem depender de `components/sidebar.php`.
   - Independe do template do admin. Lê `localStorage.getItem('authToken')` e
     usa a mesma lógica de `isLocal`/`appBase` do `login.php`.
   - Cobre o caso "usuário fechou o banner e voltou depois".

## Decisões de design confirmadas

- **Não bloquear o login** até o usuário cadastrar o 2FA. O aviso é
  persistente, mas o acesso é liberado. Bloqueio só se a política de segurança
  exigir, o que não é o caso hoje.
- **Não criar endpoint admin que devolva o segredo de outro usuário.** Isso
  quebraria o modelo do TOTP (o admin poderia gerar códigos no lugar do
  usuário). Cada usuário gera o próprio segredo via `/v1/2fa/iniciar`.
- **WhatsApp desligado por ora.** O backend já tem `WhatsAppCodigo` e
  `WhatsAppService` (Z-API / Meta Cloud API), mas o front (`2fa.php`,
  `segredos.php`) **ainda não tem campo para o código do WhatsApp**. Se o
  provedor for configurado no cofre, ações sensíveis (`revelar`, `desativar`,
  `alterarSenha`, `editarUsuario`) vão travar no front com
  `requer_whatsapp: true`. Enquanto isso não for tratado, manter
  `ZAPI_*`/`WHATSAPP_META_*` **vazios** no cofre (fallback seguro: sem provedor
  = sem exigência).

## Pendencias / passos de deploy

- **Subir por FTP** os arquivos alterados/criados:
  - `v1/src/Controllers/AdminSegredosController.php` (modificado)
  - `v1/src/Controllers/Meu2faController.php` (novo)
  - `v1/routes/api.php` (grupo `/v1/2fa`)
  - `portal/login.php` (modal de ativação)
  - `portal/estrutura/header.php` (banner)
  - `portal/modules/2fa.php` (novo)
- **`composer dump-autoload -o`** local (classe nova `Meu2faController`) e
  enviar `vendor/composer/` — pode ir como `vendor.zip` + `extract_vendor.php`.
- **Limpar OPcache** após o upload (PHP-FPM/cPanel).
- **Remover do servidor** `extract_vendor.php`, `phpinfo.php`, `sql.php` após o
  uso.
- **Rodar `docs/segredos_migration.sql`** se ainda não foi aplicado em produção
  (tabelas `admin_2fa`, `admin_segredos_log`, `sistema_segredos`,
  `usuario_seguranca`, `admin_whatsapp_codigo`).
- **Definir `APP_ENC_KEY`** distinta de `CHAVE_SECRETA` no `.env` de produção
  (evita que rotacionar `CHAVE_SECRETA` invalide o cofre, o 2FA e os códigos de
  WhatsApp em circulação).
- **Pendências anteriores** continuam válidas: `MetaBuilderController`/
  `MarketingController` com fallback `'chave_super_secreta'`; colunas
  `notificar_*` de `cron_jobs` sem envio de e-mail; WhatsApp sem campo no front.

## O que NÃO foi feito

- Não foi implementado reset administrativo de 2FA (`POST /admin/seguranca-usuarios/{id}/2fa/resetar`).
  Quando um usuário perder o celular, hoje não há caminho auditado de reset
  pelo admin. **Recomendado implementar** (apaga `admin_2fa.idusuario` e
  registra em `admin_segredos_log`).
- Não foi adicionada auditoria do "Deixar para depois" no `admin_segredos_log`
  (opcional).
- Não foi ligado o WhatsApp no cofre, nem criado campo de código do WhatsApp
  no front.

## Como testar localmente

1. `composer dump-autoload -o`.
2. Subir `php -S localhost:8080 router.php`.
3. Login como TIAGO (não-admin, `exige_2fa_login=true`, sem 2FA cadastrado):
   - modal de ativação abre;
   - "Deixar para depois" → portal com banner amarelo;
   - "Ativar agora" no banner → `2fa.php` → ativa;
   - logout + login → agora pede o código.
4. Login como admin → `/v1/admin/segredos` continua funcionando.

Verificações rápidas no Console (logado):

```js
await (await fetch('/index.php?api_route=/2fa/status', {
  headers: { 'Authorization': 'Bearer ' + localStorage.getItem('authToken') }
})).json();
// esperado: {ativo: <bool>, pendente: <bool>}