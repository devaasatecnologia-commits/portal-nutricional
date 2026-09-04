# Guia Completo do Portal

## Acesso local

Use o router oficial para manter API e portal no mesmo gateway:

```powershell
cd C:\xampp\htdocs\API
php -S localhost:8080 -t C:\xampp\htdocs\API C:\xampp\htdocs\API\router.php
```

URLs de verificacao:

- `http://localhost:8080/portal/`
- `http://localhost:8080/ping`
- `http://localhost:8080/v1/ping`
- `http://localhost:8080/v2/ping`

## Modulos do portal

O menu e carregado por `sistema_setores` e `sistema_modulos`. O acesso do
usuario comum depende de `usuario_permissoes`; administradores ativos em
`usuarios_admin` possuem acesso administrativo conforme as regras do sistema.

O valor de `sistema_modulos.url` deve ser uma rota existente no portal. O slug
deve ser usado nas permissoes, nunca o nome visual do modulo.

## Modulo TI/Admin

- usuarios: cadastro, ativacao e permissoes;
- permissoes: vinculo de modulos, filiais e gestores;
- setores: liberacao em grupo;
- crons: jobs e execucoes;
- API tokens: tokens de integracao;
- auditoria: rastreabilidade administrativa.

## Modulo Frota

Fluxo operacional:

1. Importar embarques e pedidos do ERP.
2. Criar ou agrupar uma rota.
3. Associar veiculo e motorista.
4. Geocodificar clientes sem coordenadas.
5. Ordenar manualmente ou otimizar a rota.
6. Iniciar o embarque.
7. Motorista executa check-in, entrega, fotos, checklist e ocorrencias.
8. Sincronizar dados armazenados offline.
9. Finalizar o embarque.
10. Gestor executa o acerto administrativo.

### Motorista offline

O usuario do motorista e ligado ao cadastro por:

`usuario.idcliforemp = frota_motorista.erp_id`

O login retorna o `motorista_id` e direciona o usuario para o app offline. O
manifest e o service worker ficam em `portal/modules/frota`.

O checkout exige nome do recebedor e foto do romaneio. A fila local deve ser
tratada como pendente ate a API confirmar a operacao.

## API e banco

As rotas estaveis ficam em `v1/routes/api.php`. A ponte `/v2` encaminha para os
mesmos contratos enquanto a migracao nao for concluida.

O banco PostgreSQL e externo ao repositorio. Tabelas centrais:

- acesso: `usuario`, `sistema_modulos`, `sistema_setores`, `usuario_permissoes`;
- frota: `frota_embarque`, `frota_entrega`, `frota_cliente`, `frota_motorista`, `frota_veiculo`;
- operacao: `frota_checkin`, `frota_ocorrencia`, `frota_notificacao`, `frota_historico_posicao`;
- acerto: `frota_acerto_embarque`, `frota_acerto_pedido`, `frota_acerto_item`.

### Pedidos ERP do acerto

O acerto usa as transacoes ERP:

- `19`: faltante;
- `20`: devolucao.

O fluxo grava primeiro o pedido de acerto na Frota. A criacao em
`palmtop_pedido` e `palmtop_pedido_item` permanece em Sandbox por padrao e
gera SQL para conferencia. Para habilitar insercao real, configure somente no
ambiente controlado:

```dotenv
ERP_PEDIDO_SANDBOX=false
```

Depois da insercao confirmada, o pedido de acerto e marcado como `processado`
para impedir duplicidade. Nunca habilite producao sem backup e teste de um
cliente/item controlado.

## Checklist de deploy

- [ ] `.env` preenchido somente no servidor.
- [ ] `CHAVE_SECRETA`, `API_TOKEN` e `CRON_TOKEN` rotacionados.
- [ ] extensao PHP `gd` habilitada.
- [ ] `composer install` executado.
- [ ] PostgreSQL acessivel e com backup recente.
- [ ] `/ping`, `/v1/ping` e `/v2/ping` respondendo.
- [ ] `.env`, logs e backups retornando `403`.
- [ ] login testado com usuario de homologacao.
- [ ] fluxo Frota e acerto testados sem dados reais destrutivos.

## Regras de limpeza

Arquivos temporarios, diagnosticos, credenciais, logs e backups nao devem ser
versionados. Documentacao antiga deve ser consolidada em `docs/`, sem manter
copias divergentes na raiz.
