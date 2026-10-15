# Handbook de continuidade - Portal Nutricional

**Revisado em:** 2026-10-06

**Classificacao:** interno

**Escopo:** orientacao tecnica e operacional; nao concede acesso a sistemas ou dados.

Este documento e a porta de entrada para quem vai manter o projeto. Ele registra
o que pode ser confirmado no repositorio e direciona para os guias especializados.
Detalhes mutaveis (rotas, schema externo, servicos e operacao de producao) devem
ser conferidos no codigo ou com seus responsaveis antes de qualquer mudanca.

## Regras essenciais

1. Nao grave senhas, tokens, chaves, cookies, URLs privadas, dumps ou dados de
   clientes em Git, tickets, chats, capturas de tela, testes ou tokens de
   continuidade.
2. O Bitwarden da organizacao tem custodiante unico; nao compartilhe essa conta.
   Solicite ao dono do servico contas individuais de desenvolvimento com MFA.
   Acesso a credenciais nao e autorizacao para acessar ou exportar dados de
   producao.
3. Desenvolva contra ambiente e dados ficticios/de homologacao. Qualquer acesso
   a producao ou dado pessoal exige aprovacao explicita do responsavel.
4. Nao rode `git add .` para publicar. Confira `git status`, selecione arquivos
   explicitamente e solicite revisao por pull request.
5. Nao modifique schema ou dados do ERP diretamente sem plano, backup, revisao,
   aprovacao e validacao em homologacao.
6. Nao suponha que notas antigas, contagens neste documento ou estado do banco
   ainda estejam atuais. Registre a fonte e a data ao confirmar.

## Visao do sistema

O repositorio contem um sistema PHP que integra um portal web, uma API Slim e
modulos operacionais. O banco PostgreSQL e externo ao repositorio.

| Area | Localizacao |
| --- | --- |
| Gateway do portal e da API | `index.php` |
| Roteador do servidor PHP local | `router.php` |
| Bootstrap Slim, ambiente e PDO | `v1/bootstrap/app.php` |
| Registro das rotas da API | `v1/routes/api.php` |
| Controllers e services | `v1/src/Controllers`, `v1/src/Services` |
| Portal web | `portal/` |
| Modulos do portal | `portal/modules/` |
| PWA do motorista | `portal/modules/frota/` |
| Documentacao e SQL revisavel | `docs/` |

O contrato publicado da API e `/v1`. A pasta `/v2` e uma ponte de
compatibilidade que encaminha para a V1; nao e uma API V2 implementada de forma
independente. Confirme `v2/README.md` e `v2/index.php` antes de alterar esse
comportamento.

Os grupos de rotas incluem autenticacao/perfil, Frota, separacao,
carregamento, monitor, XML, auditoria, inventario, financeiro, marketing/CRM,
deposito, previsao de estoque, desembarque, cron, vendas, analise de carteira,
chat, consulta e administracao. A lista definitiva esta em
`v1/routes/api.php`; nao mantenha contagens manuais de endpoints.

### Frota: fluxo principal

O fluxo documentado do modulo e importar/organizar embarques, associar motorista
e veiculo, executar entregas (inclusive com uso offline no PWA), sincronizar os
registros e fazer o acerto administrativo. A regra de vinculo implementada no
login e `usuario.idcliforemp = frota_motorista.erp_id`. Consulte o
[guia do portal](PORTAL_GUIDE.md) e a implementacao antes de alterar regras
operacionais ou sincronizacao.

## Preparar desenvolvimento local

Pre-requisitos a confirmar na maquina: PHP compativel com `composer.json`,
Composer, extensoes PHP exigidas pelas dependencias e acesso a um PostgreSQL
autorizado para desenvolvimento/homologacao. O PhpSpreadsheet requer a extensao
`gd` neste ambiente.

```powershell
cd C:\xampp\htdocs\API
composer install
if (-not (Test-Path .env)) { Copy-Item .env.example .env }
```

Edite `.env` localmente com valores de desenvolvimento autorizados obtidos pelo
processo em [Acesso seguro](SECRETS_ACCESS.md). O bootstrap exige
`CHAVE_SECRETA`, `API_TOKEN` e `CRON_TOKEN`; configure tambem `JWT_SECRET` com
valor forte, pois alguns handlers o utilizam. Use valores exclusivos para
desenvolvimento, nunca os de producao. Configure os dados do banco de
desenvolvimento. Nao copie um `.env` de outro programador nem sobrescreva um
`.env` local existente.

Inicie o gateway:

```powershell
php -S localhost:8080 router.php
```

Verifique no navegador ou com `curl.exe`:

```text
http://localhost:8080/portal/
http://localhost:8080/ping
http://localhost:8080/v1/ping
http://localhost:8080/v2/ping
```

No navegador, o portal pode construir a URL local da API por
`/index.php?api_route=...`; o gateway principal e `portal/assets/js/config.js`.
Nao conclua que uma rota falhou usando somente uma URL copiada de outro ambiente.

## Validacao e fluxo de trabalho

- Este repositorio nao possui atualmente uma suite de testes propria/configurada
  na raiz. Verifique isso de novo antes de adicionar uma nova ferramenta.
- `php -l <arquivo.php>` e `node --check <arquivo.js>` validam sintaxe, nao
  comportamento, autorizacao, banco nem integracoes.
- Para mudancas de API, revise middlewares e permissoes, exercite a rota em
  homologacao e valide respostas de sucesso/erro com dados ficticios.
- Para mudancas no PWA, valide modo online, offline, fila pendente, retorno de
  conexao e confirmacao do servidor; nao considere dados sincronizados antes
  da confirmacao da API.
- Para operacoes de banco/ERP, revise o SQL, obtenha backup e aprovacao, execute
  primeiro em homologacao e documente o resultado sem incluir dados pessoais.

Antes de iniciar e encerrar uma tarefa:

```powershell
git status --short
git diff --check
git diff
```

Nao sobrescreva nem descarte alteracoes locais de outra pessoa. Revise os
arquivos staged e o destino do push; nao publique diretamente na branch
principal sem seguir o processo de revisao da equipe.

## Banco, integracoes e producao

- O PostgreSQL e externo e pode conter dados operacionais/ERP. Este repositorio
  nao e uma fonte completa nem autoritativa do schema instalado.
- Trate tabelas do ERP como interface externa/legada. Escritas existentes da
  aplicacao nao autorizam novas escritas ou alteracoes de schema fora do fluxo
  aprovado pelo responsavel do ERP.
- `ERP_PEDIDO_SANDBOX` tem efeito operacional: confirme o valor no ambiente
  alvo; nao habilite insercao real sem autorizacao, backup e teste controlado.
- Crons, hospedagem, DNS, backup, disponibilidade da Cobli/Google e estado dos
  servicos de producao nao sao comprovaveis apenas lendo este checkout.
- Consulte [Deploy](DEPLOY_FTP_CPANEL.md), [Valhalla](VALHALLA_SETUP.md) e
  [Acesso seguro](SECRETS_ACCESS.md). O cofre e restrito ao custodiante; nao
  teste deploy ou integracao em producao sem aprovacao.

## Documentacao do repositorio

- [Guia de entrega para CEO e programador](GUIA_ENTREGA_CEO_PROGRAMADOR.md):
  roteiro de reuniao, apresentacao do negocio e transicao segura de acessos.
- [Handoff seguro para assistentes de IA](AI_HANDOFF.md): allowlist seletiva e
  exclusoes obrigatorias para contexto compartilhavel, sujeito a aprovacao.
- [Arquitetura](ARCHITECTURE.md): entradas, autenticacao e fluxo da Frota.
- [Guia do portal](PORTAL_GUIDE.md): modulos e regras operacionais.
- [Deploy](DEPLOY_FTP_CPANEL.md): preparacao e verificacao de publicacao.
- [Valhalla](VALHALLA_SETUP.md): roteamento opcional.
- [SQL de idempotencia offline](frota_offline_idempotencia.sql) e
  [migracao de tratamento de problemas](frota_problema_tratamento_migration.sql):
  scripts revisaveis; nao executar sem validar escopo e ambiente.
- [Token de continuidade atual](tokens/CONTINUATION-2026-10-15.md): handoff
  conciso para a proxima manutencao.
- [Historico dos tokens](tokens/README.md): snapshots antigos, nao autoritativos.

## Primeira semana de continuidade

1. Receber acesso individual e com MFA ao repositorio e aos servicos de
   desenvolvimento/homologacao necessarios; o Bitwarden permanece restrito ao
   custodiante.
2. Preparar o ambiente local com banco de desenvolvimento e confirmar os pings.
3. Ler arquitetura, guia do portal e os controllers/rotas do modulo que vai
   manter.
4. Mapear operacoes criticas com o responsavel de negocio e do banco; nao
   inferir schema ou regras somente a partir de tokens antigos.
5. Registrar gaps encontrados (testes, migrations, deploy/rollback, donos de
   servicos) e propor pequenas melhorias revisaveis, sem iniciar uma migracao
   ampla sem escopo aprovado.

Atualize este handbook quando uma mudanca arquitetural for publicada. Para
detalhes de uma sessao, atualize o token atual sem inserir segredos e confirme
o estado do codigo e do banco com seus responsaveis.
