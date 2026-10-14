# Handoff de continuidade - Frota sem Valhalla, hotfix de deploy e token de manutencao

**Verificado em:** 2026-10-14
**Repositorio:** `devaasatecnologia-commits/portal-nutricional`
**Substitui como fonte atual:** [CONTINUATION-2026-10-07.md](CONTINUATION-2026-10-07.md)
(continua valido para o historico do modulo Frota e pendencias antigas).
**Nao confirma:** schema, credenciais, monitoramento ou estado real de producao.

Para diagnosticar um erro em qualquer modulo, use o
[TOKEN-MANUTENCAO.md](TOKEN-MANUTENCAO.md).

## Decisoes confirmadas com o responsavel

- O Valhalla **nao sera implantado agora**. O Frota deve funcionar como antes:
  motorista navega pelo **Waze** e **Google Maps** (`abrirNavegacao` em
  `portal/modules/frota/assets/motorista-offline.js`) e o gestor ve o mapa com
  link do Google Maps em `embarquesClaude.js`.
- `VALHALLA_URL` fica **vazio** no `.env`. Preencher so quando houver instancia
  real validada (ver [VALHALLA_SETUP.md](../VALHALLA_SETUP.md)).

## Alteracoes desta sessao

1. **Otimizador de rotas sem Valhalla**
   (`v1/src/Services/Frota/OtimizadorRotasService.php`):
   - sem `VALHALLA_URL`, ou com Valhalla fora do ar, usa matriz Haversine
     (fator rodoviario 1,3; 40 km/h) + vizinho mais proximo + 2-opt, saida e
     retorno ao CD;
   - resposta traz `data.provedor` (`haversine` ou `valhalla`) e `data.criterio`;
     se o Valhalla estava configurado mas falhou, vai aviso em `data.avisos`;
   - geometria vira a linha reta entre pontos quando nao ha Valhalla.
2. **`ValhallaRoutingService`**: removido o default `http://localhost:8002`;
   novo `isConfigured()`; `route()` e `timeDistanceMatrix()` retornam
   `code = not_configured` sem tentar rede.
3. **Bug corrigido (antigo):** `foreach ($entregas as &$entrega)` sem `unset`
   corrompia a ultima entrega no laco seguinte (ordem `[2,2,1]`, coordenada
   errada no ultimo ponto). Corrigido com `unset($entrega)`.
4. **Front** (`embarquesClaude.js`): textos de distancia/tempo/aviso deixam de
   citar Valhalla quando o provedor e `haversine` ("estimada em linha reta").
5. **Docs:** `ARCHITECTURE.md`, `VALHALLA_SETUP.md` e este token atualizados
   (a regra "sem fallback silencioso" foi trocada por "fallback sempre
   sinalizado").

## Incidente de deploy resolvido (2026-10-13)

- Sintoma: apos voltar da inatividade, Separacao > escolher embarque mostrava
  `Falha ao sincronizar lista: Class "Nutricional\Services\EnderecoItemService"
  not found`.
- Causa: o `vendor/composer` do servidor tinha classmap antigo sem a classe nova
  (existia em `v1/src/Services/`).
- Correcao: `composer dump-autoload -o` local, enviar `vendor.tar.gz` por FTP e
  rodar `extract_vendor.php` com "Forcar Reextracao".
- A hospedagem **desabilita `exec()`**: `extract_vendor.php` foi reescrito com
  `PharData`, `RecursiveIterator` e `chmod` nativos. Ele nao e versionado e deve
  ser removido do servidor apos o uso (e acessivel pela web).
- Regra: **toda classe nova em `v1/src` exige `composer dump-autoload -o` e
  enviar `vendor/composer/` atualizado** (o servidor nao roda composer).

## Validacoes executadas

- `php -l` em todos os `.php` de `v1/` e `portal/`: sem erros.
- `node --check` em todos os JS de `portal/modules/frota/assets`: sem erros.
- Teste manual do otimizador com PDO SQLite em memoria (3 entregas, sem
  Valhalla): ordem `[2,1,3]`, provedor `haversine`, 5 pontos de geometria.
- Nao existe suite PHPUnit no repositorio; nao foi executada integracao com
  PostgreSQL, Cobli nem Google Maps reais.

## Proximos passos

- Subir ao servidor os arquivos alterados: `OtimizadorRotasService.php`,
  `ValhallaRoutingService.php` e `embarquesClaude.js` (e atualizar versao/cache
  do service worker se o JS do motorista mudar).
- Validar em homologacao o botao **Otimizar** em um embarque real.
- Pendencias antigas continuam em `CONTINUATION-2026-10-07.md` (monitoramento,
  Cobli, offline/IndexedDB, testes de integracao, modulo de manutencao de
  frota, baixa em `lote_endereco` na Separacao).
