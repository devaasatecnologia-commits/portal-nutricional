# ROTEIRO DE GUERRA — CALL MERCOS (HOJE, 24/11/2026 ÀS 15:30)

> **Objetivo Real (Em OFF):** Obter um **`ApplicationToken` próprio da Nutricional** + acesso ao **Sandbox** para rodar em paralelo com o integrador da EMA Software, sem revogar nem afetar o token/integrador atual e sem alertar a EMA Software.
>
> **Narrativa Oficial na Call:** *"O integrador da EMA Software continua rodando normalmente na nossa operação padrão. Precisamos de um `ApplicationToken` próprio para o nosso Portal Corporativo / BI interno fazer **consultas complementares, conferências, auditorias e módulos internos** (e futuramente pequenas atualizações pontuais), sem depender da software house."*

---

## 1. Regra de Ouro na Call (O que Dizer vs. O que NÃO Dizer)
- **NÃO dizer:**
  - Que você já tem o `ApplicationToken` / `CompanyToken` extraídos do `application.properties`.
  - Que vai desligar/substituir o integrador da EMA Software agora.
  - Que pretende construir um aplicativo Força de Vendas próprio no futuro.
- **SE PERGUNTAREM: *"Por que não usam o mesmo token do integrador atual (EMA)?"***
  - **Resposta:** *"Por política de segurança da EMA Software, o `ApplicationToken` fica embarcado/fechado no integrador deles e não é compartilhado para uso em nosso Portal/BI interno. Precisamos de um token próprio da Nutricional para nossas ferramentas internas de auditoria e gestão."*

---

## 2. O que o Portal Vai Consumir (Resposta Técnica sobre `GET`, `POST`, `PUT`, `DELETE`)
Se os técnicos da Mercos perguntarem **"Quais endpoints e métodos vocês vão usar, e como garantem que não vai conflitar com o integrador EMA?"**, responda com esta divisão exata:

### Bloco 1 — Somente Leitura (`GET`) — Zero Conflito com o Integrador EMA
*"Na primeira etapa, nosso Portal interno fará **consultas incrementais (`GET` com `alterado_apos`)** para conferência e BI, sem alterar dados do integrador:"*
- `GET /v2/pedidos?alterado_apos=` → Conferência de pedidos, auditoria de status/itens e metas comerciais (Programa 365).
- `GET /v1/clientes?alterado_apos=` → Análise de carteira, clientes ativos/inativos e conciliação com nosso Portal B2B.
- `GET /v1/titulos?alterado_apos=` → Conferência financeira.
- `GET /v1/produtos?alterado_apos=`, `GET /v1/tabelas_preco`, `GET /v1/formas_pagamento`, `GET /v1/condicoes_pagamento`, `GET /v1/transportadoras` → Auditoria de catálogo, preços (tabelas 1–15) e políticas comerciais.
- `GET /v1/saldo_flex` → Painel gerencial de Verba Flex para gestores e representantes.

### Bloco 2 — Escrita Pontual (`POST` / `PUT`) — Sem `DELETE` e Sem Concorrência
- **`POST /v1/saldo_flex`**: Movimentações gerenciais de Verba Flex (já operado pelo Portal, não conflita com o sync de pedidos/produtos da EMA).
- **Webhooks (passivo):** Receber eventos (`pedido.gerado`, `pedido.faturado`, `pedido.cancelado`, `cliente.atualizado`) no nosso endpoint `https://api.nutricionalbr.com/v1/webhooks/mercos` para reduzir polling `GET` e evitar `HTTP 429`.
- **Sobre `PUT` / `DELETE` em Pedidos, Produtos e Estoque:**
  *"Não faremos `DELETE` nem alteração concorrente nos mesmos registros enquanto o integrador EMA estiver operando nessa frente. Qualquer escrita futura (como envio de rastreamento ou faturamento) será feita por fila única controlada e só após homologação em Sandbox."*

---

## 3. Parâmetros Técnicos que Mostram Domínio na Reunião
- **Headers obrigatórios:** `ApplicationToken` (novo da Nutricional) + `CompanyToken` (F1 Matriz SC e F6 Filial PR) + `Content-Type: application/json`.
- **Paginação Incremental:** Uso obrigatório de `alterado_apos=YYYY-MM-DD HH:MM:SS`, respeitando lotes de até **500 registros** e o header **`MEUSPEDIDOS_LIMITOU_REGISTROS: 1`** para continuar paginando a partir da última `ultima_alteracao`.
- **Controle de Rate Limit:** Fila centralizada no Portal com backoff exponencial ao receber **`HTTP 429`** (respeitando `Retry-After`) e janelas fora de pico para não somar carga com o integrador EMA.

---

## 4. Perguntas Estratégicas para Você Fazer aos Técnicos da Mercos
1. **Emissão do Token + Sandbox:** *"Como emitimos nosso `ApplicationToken` próprio de integrador/ERP interno sem invalidar o `ApplicationToken` que já roda na nossa conta? E vocês liberam uma conta Sandbox (`sandbox.mercos.com`) para testarmos antes?"*
2. **Escopo Completo do Novo Token:** *"Esse novo `ApplicationToken` já vem com permissão total de `GET` em Produtos, Tabelas de Preço, Formas/Condições de Pagamento, Transportadoras, Promoções/Políticas, Clientes, Pedidos V2, Títulos e Saldo Flex?"*
3. **Rate Limit entre 2 `ApplicationToken`s:** *"O limite de requisições por minuto é contado por `CompanyToken` (F1 e F6 separados) ou por `ApplicationToken`? Qual o teto exato por minuto para calibrarmos nossa fila e não atrapalhar o integrador atual?"*
4. **Webhooks:** *"Quais eventos de Webhook temos disponíveis no nosso plano atual, como funciona a assinatura/segredo de validação no header e qual o comportamento de retry?"*
5. **Faturamento, Rastreio e Promoções (para nosso desenho em OFF):**
   - *"Para envio de código de rastreamento ou status de entrega no pedido (`PUT /v2/pedidos/{id}` ou `/v1/faturamento`), qual o payload exato recomendado?"*
   - *"Existe endpoint de leitura/escrita para Regras/Políticas Comerciais e Promoções (`PROMOCAO_DE_PARA`) ou apenas via painel?"*
6. **Preservação de IDs (Pergunta Sutil):** *"Os IDs internos da Mercos (`cliente_id`, `produto_id`, `pedido_id`) são vinculados à empresa (`CompanyToken`), ou seja, o nosso `ApplicationToken` novo enxergará exatamente os mesmos IDs que já existem hoje?"*

---

## 5. Como Operar Comigo ao Vivo no Google Meet (15:30)
1. No Google Meet, clique em **Atividades / Legendas (`CC`)** para ativar a **transcrição/legendas em Português** (ou use `Win + H` / Legendas ao Vivo do Windows).
2. Deixe este chat aberto ao lado da janela do Meet.
3. Assim que o técnico da Mercos responder ou perguntar algo técnico, **cole a fala/resumo aqui no chat**.
4. Eu te devolvo em segundos a **resposta exata para você falar** + a **próxima pergunta estratégica**.
