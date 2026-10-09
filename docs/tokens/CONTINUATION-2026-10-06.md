# Handoff de continuidade - Portal Nutricional

**Verificado em:** 2026-10-06

**Repositorio:** `devaasatecnologia-commits/portal-nutricional`

**Classificacao:** interno - sem credenciais ou dados de producao

## Leia antes de agir

Este handoff e um mapa para retomar o trabalho, nao uma fonte de acesso.
Consulte [o handbook](../HANDBOOK.md). O Bitwarden da organizacao tem
custodiante unico; solicite contas individuais de desenvolvimento aos donos dos
servicos conforme o [processo](../SECRETS_ACCESS.md). Nao solicite nem copie
segredos em chat, prompt, commit ou arquivo de token. Acesso a credenciais nao
autoriza acesso a dados de producao.

Antes de editar, verifique branch, `git status --short` e diff. O checkout pode
conter trabalho pendente de outra pessoa; nao reverta, esconda ou publique essas
alteracoes sem autorizacao.

## Fatos confirmados no codigo/documentacao em 2026-10-06

- Aplicacao PHP com Composer, Slim 4, portal PHP/JavaScript e PostgreSQL externo.
- Gateway principal: `index.php`; desenvolvimento pode usar
  `php -S localhost:8080 router.php`.
- Bootstrap da API: `v1/bootstrap/app.php`; rotas: `v1/routes/api.php`;
  controllers/services: `v1/src/`.
- API estavel: `/v1`. `/v2` e atualmente uma ponte para a V1, nao uma
  implementacao independente (`v2/README.md`, `v2/index.php`).
- O bootstrap exige `CHAVE_SECRETA`, `API_TOKEN` e `CRON_TOKEN`; alguns
  handlers tambem usam `JWT_SECRET`, que deve ser configurado com valor forte.
  Os valores sao exclusivamente de ambiente e nao devem ser transcritos para
  este arquivo.
- O login ainda verifica a senha do usuario legado com a formula MD5 existente
  em `v1/src/Controllers/AuthController.php`. Nao migre ou altere o formato sem
  plano de compatibilidade e aprovacao.
- O PWA do motorista fica em `portal/modules/frota/`; o checkout atual usa
  cache `frota-motorista-v49`. Isso nao confirma a versao instalada em
  homologacao/producao, que deve ser validada separadamente.
- O banco e externo. Os SQL em `docs/` sao scripts pontuais, nao um schema
  completo nem uma migration runner.
- Nao foi identificada uma suite de testes propria/configuracao PHPUnit na raiz
  neste checkout. Verifique novamente antes de assumir que isso continua igual.

## Mapa de leitura

1. `README.md` - setup rapido e links.
2. `docs/HANDBOOK.md` - arquitetura de alto nivel, seguranca e onboarding.
3. `docs/ARCHITECTURE.md` - gateways, autenticacao e fluxo da Frota.
4. `docs/PORTAL_GUIDE.md` - modulos e regras operacionais.
5. `docs/DEPLOY_FTP_CPANEL.md` - publicacao, verificacoes e rollback.
6. `docs/SECRETS_ACCESS.md` - concessao de acesso sem divulgar valores.
7. `v1/routes/api.php` e o controller/service do modulo em manutencao.

Snapshots antigos estao listados em `docs/tokens/README.md`; trate-os como
historicos sujeitos a desatualizacao.

## Primeiro ciclo de trabalho

1. Confirmar com responsaveis qual branch/release e ambiente sao autoritativos.
2. Solicitar somente acesso individual ao repositorio e aos servicos necessarios;
   desenvolvimento deve usar banco e contas de desenvolvimento/homologacao.
3. Preparar `.env` local a partir de `.env.example`; nunca copiar `.env` de
   producao ou de outra pessoa.
4. Validar `/ping`, `/v1/ping` e `/v2/ping` localmente, depois exercitar um
   fluxo com dados ficticios.
5. Antes de SQL, deploy, acao ERP ou teste de integracao externa, pedir
   aprovacao do respectivo dono e validar em homologacao.
6. Atualizar este handoff somente com fatos confirmados e sem valores secretos.

## A confirmar com os responsaveis

- Quem sao os donos tecnicos e funcionais de API, ERP/banco, hospedagem,
  Google Maps, Cobli e crons.
- Quem e o custodiante Bitwarden, qual processo de recuperacao foi definido e
  quais contas individuais de desenvolvimento existem nos servicos necessarios.
- URLs, contas, cronogramas, backups, monitoramento, ambientes e rollback
  atuais; nao presumir que a documentacao historica os descreva corretamente.
- Schema PostgreSQL instalado, migrations aplicadas e diferencas de homologacao
  para producao.
- Estado real de cada integracao e quais permissoes sao necessarias por tarefa.
- Suite de testes/build e fluxo de revisao/publicacao vigentes.

## Ao concluir uma tarefa

Executar verificacoes proporcionais a mudanca, revisar
`git diff --check`/`git diff`, descrever validacoes realizadas e deixar claro o
que nao foi possivel testar. Nao fazer `git add .`, commit, push, publicacao,
exportacao de dados ou alteracao destrutiva sem solicitacao/aprovacao adequada.
