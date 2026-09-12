# Deploy por FTP/cPanel

## Antes do upload

1. Faça backup do banco PostgreSQL.
2. Revogue e gere novamente qualquer segredo que tenha sido exposto.
3. Não envie `.env`, logs, backups, tokens ou chaves para o GitHub/FTP.
4. Rode `composer install --no-dev --optimize-autoloader` no ambiente que tiver Composer, ou envie `vendor/` somente se a hospedagem não permitir Composer.

## Arquivos e pastas principais

- `index.php`: gateway da aplicação.
- `router.php`: servidor PHP local.
- `v1/`: API Slim, controllers, middleware e rotas.
- `portal/`: portal web.
- `portal/modules/frota/`: Embarques, Gestão de Cargas, Acerto e Motorista Offline.
- `portal/assets/`: scripts compartilhados, autenticação e SweetAlert.
- `docs/`: documentação e SQL revisável.
- `vendor/`: dependências Composer.

## Configuração no cPanel

1. Crie o banco e usuário PostgreSQL conforme o serviço disponível.
2. Configure o document root para a pasta pública correta do projeto.
3. Envie os arquivos via FTP preservando a estrutura.
4. Crie `.env` no servidor a partir de `.env.example` e preencha somente no servidor.
5. Configure permissões de escrita para `portal/uploads/`, `logs/` e diretórios de cache necessários.
6. Ative HTTPS antes de testar login, GPS e upload de fotos.
7. Configure cron jobs para as rotinas existentes, usando `CRON_TOKEN` no ambiente.
8. Teste `/ping`, login, `/v1/ping`, Embarques, Gestão de Cargas e Acerto.

## Motorista Offline

O Service Worker fica em `portal/modules/frota/service-worker.js`. Após publicar uma versão nova, atualize o cache do navegador no primeiro teste. O app mantém a rota e a fila localmente, mas precisa de conexão para sincronizar fotos e checkouts.

## Fotos

- Fotos de produtos podem continuar em `https://acesso.nutricionalbr.com:2053/fotos/`.
- Fotos capturadas pelo motorista devem ser salvas na API em `portal/uploads/frota/entregas/`.
- Bloqueie execução de scripts dentro de `uploads`.
- Faça backup dos uploads.

## Valhalla

Hospedagem compartilhada/cPanel não costuma permitir Valhalla persistente. O projeto aceita um serviço separado via `VALHALLA_URL`. Sem Valhalla, usa fallback local/offline e Google Maps/Waze.

## Pós-deploy

```text
GET /ping
POST /v1/auth/login
GET /v1/ping
GET /v1/frota/dashboard/kpis
GET /v1/frota/dashboard/embarques-ativos
GET /v1/frota/acerto/embarques
```

Nunca coloque tokens, senhas ou chaves reais neste documento.
