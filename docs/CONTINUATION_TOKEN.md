# Token de Continuidade

Este documento e um contexto tecnico para a proxima sessao. Ele nao contem
senhas, tokens JWT, chaves de API ou credenciais.

## Projeto

- Repositorio: `portal-nutricional`
- Branch: `main`
- API estavel: `/v1`
- Ponte de compatibilidade: `/v2` encaminha temporariamente para `/v1`
- Banco: PostgreSQL externo, configurado por `.env`
- Ultima referencia: `f24f358` - monitoramento, quantidades ERP e Gestão de Cargas.

## Modulos importantes

- Portal: `portal/index.php`
- Login: `portal/login.php`
- Gateway: `index.php`
- Rotas: `v1/routes/api.php`
- Frota: `portal/modules/frota`
- App offline: `portal/modules/frota/motorista-offline.php`
- Acerto: `portal/modules/frota/acerto-embarque.php`
- Administracao: `portal/modules/admin`

## Vinculo motorista

O usuario do motorista deve usar o mesmo identificador ERP:

`usuario.idcliforemp = frota_motorista.erp_id`

O login retorna `motorista_id` e direciona o usuario para o aplicativo offline.
O modulo liberado usa o slug `motorista-offline` em `usuario_permissoes`.

## Pendencias prioritarias

1. Implementar checklist visual, fotos dos itens e assinatura no offline.
2. Criar push nativo quando o aplicativo estiver fechado.
3. Criar idempotencia persistida para todas as operações offline.
4. Implementar conflitos quando o gestor alterar uma rota durante o offline.
5. Revisar a função PostgreSQL `calcular_estatisticas_motorista()` para usar `pedido_item`.
6. Corrigir rollbacks em retornos antecipados dentro de transações.
7. Substituir a ponte V2 por contratos V2 implementados e testados.
8. Validar em homologação a criação ERP com transações 19/20 antes de produção.

## Validacao minima

```powershell
php -l index.php
php -l v1/bootstrap/app.php
php -l v1/src/Controllers/AuthController.php
node --check portal/modules/frota/assets/motorista-offline.js
curl http://localhost:8080/ping
```

## Ultimas entregas

- `a701bd0`: GPS, fila premium, ordenação e autorização do motorista.
- `fb3a3ad`: notificação automática ao iniciar rota.
- `bacbafb`: tema escuro persistente no app motorista.
- `24c23da`: layout e filtros do Acerto.
- `e8670b6`: correção estrutural da Gestão de Cargas.
- `6e907f8`: problemas gerados automaticamente no checkout.
- `f24f358`: KPIs operacionais e valores/quantidades vindos do ERP.

## Regra de continuidade

Nao renomear `v1` para `v2` diretamente. A ponte `/v2` deve ser substituida
grupo a grupo por contratos V2 testados; somente depois os clientes podem ser
migrados e a `/v1` descontinuada.