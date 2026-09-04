# Portal Nutricional

Sistema legado e API REST para operacoes da Nutricional, com portal web, gestao
de frotas, entregas, acerto administrativo e aplicativo offline do motorista.

## Estado atual

- Backend: PHP 8.2+, Slim 4 e PostgreSQL.
- Frontend: PHP, JavaScript, Alpine.js e Tailwind via CDN.
- API atual: `/v1`.
- Frota: embarques ERP, rotas, entregas, rastreamento, acerto e motorista offline.
- Branch principal: `main`.

## Desenvolvimento local

```powershell
cd C:\xampp\htdocs\API
composer install
php -S localhost:8080 router.php
```

No XAMPP, habilite a extensao `gd` no `php.ini` antes do Composer, pois ela e
exigida pelo PhpSpreadsheet.

Acesse `http://localhost:8080/portal/`.

O arquivo `.env` e obrigatorio. Nunca publique credenciais, tokens ou senhas no
repositorio, na documentacao ou em scripts de teste.

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
- [Guia completo do portal](docs/PORTAL_GUIDE.md)
- [Token de continuidade](docs/CONTINUATION_TOKEN.md)

## Banco

O banco e externo ao repositorio e usa PostgreSQL. As alteracoes estruturais
devem ser entregues como SQL revisavel e executadas com backup previo.

## Versionamento da API

A API em producao continua em `/v1`. A migracao para `/v2` deve ser feita por
compatibilidade gradual, com rotas espelhadas, testes de contrato e periodo de
deprecacao. Nao renomeie a pasta `v1` diretamente.
