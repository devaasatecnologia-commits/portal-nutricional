# Token de Continuidade — Hotfix Emergencial CSRF / Verba Flex (24/11/2026)

**Data:** 24/11/2026 (21:09 BRT — atendimento emergencial remoto)  
**Identificador:** `CONTINUATION-2026-11-24-emergencia-csrf-flex`  
**Token Anterior (contexto de módulos em andamento):** `docs/tokens/CONTINUATION-2026-10-26-fechamento-dia.md`  
**Commit do Hotfix:** `222ef73` (`fix(csrf): alinha fuso America/Sao_Paulo e uid do JWT na validacao CSRF`)  
**Status em Produção:** ✅ Corrigido, subido via GitSync + AndFTP e validado funcionando em `api.nutricionalbr.com`

---

## 1. Resumo da Emergência Ocorrida

- **Sintoma reportado:** Ao tentar realizar uma transferência no módulo **Transferência de Verba Flex** (`portal/modules/transferencia-flex.php` -> `POST /v1/flex/transferir`) às **21:09 BRT de 24/11/2026**, o portal retornava HTTP `403` com o alerta **"Falha na Transferência — CSRF token inválido"**.
- **Causa raiz:**
  1. O backend ([`v1/bootstrap/app.php`](../../v1/bootstrap/app.php) linha 41) opera em `America/Sao_Paulo` (`date('Y-m-d')` = `2026-11-24`).
  2. O frontend ([`portal/assets/js/config.js`](../../portal/assets/js/config.js)) gerava a data do CSRF com `new Date().toISOString().slice(0, 10)` (**UTC** = `2026-11-25` após 21:00 BRT) e obtinha o `uid` apenas de `localStorage.userData`.
  3. Com isso, todas as requisições `POST`/`PUT`/`DELETE`/`PATCH` protegidas pelo [`CsrfMiddleware`](../../v1/src/Middleware/CsrfMiddleware.php) falhavam diariamente entre **21:00 e 23:59 (horário de Brasília)**.

---

## 2. Alterações Aplicadas no Hotfix (`222ef73`)

1. **[`v1/src/Middleware/CsrfMiddleware.php`](../../v1/src/Middleware/CsrfMiddleware.php)**
   - Validação com `hash_equals` aceitando tanto `date('Y-m-d')` (`America/Sao_Paulo`) quanto `gmdate('Y-m-d')` (UTC), em MD5 e fallback Base64, garantindo compatibilidade imediata inclusive com caches antigos de JS no navegador.
2. **[`portal/assets/js/config.js`](../../portal/assets/js/config.js)**
   - Criada `getCsrfDateSaoPaulo()` usando `Intl.DateTimeFormat('en-CA', { timeZone: 'America/Sao_Paulo' })` com fallback local.
   - Criada `getCsrfUid()` priorizando o `uid` extraído do próprio JWT (`authToken` em `localStorage`/`sessionStorage`, com padding base64) e fallback para `localStorage.userData.uid`.
   - Interceptor `window.fetch` atualizado para preservar instâncias de `Headers`.
3. **[`portal/modules/admin/components/footer.php`](../../portal/modules/admin/components/footer.php)**
   - `getCsrfToken()` alinhado para `America/Sao_Paulo` e padding base64 no decode do JWT.

---

## 3. Arquivos Já Publicados em Produção (FTP)

> Todos os 3 arquivos abaixo já foram subidos para produção via **AndFTP** e validados em `24/11/2026`:

1. `v1/src/Middleware/CsrfMiddleware.php` ✅
2. `portal/assets/js/config.js` ✅
3. `portal/modules/admin/components/footer.php` ✅

*(Os arquivos de documentação em `docs/` deste handoff servem apenas ao repositório Git / VSCode e não precisam de deploy FTP.)*

---

## 4. Ações Pendentes ao Retomar no VSCode (Máquina Local)

Ao abrir o projeto no VSCode (`c:\xampp\htdocs\API`):

1. **Sincronizar repositório local:** executar `git pull origin main` para trazer o hotfix (`222ef73`) e este registro de handoff.
2. **Dar ciência do ocorrido e formalizar pós-incidente:**
   - Criar/registrar o **chamado** correspondente à emergência do CSRF no Verba Flex (conforme diretriz operacional da equipe).
   - Atualizar/criar a documentação formal seguindo o protocolo das **4 Etapas** (incluindo ficha `MOD-TRANSFERENCIA-FLEX` se desejado).
3. **Retomar fila anterior de módulos:** consultar o token anterior [`docs/tokens/CONTINUATION-2026-10-26-fechamento-dia.md`](./CONTINUATION-2026-10-26-fechamento-dia.md) para dar continuidade aos módulos indicados pelo supervisor.
