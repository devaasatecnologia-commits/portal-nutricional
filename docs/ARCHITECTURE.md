# Arquitetura Oficial

## Visao geral

O projeto e um monolito PHP com tres entradas integradas:

1. `index.php` normaliza o caminho e encaminha portal e API.
2. `v1/bootstrap/app.php` cria o Slim, carrega o ambiente, PDO e middlewares.
3. `v1/routes/api.php` registra autenticacao, administracao e Frota.

O document root recomendado para desenvolvimento e a raiz do projeto com:

```powershell
php -S localhost:8080 router.php
```

O PHP CLI precisa da extensao `gd` habilitada para instalar o PhpSpreadsheet.

Em Apache/XAMPP, o projeto pode ser acessado sob `/API`. Os dois ambientes
devem responder ao mesmo gateway antes de validar uma funcionalidade.

## Fluxo de autenticacao

1. `POST /v1/auth/login` valida o usuario legado.
2. O JWT retorna `idusuario`, `idcliforemp` e permissoes.
3. Motoristas sao identificados por `usuario.idcliforemp = frota_motorista.erp_id`.
4. Usuarios com `motorista_id` sao direcionados ao app offline.

As permissoes do portal dependem de `sistema_modulos`, `sistema_setores` e
`usuario_permissoes`. Administradores ativos em `usuarios_admin` recebem acesso
global conforme o controller de autenticacao.

## Fluxo da Frota

1. Gestor importa embarques e pedidos do ERP.
2. O gestor cria ou agrupa uma rota em `frota_embarque`.
3. Entregas e clientes sao persistidos em `frota_entrega` e `frota_cliente`.
4. A rota pode ser ordenada manualmente ou otimizada por coordenadas.
5. O motorista consulta a rota pelo app offline.
6. Check-in, checkout, fotos, checklist e ocorrencias persistem nas tabelas de
   entrega e ficam em fila quando o dispositivo esta sem conexao.
7. Ao retornar, o gestor inicia e finaliza o acerto em
   `frota_acerto_embarque`, com pedidos e itens de acerto.
8. Problemas do checkout são registrados em `frota_entrega_problema` para a
   Gestão de Cargas.

## Tabelas principais

- Acesso: `usuario`, `sistema_setores`, `sistema_modulos`,
  `usuario_permissoes`, `usuarios_admin`.
- Rota: `frota_embarque`, `frota_entrega`, `frota_cliente`, `frota_veiculo`,
  `frota_motorista`.
- Operacao: `frota_checkin`, `frota_checklist_entrega`, `frota_ocorrencia`,
  `frota_entrega_foto`, `frota_historico_posicao`, `frota_notificacao`.
- Acerto: `frota_acerto_embarque`, `frota_acerto_pedido`, `frota_acerto_item`,
  `frota_entrega_problema`, `frota_entrega_timeline`.

Os valores monetários e quantidades operacionais vêm de `pedido_item.valortotal`
e `pedido_item.qt`, relacionados pelos IDs ERP em `frota_entrega.pedidos_ids`.

## Versionamento V2

O codigo atual usa namespace e autoload `Nutricional\\` em `v1/src` e registra
rotas sob `/v1`. Existe uma ponte inicial em `v2/index.php`; ela encaminha
temporariamente as requisicoes para os mesmos contratos estaveis da V1.

A estrategia segura e:

- manter `/v1` funcionando;
- criar contratos e testes para cada grupo de rotas;
- substituir a ponte por implementacoes V2 grupo a grupo;
- migrar consumidores gradualmente;
- remover `/v1` somente depois de medir uso e publicar deprecacao.

Renomear a pasta `v1` sem essa transicao quebra autoload, URLs, frontend e
integracoes externas.

## Operacao segura

- `.env`, logs, uploads, backups e arquivos de teste nao devem ser publicados.
- Segredos devem existir somente no ambiente de execucao.
- O bootstrap deve falhar se segredos obrigatorios estiverem ausentes.
- Testes devem usar usuarios e dados ficticios.