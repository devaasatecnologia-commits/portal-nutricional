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

As operacoes administrativas da Frota exigem permissao `admin`, `frota` ou
`gestao-cargas`. O motorista autenticado pode consultar e operar apenas as
entregas e o veiculo ligados ao `motorista_id` do JWT; a reordenacao offline
tambem valida o embarque atribuido, a versao da rota e a lista completa de
entregas sem duplicatas. Rastreamento geral, administracao da integracao Cobli e
operacoes de cadastro ficam restritos a usuarios de gestao; o motorista so pode
consultar a posicao do proprio veiculo vinculado.

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

## Auditoria de carregamento

A aba Carregamento da Auditoria consulta `/v1/auditoria/carregamentos` pelo
período selecionado e inclui embarques com registros de carga, mesmo depois de
finalizados. O detalhe `/v1/auditoria/dossie/{idembarque}` compara quantidades
previstas, separadas e atualmente carregadas, lista pedidos alocados por subida,
responsável (`usuario.idusuario`), data/hora, doca, foto e estornos com motivo.
O dossiê permite filtrar itens acima do separado, parciais/pendentes e sem
evidência, além de exportar os dados em CSV.

`docs/carregamento_auditoria_migration.sql` cria as alocações imutáveis entre
subidas e itens de pedido, o registro append-only dos estornos e a ligação
direta das fotos às subidas. A migração também impede atualização/exclusão das
novas evidências. Ela deve ser revisada e aplicada primeiro em homologação,
com backup e aprovação do responsável pelo PostgreSQL externo; não é executada
automaticamente pela aplicação. O deploy da API/frontend depende dessa
migração.

Toda reversão exige motivo (3 a 500 caracteres), operador e timestamp. O
carregamento operacional é reduzido na mesma transação em que o evento de
estorno e seu snapshot de alocações são gravados. Subidas e fotos originais não
são apagadas nem renumeradas; a migração também impede apagar subidas e alterar
os seus dados originais. O upload da foto exige vínculo com a subida. A trilha
de alocações exatas passa a valer para
subidas registradas após a migração. Para subidas legadas sem alocação histórica,
o relatório identifica que o pedido/item não pode ser afirmado retroativamente;
estornos legados novos guardam apenas o saldo distribuído no momento do
estorno. Estornos feitos antes desta migração não podem ser reconstruídos.

## Otimizacao de rotas

O otimizador usa vizinho mais proximo e 2-opt, partindo e retornando ao centro
de distribuicao. Ha dois modos, escolhidos pela variavel `VALHALLA_URL`:

- **Sem `VALHALLA_URL` (modo atual de producao):** estimativa em linha reta
  (Haversine) com fator rodoviario 1,3 e velocidade media de 40 km/h. A resposta
  traz `provedor = haversine` e `criterio = menor_distancia_estimada`; a tela
  informa que o valor e estimado. A navegacao do motorista continua pelos links
  do Waze e Google Maps no app offline (`abrirNavegacao`).
- **Com `VALHALLA_URL`:** matriz de tempo rodoviario por caminhao. Se o Valhalla
  estiver indisponivel, o otimizador cai na estimativa e devolve um aviso
  explicito em `data.avisos`; a falha nunca e silenciosa.

A geocodificacao de entregas sem coordenadas usa o Google Maps
(`GOOGLE_MAPS_API_KEY`). Consulte [VALHALLA_SETUP.md](VALHALLA_SETUP.md) para
ativar o Valhalla futuramente.

O service worker nao armazena paginas de login/redirecionamento no cache. A
pagina autenticada do motorista e removida do cache ao sair da conta; a fila
pendente permanece por motorista para nao descartar operacoes offline.

## Tabelas principais

- Acesso: `usuario`, `sistema_setores`, `sistema_modulos`,
  `usuario_permissoes`, `usuarios_admin`.
- Rota: `frota_embarque`, `frota_entrega`, `frota_cliente`, `frota_veiculo`,
  `frota_motorista`.
- Operacao: `frota_checkin`, `frota_checklist_entrega`, `frota_ocorrencia`,
  `frota_entrega_foto`, `frota_historico_posicao`, `frota_notificacao`.
- Acerto: `frota_acerto_embarque`, `frota_acerto_pedido`, `frota_acerto_item`,
  `frota_entrega_problema`, `frota_entrega_timeline`.
- Auditoria de carregamento: `carregamento_subidas`,
  `carregamento_subida_alocacoes`, `carregamento_estornos` e
  `carregamento_fotos`.

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
- O `.env.example` documenta nomes e valores nao secretos; solicite credenciais
  pelo [processo Bitwarden](SECRETS_ACCESS.md). Nao copie `.env` de outra pessoa.
- O schema PostgreSQL e externo e nao esta versionado integralmente. Confirme
  o estado do ambiente antes de qualquer SQL e aplique mudancas primeiro em
  homologacao, com backup, revisao e aprovacao do responsavel.
- Use [HANDBOOK.md](HANDBOOK.md) como indice de continuidade e nao trate
  snapshots em `tokens/` como fonte atual de verdade.