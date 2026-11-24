# FALAS PRONTAS PARA A CALL MERCOS (HOJE, 15:30)

## 1. Abertura (Explicando o cenário sem denunciar nada)
> *"Pessoal, boa tarde! Obrigado pelo tempo de vocês. Hoje nós usamos a Mercos nas nossas duas unidades — Matriz SC e Filial PR — integradas ao nosso ERP EMA através do integrador padrão da software house (EMA Software), e esse integrador vai continuar rodando normalmente no dia a dia.*
> *O motivo da nossa conversa hoje é que a Nutricional desenvolveu um Portal Corporativo próprio — com BI, acompanhamento de campanhas, análise de carteira e gestão interna — e nós queremos conectar esse nosso Portal diretamente na API oficial da Mercos."*

## 2. Se perguntarem: "Por que não usam o token que já está no integrador da EMA?"
> *"Porque o `ApplicationToken` da EMA Software fica fechado dentro da aplicação deles — por política de software house, eles não liberam a chave deles para nós consumirmos no nosso Portal interno. Então nós precisamos emitir um `ApplicationToken` próprio da Nutricional, exclusivamente para o nosso Portal/BI interno, mantendo o integrador da EMA rodando em paralelo sem mexer em nada dele."*

## 3. Quando perguntarem: "O que vocês querem consumir? Vão fazer POST, PUT ou DELETE?"
> *"Nossa preocupação número um é exatamente **não gerar nenhum conflito** com o integrador da EMA. Por isso, nesta etapa:*
> *1. O foco principal é **consulta incremental (`GET` com `alterado_apos`)** para conferência, auditoria e BI interno: pedidos V2, clientes, títulos, produtos, tabelas de preço, formas e condições de pagamento, transportadoras e saldo flex.*
> *2. Em termos de escrita, o único `POST` ativo do nosso lado hoje é o **Saldo Flex (`/v1/saldo_flex`)**, que é gerencial nosso e não concorre com a rotina de pedidos/produtos da EMA. **Não vamos usar `DELETE`** e não vamos alterar os mesmos campos que o integrador atual escreve.*
> *3. Para não sobrecarregar a API com consultas repetitivas, queremos também ativar **Webhooks** (como pedido gerado, faturado, cancelado e cliente atualizado) apontando para o nosso servidor."*

## 4. Perguntas Técnicas para Você Fazer na Sequência
- **Token + Sandbox:**
  > *"Como fazemos para gerar esse `ApplicationToken` próprio sem derrubar o `ApplicationToken` da EMA que já está conectado nos nossos dois `CompanyToken`s? E vocês conseguem liberar uma conta Sandbox (`sandbox.mercos.com`) para homologarmos tudo antes?"*
- **Permissões de Leitura (`GET`):**
  > *"Com o nosso `ApplicationToken` próprio, nós teremos permissão completa de `GET` em Produtos, Tabelas de Preço, Promoções/Políticas, Formas e Condições de Pagamento e Transportadoras?"*
- **Rate Limit (F1 + F6):**
  > *"Como o integrador da EMA já consome a API nas nossas duas empresas (SC e PR), qual é o Rate Limit exato por minuto? Esse limite é por `CompanyToken` ou somado no `ApplicationToken`? Queremos configurar uma fila com backoff no nosso Portal para nunca bater `429`."*
- **Webhooks:**
  > *"Quais eventos de Webhook estão liberados no nosso plano hoje, como validamos a assinatura/segredo no header e como funciona o retry se nosso servidor oscilar?"*
- **Rastreio, Faturamento e Promoções (extração estratégica):**
  > *"Se no futuro quisermos enviar código de rastreamento da nossa frota ou status de entrega para o pedido aparecer para o representante no app da Mercos, qual rota/campo vocês recomendam (`PUT /v2/pedidos/{id}` ou `/v1/faturamento`)? E existe API para consultar ou criar Promoções / Políticas Comerciais?"*
- **IDs internos (pergunta sutil):**
  > *"Só para confirmarmos no nosso BI: os IDs internos da Mercos (`cliente_id`, `produto_id`, `pedido_id`) pertencem ao `CompanyToken` da nossa empresa, certo? Ou seja, consultando com nosso `ApplicationToken` novo, os IDs serão exatamente os mesmos que já existem hoje?"*

## 5. Fechamento
> *"Perfeito! Então o próximo passo é emitirmos o nosso `ApplicationToken` + acesso Sandbox para começarmos a validar as consultas de conferência e os Webhooks em paralelo, sem impactar a operação atual. Muito obrigado!"*
