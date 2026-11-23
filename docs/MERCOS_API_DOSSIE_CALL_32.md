# Dossiê Técnico — Call MERCOS (Chamado #32: Integração Direta Portal Nutricional ↔ API Mercos)

> **Objetivo da Reunião (24/11/2026 às 15:30):** Viabilizar a substituição completa dos dois integradores Java (`ema-mercos-integration.exe` portas `8081` F1 / `8086` F6) + bancos Firebird (`DE_PARA_MERCOS.FDB` / `DE_PARA_MERCOS-F6.FDB`) por **integração nativa direta do Portal Nutricional (PHP + PostgreSQL `ema` + Webhooks + Crons)** com a API oficial `https://app.mercos.com/api`.

---

## 1. Diagnóstico Real Testado Hoje (23/11/2026) na API Oficial Mercos
Testes `GET` somente leitura executados contra `https://app.mercos.com/api` usando `ApplicationToken` atual (EMA Software) + `CompanyToken` F1:

| Endpoint Testado | HTTP | Latência | Resultado Observado | Observação Técnica |
| :--- | :---: | :---: | :--- | :--- |
| `/v2/pedidos?alterado_apos=2026-11-23%2000:00:00` | `200` | `1.468ms` | `147` pedidos | Sem `alterado_apos`, dá timeout tentando ler histórico completo. |
| `/v1/titulos?alterado_apos=2026-11-23%2000:00:00` | `200` | `276ms` | `371` títulos | Retorna `id, cliente_id, numero_documento, valor, data_vencimento, data_pagamento, excluido`. |
| `/v1/clientes?limit=1` | `200` | `1.137ms` | `500` itens | Paginação fixa de 500 por página com header `meuspedidos_limitou_registros: 1`. |
| `/v1/saldo_flex` | `200` | `166ms` | `29` contas (F1) | **Já migrado para conexão direta** no `MercosFlexService.php` via `SecretVault` + `usuarios_mercos.idmercos`. |
| `/v1/produtos`, `/v1/tabelas_preco`, `/v1/formas_pagamento`, `/v1/condicoes_pagamento`, `/v1/transportadoras` | **`401`** | `141–1.033ms` | Bloqueado no `GET` | **Ponto crítico para a call:** `ApplicationToken` atual (EMA) bloqueia `GET` dessas rotas (só permite `PUT`/`POST` de envio). |
| `/v1/webhooks` | `404` | `195ms` | Não listado via API | Webhooks são configurados no painel web Mercos (*Minha Conta > Sistema > Webhooks*). |

---

## 2. Mapa Ponta a Ponta: Integrador Atual vs. Portal Nutricional (PostgreSQL `ema`)

| Fluxo | Como funciona hoje (Java + Firebird) | Como será no Portal (API Mercos + Postgres `ema`) |
| :--- | :--- | :--- |
| **Autenticação** | `ApplicationToken` da EMA Software + `CompanyToken` F1/F6 | **`ApplicationToken` próprio da Nutricional** + `CompanyToken` F1/F6 no `SecretVault` (AES-256-GCM) |
| **De-Para de IDs** | Firebird `DE_PARA_MERCOS.FDB` (F1) e `DE_PARA_MERCOS-F6.FDB` (F6) | Tabela nativa `mercos_depara` no Postgres `ema` (importada integralmente dos `.FDB` antes da virada) |
| **Entrada de Pedidos** | Polling Java `/v2/pedidos` → grava `palmtop_pedido` + `palmtop_pedido_item` | **Webhook `pedido.gerado`** → endpoint `/v1/webhooks/mercos` + Cron de contingência `/v2/pedidos?alterado_apos=` (mantendo transação `1` e exceções `948943->66`, `17447->3`, `23248->6`) |
| **Faturamento / Status** | Java lê `nfs`/`pedido` e envia para Mercos | Portal envia `POST /v1/faturamento` (`pedido_id, numero_nf, valor_faturado, data_faturamento, itens_faturados`) + `PUT /v2/pedidos/{id}` |
| **Estoque** | Envia saldo físico do **Depósito 1** (`estoque_filial_deposito WHERE iddeposito = 1`) | Cron / Trigger leve enviando `POST/PUT /v1/estoques` apenas para SKUs movimentados do **Depósito 1** |
| **Produtos & Tabelas (1–15)** | Sync Java de `item` + tabelas `1..15` (janela `08:00–08:05`) | Sync incremental de `item` / tabelas `1..15` via `/v1/produtos` e `/v1/tabelas_preco` |
| **Clientes & Títulos** | Sync bidirecional de `cliforemp`/`cliente` e `receber` | Webhook `cliente.atualizado` + Cron incremental `alterado_apos` em `/v1/clientes` e `/v1/titulos` |
| **Verba Flex** | Java `:8081/:8086/saldo_flex` (hoje retornando `[]`!) | **Já em produção no Portal** (`MercosFlexService.php` direto em `/v1/saldo_flex` `GET` e `POST`) |

---

## 3. Roteiro & Perguntas Estratégicas para a Call de Amanhã (15:30)

### Bloco A — Credenciais, Escopo & Ambiente
1. **"Queremos um `ApplicationToken` próprio da Nutricional (ERP Próprio) para substituir o integrador terceiro da EMA Software."**
   - Como fazemos a emissão do `ApplicationToken` próprio e também de credenciais no **Sandbox (`sandbox.mercos.com/api`)** para homologarmos antes de virar a chave em produção?
   - Com o nosso `ApplicationToken` próprio, teremos acesso completo (`GET`, `POST`, `PUT`, `DELETE`) a **Produtos, Estoques, Tabelas de Preço, Formas/Condições de Pagamento, Transportadoras, Clientes, Pedidos V2, Faturamento e Saldo Flex**?
   - *(Mencionar que no token atual da EMA Software, `GET /v1/produtos`, `/v1/tabelas_preco` e `/v1/condicoes_pagamento` retornam `HTTP 401`.)*

### Bloco B — Migração Sem Quebra (Zero Duplicidade)
2. **"Os IDs internos da Mercos (`cliente_id`, `produto_id`, `pedido_id`, `colaborador_id`) pertencem ao `CompanyToken` da empresa ou mudam se trocarmos o `ApplicationToken`?"**
   - *(Objetivo: garantir que podemos migrar os registros de `DE_PARA_MERCOS.FDB` e `DE_PARA_MERCOS-F6.FDB` para o PostgreSQL mantendo exatamente os mesmos `IDMP` sem duplicar nenhum cliente, produto ou pedido na virada!)*
3. **"Durante a transição, podemos rodar o Portal em modo sombra (leitura) enquanto o integrador atual ainda está ativo por alguns dias?"**

### Bloco C — Webhooks, Tempo Real & Rate Limit
4. **"Quais eventos de Webhook estão disponíveis hoje no nosso plano?"**
   - Confirmar: `pedido.gerado` / `pedido.atualizado`, `pedido.faturado`, `pedido.cancelado`, `cliente.cadastrado` / `cliente.atualizado`.
   - O Webhook envia algum **header de assinatura (HMAC / secret)** para validarmos autenticidade no nosso endpoint `https://api.nutricionalbr.com/v1/webhooks/mercos`?
   - Qual a política de **retry** da Mercos se o nosso endpoint responder `5xx` ou timeout?
5. **"Qual é o Rate Limit exato (requisições por segundo/minuto) por `CompanyToken` e por `ApplicationToken`?"**
   - Temos 2 empresas/filiais (`F1 Matriz SC` e `F6 Filial PR`) rodando sob o mesmo `ApplicationToken`. O limite é contado por `CompanyToken` ou somado no `ApplicationToken`?
   - Existe endpoint de atualização de **estoque em lote** (`POST /v1/estoques` ou similar) para evitar estourar `HTTP 429` quando vários SKUs mudam de saldo?

### Bloco D — Pedidos V2, Faturamento & Regras de Negócio
6. **"No `GET /v2/pedidos`, quando um pedido tem múltiplos itens, descontos, IPI/ST ou observação, qual campo distingue orçamento vs. pedido efetivado (`status`) e como evitar reimportar um pedido já capturado?"**
   - Confirmar se `PUT /v2/pedidos/{id}` ou `POST /v1/faturamento` aceita `itens_faturados` no nosso plano atual para faturamento parcial/total.
7. **"No `/v1/saldo_flex`, já estamos operando direto via API Cloud com sucesso (`GET` e `POST`). Existe alguma restrição de concorrência ou limite específico para lançamentos sequenciais (transferências entre vendedores)?"**

---

## 4. Checklist de Saída da Reunião
- [ ] Solicitar **ApplicationToken próprio da Nutricional** + conta **Sandbox** (`sandbox.mercos.com`).
- [ ] Confirmar **preservação dos IDs (`IDMP`)** ao trocar de `ApplicationToken` mantendo os `CompanyToken` F1/F6.
- [ ] Confirmar **lista exata de Webhooks + assinatura/validação** e **Rate Limit por minuto**.
- [ ] Confirmar contrato de **`POST /v1/estoques` (lote)** e **`POST /v1/faturamento`** para o nosso plano.
- [ ] Após a call: atualizar o Chamado `#32` com as respostas oficiais e iniciar a Fase 1 (tabela `mercos_depara` + modo sombra no Portal).
