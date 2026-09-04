# Token de Continuidade

Este documento e um contexto tecnico para a proxima sessao. Ele nao contem
senhas, tokens JWT, chaves de API ou credenciais.

## Projeto

- Repositorio: `portal-nutricional`
- Branch: `main`
- API estavel: `/v1`
- Ponte de compatibilidade: `/v2` encaminha temporariamente para `/v1`
- Banco: PostgreSQL externo, configurado por `.env`
- Ultima referencia: `bed6e31` - revisao geral, limpeza e ponte V2.

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

1. Capturar GPS real no app offline em check-in e checkout.
2. Criar notificacao automatica quando uma rota for atribuida/iniciada.
3. Adicionar ordenacao de rota no app do motorista.
4. Implementar checklist, fotos de itens e assinatura no offline.
5. Isolar a fila offline por motorista e criar idempotencia das operacoes.
6. Validar no backend que o motorista autenticado so opera sua propria rota.
7. Corrigir rollbacks em retornos antecipados dentro de transacoes.
8. Migrar senhas MD5 para `password_hash` com compatibilidade temporaria.
9. Substituir a ponte V2 por contratos V2 implementados e testados.
10. Validar em homologacao a criacao ERP com transacoes 19/20 antes de ativar producao.

## Validacao minima

```powershell
php -l index.php
php -l v1/bootstrap/app.php
php -l v1/src/Controllers/AuthController.php
node --check portal/modules/frota/assets/motorista-offline.js
curl http://localhost:8080/ping
```

## Regra de continuidade

Nao renomear `v1` para `v2` diretamente. A ponte `/v2` deve ser substituida
grupo a grupo por contratos V2 testados; somente depois os clientes podem ser
migrados e a `/v1` descontinuada.