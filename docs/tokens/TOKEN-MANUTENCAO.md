# TOKEN DE MANUTENCAO - Portal Nutricional

**Atualizado:** 2026-10-14
**Uso:** envie este arquivo a uma IA junto com (1) o nome do modulo/tela, (2) o
erro exato e (3) os arquivos relacionados. A IA deve diagnosticar e propor a
correcao minima. Nao anexe `.env`, senhas, JWT, cookies, dumps ou dados reais.

> Este arquivo e contexto tecnico, nao concede acesso. Use apenas IA aprovada
> pela empresa (ver [AI_HANDOFF.md](../AI_HANDOFF.md)).

## Instrucoes para a IA

Voce esta ajudando a manter o Portal Nutricional (PHP + Slim 4 + PostgreSQL).
Ao receber um modulo e um erro:

1. Leia o erro literal e identifique a camada (ver "Mapa de erros comuns").
2. Pesquise o simbolo/rota citados antes de supor; confirme no codigo.
3. Explique a causa raiz em poucas linhas e proponha a menor correcao.
4. Nao refatore alem do necessario, nao invente tabelas/colunas: o schema
   PostgreSQL e externo e so esta parcialmente em `docs/*.sql`. Se faltar
   informacao do banco, pergunte.
5. Valide: `php -l <arquivo>`, `node --check <arquivo.js>`; descreva como
   testar manualmente. Nunca peca nem grave segredos.
6. Se a correcao exigir migration SQL, deploy ou rodar algo em producao, avise
   e peca aprovacao; nao execute por conta propria.

## Pilha e estrutura

- PHP 8.2, Slim 4, Guzzle, JWT (firebase/php-jwt), PhpSpreadsheet, Monolog,
  Predis. Banco PostgreSQL (externo, acesso via PDO). Dev: XAMPP em
  `C:\xampp\htdocs\API`, URL `/API`. Producao: cPanel/FTP
  (`api.nutricionalbr.com`).
- Autoload PSR-4: `Nutricional\` => `v1/src/` (via `vendor/composer`).
- Entradas: `index.php` -> `v1/bootstrap/app.php` (Slim, .env, PDO,
  middlewares) -> `v1/routes/api.php` (todas as rotas `/v1/...`).
- `v1/src/Controllers/` (um por dominio; `Frota/` para o modulo de frota),
  `v1/src/Services/` (regras reutilizaveis; `Frota/` para Cobli, Geocoding,
  Otimizador, Valhalla, ERP), `v1/src/Middleware/` (Jwt, ModulePermission,
  FrotaGestao, RateLimit, Csrf, Logging, Blacklist, Cache).
- Front: `portal/` (paginas PHP + JS/CSS por modulo em `portal/modules/<nome>/`,
  ex.: `frota`, `admin`, `marketing`). Frota: `embarques.php`,
  `gestao-cargas.php`, `acerto-embarque.php`, `cadastro-frota.php`,
  `motorista-offline.php` (PWA com `service-worker.js` e fila offline).
- Docs: `docs/ARCHITECTURE.md`, `docs/HANDBOOK.md`, `docs/PORTAL_GUIDE.md`,
  `docs/DEPLOY_FTP_CPANEL.md`, migrations em `docs/*.sql`, historico em
  `docs/tokens/`.
- Logs: `erros_log/` e `v1/logs/`; erros PHP vao para `error_log`. Peca a
  linha do log correspondente ao erro (sem dados pessoais).

## Autenticacao e permissoes (resumo)

- `POST /v1/auth/login` devolve JWT; rotas protegidas usam `JwtMiddleware`.
- Permissoes: `sistema_modulos`, `sistema_setores`, `usuario_permissoes`;
  `usuarios_admin` = acesso global. Frota administrativa exige `admin`, `frota`
  ou `gestao-cargas`; motorista so ve o que esta ligado ao seu `motorista_id`.
- Inatividade: front chama `POST /v1/auth/inatividade` ao retomar a sessao
  (codigos de inatividade; tabela `portal_inatividade`).

## Regras de negocio que ja causaram duvida

- **Roteamento (Frota):** Valhalla NAO esta implantado. `VALHALLA_URL` vazio =
  otimizador usa Haversine (x1,3; 40 km/h) e o motorista navega por
  Waze/Google Maps. A resposta traz `data.provedor`. Nao reintroduza default
  `localhost:8002`.
- **Geocodificacao:** Google Maps (`GOOGLE_MAPS_API_KEY`), em 3 niveis
  (frota_cliente -> endereco completo -> simplificado/pendente confirmacao).
- **Carregamento/Auditoria:** subidas, alocacoes e estornos sao append-only;
  estorno exige motivo (3-500 chars); filial 6 sem NF.
- **Deposito/Separacao/Desembarque:** enderecos vem de `lote_endereco` via
  `EnderecoItemService` (FIFO; endereco = letra da secao + linha + coluna);
  INSERT em `lote_endereco` faz `ON CONFLICT` somando.
- **Retorno:** retorno por embarque em `retorno_carga` (coluna `idembarque`).
- Dinheiro/quantidade de acerto: `pedido_item.valortotal` e `qt`, ligados por
  `frota_entrega.pedidos_ids`.

## Mapa de erros comuns

| Sintoma | Causa provavel | Acao |
|---|---|---|
| `Class "Nutricional\...\X" not found` | Classe nova fora do classmap otimizado do `vendor/composer` no servidor | `composer dump-autoload -o` local, enviar `vendor/composer` (ou `vendor.tar.gz` + `extract_vendor.php`), limpar OPcache. Conferir se o arquivo existe no servidor |
| `Call to undefined function exec()` | Hospedagem desabilita `exec`/`shell_exec` | Usar so funcoes PHP nativas (PharData, ZipArchive, iteradores) |
| Erro so depois de inatividade/relogin | Sessao/JWT expirado, cache do navegador ou servidor com arquivos desatualizados | Ver 401 no Network; relogar; comparar versao dos arquivos no servidor |
| HTTP 401/403 em Frota | JWT expirado ou permissao faltando | Checar `JwtMiddleware`, `FrotaGestaoMiddleware`, permissoes do usuario |
| HTTP 500 em endpoint | Excecao PHP/SQL (coluna ou tabela ausente no schema externo) | Ler `error_log`; checar migration pendente em `docs/*.sql` |
| `relation/column ... does not exist` | Migration nao aplicada | Identificar o `.sql` em `docs/`; aplicar em homologacao com aprovacao do dono do banco |
| Otimizar rota mostra "linha reta" | Valhalla nao configurado (esperado) | Nenhuma; so configurar `VALHALLA_URL` quando houver instancia |
| Ordem de entregas estranha apos otimizar | `foreach` por referencia sem `unset` | Procurar `foreach (... as &$x)` seguido de outro `foreach` com a mesma variavel |
| Motorista sem navegacao | `abrirNavegacao` sem latitude/longitude cai para busca por endereco | Conferir coordenadas da entrega |
| PWA mostra versao velha | Service worker em cache | Subir `CACHE_VERSION`/nome do cache no `service-worker.js` |
| `php -S` falha ao instalar PhpSpreadsheet | Extensao `gd` desabilitada | Habilitar `gd` no `php.ini` |
| Pedido de Amostra: "Sandbox, nada gravado" | `ERP_PEDIDO_SANDBOX` ausente/true no `.env` | Definir `ERP_PEDIDO_SANDBOX=false` no servidor |
| Pedido de Amostra: 403 filial/transacao | `usuario.dash_filiais` vazio (so admin passa) ou codigo fora da view `transacao_pedido` | Ajustar filiais do usuario no admin |
| Modulo Pedido de Amostra nao aparece | Slug `pedido-amostra` nao registrado | Rodar `docs/pedido_amostra_modulo_migration.sql` e liberar ao usuario |

## Modulo Pedido de Amostra

- Rotas `/v1/amostras/{contexto,itens,clientes,pedidos}` (GET) e `POST /v1/amostras/pedidos`, slug `pedido-amostra`.
- Codigo: `AmostraPedidoController`, `portal/modules/pedido-amostra.php`, `portal/assets/js/pedido-amostra.js`.
- Itens/saldo: view `amostra_brindes_com_saldo` (idimposto 4 se transacao 8, senao 6; filial 6 ou 1; `saldo_estoque <> 0`); quantidade limitada a `quantidade_estoque`.
- Transacoes: somente as da view `transacao_pedido`. Filiais: `usuario.dash_filiais` (vazio = sem acesso, exceto admin = 1 e 6).
- Cria via `ERPPedidoService` (palmtop_pedido), com `nomecliente=PORTAL[nextval('pedido_portal')]`, observacao livre e sem a checagem de saldo por lote.
- Lista dos ultimos pedidos: query `pedido`/`pedido_item` (transacoes 8 e 10, cliente tipo 5, status 1 e 5) mais pendentes de importacao em `palmtop_pedido`.

## Deploy (resumo)

- O servidor **nao roda composer** nem `exec()`. Alteracoes em `v1/src` e
  `portal/` sobem por FTP. Mudou dependencia ou classe nova? Gere
  `composer dump-autoload -o`, compacte (`tar -czf vendor.tar.gz vendor`),
  envie e rode `extract_vendor.php` (nao versionado; remova depois). Detalhes
  em [DEPLOY_FTP_CPANEL.md](../DEPLOY_FTP_CPANEL.md).
- Nunca commitar: `.env`, `phpinfo.php`, `extract_vendor.php`, `vendor/`,
  `*.tar.gz`, logs, dumps.

## Modelo para pedir ajuda (copie e preencha)

```text
Modulo/tela: (ex.: Frota > Embarques > Otimizar rota)
Ambiente: (XAMPP local | producao cPanel)
Usuario/perfil (sem dados pessoais): (gestor | motorista | admin)
Passos para reproduzir:
Erro exato (texto da tela / HTTP / console):
Trecho do error_log (sem segredos):
O que mudou recentemente (deploy, migration, arquivo):
Arquivos anexados:
```

## Checklist final da IA antes de responder

- [ ] Causa raiz identificada no codigo, nao suposta.
- [ ] Correcao minima, sem mudar comportamento nao relacionado.
- [ ] `php -l` / `node --check` executados nos arquivos alterados.
- [ ] Passos de teste manual descritos.
- [ ] Indicado o que precisa subir por FTP e se exige `dump-autoload -o` ou
      migration.
- [ ] Nenhum segredo, dado real ou URL privada incluido na resposta.
