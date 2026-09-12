# Token de Continuidade

Este documento é um contexto técnico para a próxima sessão. Não contém
senhas, tokens JWT, chaves de API ou credenciais.

## Projeto

- Repositório: `portal-nutricional`
- Branch: `main`
- API estável: `/v1`
- Ponte de compatibilidade: `/v2` encaminha temporariamente para `/v1`
- Banco: PostgreSQL externo, configurado por `.env`
- Última referência: sessão 2026-09-11 (perfil/logout/chat no app do motorista).

## Módulos importantes

- Portal: `portal/index.php`
- Login: `portal/login.php`
- Gateway: `index.php`
- Rotas: `v1/routes/api.php`
- Frota: `portal/modules/frota`
- App offline do motorista: `portal/modules/frota/motorista-offline.php`
- Acerto: `portal/modules/frota/acerto-embarque.php`
- Administração: `portal/modules/admin`

## Vínculo motorista

O usuário do motorista deve usar o mesmo identificador ERP:

`usuario.idcliforemp = frota_motorista.erp_id`

O login retorna `motorista_id` e direciona o usuário para o aplicativo offline.
O módulo liberado usa o slug `motorista-offline` em `usuario_permissoes`.

## Estado atual (2026-09-11)

### Concluído nesta sessão

- `AuthController::login()` grava sessão PHP (`$_SESSION`) além do JWT.
  Permite que `motorista-offline.php` (PHP puro) enxergue o usuário logado.
- `AuthController::logout()` limpa `$_SESSION` e apaga o cookie de sessão.
- `CsrfMiddleware` foi ajustado para isentar as rotas `/v1/frota/`,
  `/v1/perfil` e `/v1/admin/upload-foto`. O app do motorista usa JWT no
  header `Authorization`, então é imune a CSRF por design.
- PWA do motorista rebatizado como **Nutricional Rotas** com ícone verde
  customizado (android-chrome-192x192.png, android-chrome-512x512.png,
  android-chrome-512x512-maskable.png, apple-touch-icon.png).
- `motorista-offline.php` redireciona para login com `?redirect=` quando
  não autenticado. Sessão ativa carrega direto a rota.
- Drawer lateral no header com: Meu perfil, Alterar foto, Mensagens
  (desativado), Tema, Sincronizar agora, Sair.
- Modal de perfil com dados do usuário (nome, email, telefone, endereço,
  cidade, cargo) e alteração de foto (redimensionamento 512x512 no cliente,
  upload via `/v1/admin/upload-foto`).
- Fila offline de foto de perfil (envia quando volta conexão).
- Logout inteligente: bloqueia se houver fila pendente, oferece
  "sincronizar primeiro".
- `service-worker.js` versão atual: `frota-motorista-v15`.

### Pausado nesta sessão

- **Chat interno no app do motorista (Etapa 2A + 2B)**. Chat do portal
  funciona. Endpoints `/v1/chat/*` no `ChatController` estão estáveis.
  Botão "Mensagens" no drawer existe, mas está sem handler — clicar não
  faz nada. Decisão: retomar depois.

### Estrutura de arquivos do app do motorista

- `portal/modules/frota/motorista-offline.php` — HTML + PHP (verifica
  sessão, define `window.MOTORISTA_ID_INICIAL`, `window.MOTORISTA_ID_VINCULADO`,
  `window.IS_ADMIN_APP`, `window.MOTORISTA_NOME_SESSAO`).
- `portal/modules/frota/assets/motorista-offline.js` — toda a lógica do
  app (rota, GPS, fila offline, perfil, drawer, logout).
- `portal/modules/frota/assets/motorista-offline.css` — estilos do app.
- `portal/modules/frota/service-worker.js` — cache offline-first.
- `portal/modules/frota/manifest-motorista.json` — manifest PWA.

### Regras aplicadas

- Motorista comum **nunca** pode trocar para outro motorista. O PHP esconde
  o seletor via `hidden` e o JS valida `window.IS_ADMIN_APP`.
- Admin/gestor pode passar `?motorista_id=N` na URL ou usar o seletor.
- A fila offline usa IndexedDB na store `fila`, com chave `operation_id`.
- Requisições de API **nunca** são cacheadas pelo Service Worker.

## Pendências prioritárias

1. **Aplicar `docs/frota_offline_idempotencia.sql`** no banco de testes e
   validar idempotência nas operações do motorista.
2. **Retomar chat do motorista** (Etapa 2A modal + 2B fila offline).
   Chat do portal funciona; endpoints prontos em `/v1/chat/*`.
3. **Conflitos de rota offline** — testar o fluxo "Descartar ordenação
   local" ponta-a-ponta.
4. **Dashboard Executivo** — `/v1/frota/acerto/embarques?limite=1000` é
   ineficiente. Criar endpoint `/v1/frota/acerto/resumo`.
5. **Duplicações de funções JS** — `formatarMoeda` em `gestao-cargas.js`,
   `getStatusClass` em `embarquesClaude.js`, `fecharModal` usando jQuery
   não carregado em `embarquesClaude.js`.
6. **Push notification nativo** quando app do motorista está fechado.
7. **Revisar `calcular_estatisticas_motorista()`** no PostgreSQL para usar
   `pedido_item.valortotal` (regra do `PORTAL_GUIDE.md`).
8. **Corrigir rollbacks** em retornos antecipados dentro de transações.
9. **Substituir a ponte V2** por contratos V2 testados.
10. **Validar em homologação** a criação ERP com transações 19/20 antes de
    produção.

## Bugs conhecidos

- Badge "Mensagens" no drawer aparece mas o modal não existe (chat pausado).
  Ao clicar, nada acontece.
- `csrftoken` do app do motorista é gerado em `config.js` com base em
  `localStorage.userData.uid`. Como as rotas `/v1/frota/*` agora são
  isentas de CSRF, isso não afeta o funcionamento.

## Validação mínima

```powershell
php -l index.php
php -l v1/bootstrap/app.php
php -l v1/src/Controllers/AuthController.php
php -l v1/src/Middleware/CsrfMiddleware.php
node --check portal/modules/frota/assets/motorista-offline.js
curl http://localhost:8080/ping