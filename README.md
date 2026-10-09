# Portal Nutricional

Sistema legado e API REST para operacoes da Nutricional, com portal web, gestao
de frotas, entregas, acerto administrativo e aplicativo offline do motorista.

## Estado atual

- Backend: PHP 8.2+, Slim 4 e PostgreSQL.
- Frontend: PHP, JavaScript, Alpine.js e Tailwind via CDN.
- API atual: `/v1`.
- Frota: embarques ERP, rotas, entregas, rastreamento, acerto e motorista offline.
- App motorista: GPS, fila offline, ordenação de rota, notificações e tema escuro.
- Gestão de Cargas: problemas, KPIs operacionais e valores reais do ERP.
- Branch principal: `main`.

## Desenvolvimento local

```powershell
cd C:\xampp\htdocs\API
composer install
if (-not (Test-Path .env)) { Copy-Item .env.example .env }
# Preencha .env com credenciais de desenvolvimento obtidas pelo processo seguro.
php -S localhost:8080 router.php
```

No XAMPP, habilite a extensao `gd` no `php.ini` antes do Composer, pois ela e
exigida pelo PhpSpreadsheet.

Acesse `http://localhost:8080/portal/`.

O `.env.example` documenta os nomes das variaveis; `.env` e local e obrigatorio.
Use credenciais e banco de desenvolvimento. Nunca publique `.env`, credenciais,
tokens ou dados reais no repositorio, na documentacao ou em scripts de teste.

## Arquitetura

- `index.php`: gateway unico para portal, arquivos estaticos e API.
- `v1/bootstrap/app.php`: bootstrap Slim, PDO e middlewares.
- `v1/routes/api.php`: registro das rotas.
- `v1/src/Controllers`: controllers da API.
- `portal`: telas e assets do portal.
- `portal/modules/frota`: telas da frota e app offline.
- `docs`: documentacao oficial e token de continuidade.

## Documentacao oficial

- [Arquitetura e operacao](docs/ARCHITECTURE.md)
- [Handbook de continuidade](docs/HANDBOOK.md)
- [Guia de entrega para CEO e programador](docs/GUIA_ENTREGA_CEO_PROGRAMADOR.md)
- [Manual para o CEO (Word)](docs/GUIA_ENTREGA_CEO_PROGRAMADOR.docx)
- [Manual para o CEO (PDF)](docs/GUIA_ENTREGA_CEO_PROGRAMADOR.pdf)
- [Handoff seguro para assistentes de IA](docs/AI_HANDOFF.md)
- [Guia completo do portal](docs/PORTAL_GUIDE.md)
- [Token de continuidade](docs/CONTINUATION_TOKEN.md)
- [Acesso seguro a credenciais e servicos](docs/SECRETS_ACCESS.md)
- [Deploy por FTP/cPanel](docs/DEPLOY_FTP_CPANEL.md)
- [Valhalla self-hosted](docs/VALHALLA_SETUP.md)
- [Idempotencia offline](docs/frota_offline_idempotencia.sql)
- [Historico de tokens de continuidade](docs/tokens/README.md)

## Banco

O banco e externo ao repositorio e usa PostgreSQL. O schema real nao e
versionado integralmente neste repositorio; confirme tabelas, funcoes e views
no ambiente autorizado antes de alterar. As mudancas estruturais devem ser
entregues como SQL revisavel e aplicadas primeiro em homologacao, com backup e
aprovacao do responsavel pelo banco.

## Frota e motorista

O módulo Frota inclui Embarques, Gestão de Cargas, Acerto de Embarque e o PWA
do motorista. O PWA suporta rota cacheada, GPS, ordenação local, Google Maps e
Waze, check-in, checkout com romaneio e fotos por item, problemas de faltante ou
devolução e sincronização manual/automática.

O fluxo recomendado é: finalizar o embarque pelo motorista, iniciar o acerto,
conferir pedidos e evidências, criar pedidos ERP quando necessário e finalizar
o acerto. Consulte o guia de deploy antes de publicar via FTP.

## Versionamento da API

A API em producao continua em `/v1`. A migracao para `/v2` deve ser feita por
compatibilidade gradual, com rotas espelhadas, testes de contrato e periodo de
deprecacao. Nao renomeie a pasta `v1` diretamente.

## Seguranca e continuidade

Segredos de Google, FTP/SFTP/cPanel, Cobli, PostgreSQL e demais integracoes
ficam no Bitwarden da organizacao, atualmente restrito a um custodiante. O
programador deve receber contas individuais de desenvolvimento nos proprios
servicos, com MFA e permissao minima; nunca a conta do cofre nem acesso
automatico a producao. Consulte [o processo](docs/SECRETS_ACCESS.md).
