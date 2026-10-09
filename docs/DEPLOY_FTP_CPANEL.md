# Deploy por FTP/cPanel

## Antes do upload

1. Faça backup do banco PostgreSQL.
2. Revogue e gere novamente qualquer segredo que tenha sido exposto.
3. Não envie `.env`, logs, backups, tokens ou chaves para o GitHub nem para o
   servidor de arquivos.
4. Rode `composer install --no-dev --optimize-autoloader` no ambiente que tiver Composer, ou envie `vendor/` somente se a hospedagem não permitir Composer.
5. Obtenha autorizacao do responsavel pela publicacao e valide o commit/release.
   Nunca publique alteracoes locais de outras pessoas.

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
3. Prefira SFTP/FTPS com conta individual e privilegio minimo; FTP sem
   criptografia pode expor credenciais e arquivos em transito.
4. Publique somente o artefato revisado, preservando a estrutura. Nao envie
   `.env`, `.git`, dumps, logs, backups, credenciais ou arquivos de diagnostico.
5. Crie/configure `.env` diretamente no servidor por canal seguro, usando
   valores de producao aprovados pelo custodiante do Bitwarden. O custodiante
   deve provisionar a configuracao sem compartilhar a conta do cofre. Nunca
   baixe o `.env` de producao para uma maquina pessoal.
6. Configure permissoes de escrita minimas para `portal/uploads/`, `logs/` e
   diretorios de cache realmente necessarios.
7. Ative HTTPS antes de testar login, GPS e upload de fotos.
8. Configure cron jobs somente apos confirmar comandos e horarios existentes;
   injete `CRON_TOKEN` pelo ambiente, nao pela linha de comando ou repositorio.
9. Teste `/ping`, login, `/v1/ping`, Embarques, Gestao de Cargas e Acerto com
   usuario de homologacao/conta de teste.

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

## Rollback e acessos

Antes da publicacao, registre o release anterior e valide como restaurar
arquivos, configuracao e banco. Rollback de banco requer plano proprio; restaurar
arquivos nao reverte automaticamente mudancas de schema ou dados. Teste o
procedimento sem afetar producao e mantenha backups sob acesso restrito.

Credenciais de hospedagem, banco e integracoes devem permanecer no Bitwarden da
organizacao, restrito ao custodiante. A equipe tecnica usa contas individuais
nos servicos e ambientes autorizados. Consulte [Acesso seguro](SECRETS_ACCESS.md).
Remova credenciais ou dumps do servidor
quando nao forem necessarios e verifique que arquivos de diagnostico (por
exemplo `phpinfo.php`) nao estejam publicados.
