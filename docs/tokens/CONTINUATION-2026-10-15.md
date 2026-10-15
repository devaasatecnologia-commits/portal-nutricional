# Handoff de continuidade - Admin: cofre de segredos, 2FA e motor de crons

**Data:** 2026-10-15
**Repositorio:** `devaasatecnologia-commits/portal-nutricional`
**Substitui como fonte atual:** [CONTINUATION-2026-10-14.md](CONTINUATION-2026-10-14.md)
(continua valido para Frota/Valhalla, hotfix de deploy e token de manutencao).
**Nao confirma:** schema, credenciais ou estado real de producao. Alteracoes
abaixo **nao foram executadas nem testadas** (ambiente sem PHP/shell na sessao).

## O que foi implementado

1. **Cofre de segredos** (`v1/src/Services/SecretVault.php`): tokens/chaves de
   integracao ficam cifrados (AES-256-GCM) na tabela `sistema_segredos`. O banco
   tem prioridade; `.env` e fallback. Chave mestra: `APP_ENC_KEY` (ou
   `CHAVE_SECRETA` se vazia) - **trocar a chave invalida os segredos salvos**.
   Gerenciaveis: `COBLI_API_KEY`, `COBLI_WEBHOOK_SECRET`, `GOOGLE_MAPS_API_KEY`,
   `MAIL_PASS`, `FLEX_API_TOKEN`. Ficam no `.env`: `DB_*`, `CHAVE_SECRETA`,
   `API_TOKEN`, `CRON_TOKEN`, `JWT_SECRET` (necessarios antes do banco).
   Consumidores migrados: Cobli (service e webhook), Geocoding/Geolocalizacao,
   MercosFlex, FlexMinimo, CronRotinas, Marketing e Xml (senha SMTP).
2. **2FA por TOTP** (`Services/Totp.php`, `Controllers/AdminSegredosController.php`):
   ativacao exige senha + codigo do app; revelar segredo/token exige codigo;
   anti-reuso de codigo, limite de 5 falhas/10 min e auditoria em
   `admin_segredos_log`. Tela: `portal/modules/admin/segredos.php`.
3. **Tokens de API**: `GET /admin/api-tokens` nao devolve mais o token completo;
   o botao do olho usa `POST /admin/api-tokens/{id}/revelar` (2FA).
4. **Autorizacao**: grupo `/v1/admin/*` agora exige permissao `admin`
   (`/admin/upload-foto` continua para qualquer usuario autenticado) e as rotas
   `/v1/cron/*` exigem admin (exceto `/cron/alertas-marketing`). Antes qualquer
   usuario autenticado alcancava essas rotas.
5. **Motor de crons**: os jobs de `cron_jobs` nunca eram disparados por horario
   (so manualmente). Novo dispatcher `v1/cron/run.php` roda a cada minuto,
   executa jobs vencidos (`CronExpression::isDue`), registra em `cron_execucoes`,
   usa `pg_try_advisory_lock` e grava heartbeat em `logs/cron_heartbeat.txt`.
   Novo `GET /v1/cron/servidor` e botao "Agendar no servidor" na tela de crons
   mostram a linha exata do cPanel (a hospedagem desabilita `exec()`, entao o
   painel nao edita o crontab). Novo `GET /v1/cron/rotinas` e rotina
   `refresh_cubo_vendas`. Corrigido `executar()` (JSONB chegava como string em
   parametro `array`).

## Pendencias / passos de deploy

- Rodar `docs/segredos_migration.sql` se o usuario do app nao puder criar tabelas.
- `composer dump-autoload -o` e enviar `vendor/composer/` por FTP (classes novas
  em `v1/src`: SecretVault, Totp, AdminSegredosController).
- Agendar no cPanel (uma vez): `* * * * * php .../v1/cron/run.php >> .../logs/cron_run.log 2>&1`.
- Ativar 2FA em Admin > Segredos & 2FA e migrar chaves do `.env` para o cofre.
- Remover do servidor `extract_vendor.php`, `phpinfo.php`, `sql.php` (acessiveis pela web).
- `MetaBuilderController`/`MarketingController` ainda usam fallback
  `'chave_super_secreta'` para `JWT_SECRET` - revisar.
- Colunas `notificar_*` de `cron_jobs` ainda nao enviam e-mail de sucesso/falha.
