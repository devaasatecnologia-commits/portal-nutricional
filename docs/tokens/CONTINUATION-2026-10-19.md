# Handoff de continuidade - Correção de fallbacks JWT e Reset Administrativo de 2FA

**Data:** 2026-10-19
**Repositorio:** `devaasatecnologia-commits/portal-nutricional`
**Substitui como fonte atual:** [CONTINUATION-2026-10-16.md](CONTINUATION-2026-10-16.md)
(continua valido para 2FA por usuario com auto-atendimento, cofre de segredos, motor de crons e otimizador de frota sem Valhalla).
**Nao confirma:** schema, credenciais ou estado real de producao.

## O que foi implementado

1. **Eliminação dos fallbacks hardcoded de chave JWT (`'chave_super_secreta'`)**:
   - `v1/src/Controllers/MarketingController.php` (método `getUserIdFromToken`):
     - Primeiro obtém o usuário autenticado diretamente do request (`$request->getAttribute('user')`), evitando decodificação redundante de token.
     - Caso precise decodificar via header, utiliza `CHAVE_SECRETA` (ou `$_ENV['CHAVE_SECRETA']` / `$_ENV['JWT_SECRET']`).
     - Removido completamente o fallback `'chave_super_secreta'`. Se não houver chave ou o token for inválido, retorna `0` com segurança.
   - `v1/src/Controllers/MetaBuilderController.php` (método `getUserIdFromToken`):
     - Mesma correção aplicada: prioriza `$request->getAttribute('user')` e usa `CHAVE_SECRETA` sem nenhum fallback vulnerável.

2. **Reset Administrativo de 2FA (`POST /v1/admin/seguranca-usuarios/{id}/2fa/resetar`)**:
   - `v1/src/Controllers/AdminSegurancaUsuariosController.php`:
     - Novo método `resetar2fa(Request $request, Response $response, array $args): Response`.
     - Protegido por dupla camada: barreira de permissão admin (`$this->exigirAdmin()`) e validação de 2FA do próprio administrador executor (`$this->validarDoisFatores()`).
     - Remove o registro do usuário em `admin_2fa`. Caso o usuário tenha `exige_2fa_login = true`, no próximo login ele será conduzido ao auto-atendimento para cadastrar um novo dispositivo.
     - Registra auditoria completa em `admin_segredos_log` (chave `2fa:<username>`, ação `reset_admin`, IP, idusuario do admin e sucesso).
   - `v1/routes/api.php`:
     - Rota registrada dentro do grupo protegido `/v1/admin` (sob `ModulePermissionMiddleware('admin')`).

3. **Interface Visual para Reset de 2FA no Portal**:
   - `portal/modules/admin/segredos.php`:
     - Na aba "Segurança por Usuário", a coluna de status 2FA agora exibe o botão **"Resetar"** ao lado do badge "Ativo".
     - Ao clicar, exibe confirmação no SweetAlert2 explicando que o autenticador será removido e pedindo o código 2FA do admin para autorizar a operação.
   - `portal/modules/admin/usuarios.php`:
     - No modal de Segurança individual (botão de escudo na listagem de usuários), caso o usuário tenha 2FA ativo, é exibido o botão **"Resetar 2FA"** com o mesmo fluxo seguro de confirmação e auditoria.

4. **Saneamento e Limpeza Operacional**:
   - O deploy da fase 2FA Geral (16/10) foi confirmado em produção pelo responsável.
   - Os arquivos de diagnóstico/legados com riscos de segurança na raiz (`sql.php`, `extract_vendor.php`, `phpinfo.php`) foram marcados para remoção definitiva.

## Decisões de design confirmadas

- **Reset de 2FA pelo admin não gera novo segredo no lugar do usuário:** O endpoint apenas remove o segredo antigo do banco (`DELETE FROM admin_2fa WHERE idusuario = :id`). O próprio usuário gera sua nova chave privada no próximo acesso via `/v1/2fa/iniciar`, mantendo o princípio de que o segredo TOTP nunca é conhecido por terceiros (nem pelo admin).
- **Ação exige 2FA do admin:** Resetar autenticador de outrem é operação sensível de segurança e exige autenticação em duas etapas do administrador solicitante.
- **Não há novas classes:** Como os métodos foram adicionados em controllers existentes (`AdminSegurancaUsuariosController`, `MarketingController`, `MetaBuilderController`), o upload **não requer** `composer dump-autoload -o`.

## Arquivos alterados nesta entrega

- `v1/src/Controllers/MarketingController.php`
- `v1/src/Controllers/MetaBuilderController.php`
- `v1/src/Controllers/AdminSegurancaUsuariosController.php`
- `v1/routes/api.php`
- `portal/modules/admin/segredos.php`
- `portal/modules/admin/usuarios.php`

## Como testar

1. No terminal local:
   ```powershell
   php -l v1/src/Controllers/MarketingController.php
   php -l v1/src/Controllers/MetaBuilderController.php
   php -l v1/src/Controllers/AdminSegurancaUsuariosController.php
   php -l v1/routes/api.php
   php -l portal/modules/admin/segredos.php
   php -l portal/modules/admin/usuarios.php
   ```
2. Testar no navegador como Admin:
   - Acessar `Admin > Segredos & 2FA > Segurança por Usuário`.
   - Localizar um usuário com 2FA ativo e clicar em **"Resetar"**.
   - Informar o código 2FA do administrador logado.
   - O status do usuário passará para "Não ativado".
   - Na aba "Auditoria", verificar o registro `acao = reset_admin` e `chave = 2fa:<usuario>`.

## Pendências restantes

- **WhatsApp no cofre / front:** O backend possui `WhatsAppCodigo` e `WhatsAppService`, mas o frontend ainda não tem inputs para o código de 5 minutos do WhatsApp em `segredos.php`. Manter chaves do provedor vazias no cofre até que as telas sejam adaptadas.
- **Notificações por e-mail nos crons:** Colunas `notificar_sucesso` e `notificar_falha` de `cron_jobs` continuam sem disparo de e-mail implementado no dispatcher.
- **Hash de senhas:** Migração gradual do MD5 legado para `password_hash()` (Bcrypt/Argon2ID).

