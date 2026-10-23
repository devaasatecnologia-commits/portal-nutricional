# Token de Continuidade — 2026-10-22

**Módulo:** Transferência de Compra (Matriz $\leftrightarrow$ Filial PR)  
**Status:** Implementado, validado e pronto para deploy FTP.  
**Autor:** Antigravity Tech Lead / Arquiteto Especialista

---

## 1. Resumo Técnico das Entregas

### 1.1 Regra de Negócio de Necessidade com Reserva 14523
- Implementado cálculo inteligente da quantidade necessária a transferir da Matriz para a Filial PR:
  $$\text{Estoque Livre PR} = \max(\text{Saldo Físico Filial 6} - \text{Qtd Pendente Pedidos Abertos (Status 1) Cliente 14523},\; 0)$$
  $$\text{Qtd a Solicitar} = \max(\text{Qtd Necessária no Embarque} - \text{Estoque Livre PR},\; 0)$$
- Proteção garantida do estoque reservado para a Filial PR (`idcliforemp = 14523`).
- Separação fiscal automática entre itens de **Remessa** (`idmarca = 60`, amostras/brindes/apoio a vendas) e itens **Normais** (produtos comerciais).

### 1.2 Backend (API Slim 4 / PSR-7)
- **Nova Rota:** `GET /v1/transf-compras/matriz-filial/previa/{embarque}`
  - Retorna cabeçalho do embarque, histórico de solicitações geradas no ERP, resumo executivo de KPIs e tabela completa de itens analisados com badges de proteção.
- **Aprimoramento de Rota:** `GET /v1/transf-compras/matriz-filial/pendentes`
  - Suporte a filtros de busca por número ou placa (`?q=...`) e flag para auditoria retroativa de embarques já faturados (`?faturados=1`).

### 1.3 Frontend (Portal Modular)
- **Tela `portal/modules/transferencia-compra.php`:**
  - Layout refinado com Tailwind CSS e Plus Jakarta Sans.
  - Hero com navegação ágil entre fluxos (Matriz $\rightarrow$ Filial vs Filial $\rightarrow$ Matriz).
  - 4 Cards de KPI no topo: Solicitações no filtro, Valor Guia ST apurado, Embarques Filial 6 aguardando e Indicador da Proteção Cliente 14523.
  - Grade de Embarques Pendentes da Filial PR com badges de status ("Aguardando" vs "Solicitado #ID") e botão direto para consulta de prévia.
  - **Modal Premium de Prévia (`modalPrevia`):**
    - Sub-dashboard interno com 4 KPIs: Total Itens, Qtd Total a Solicitar, Subtotais Normais e Remessas (Marca 60).
    - Tabs de filtro interno: Todos, Apenas a Solicitar, Normais, Remessas.
    - Campo de busca instantânea de itens.
    - Tabela rica e clara com indicadores visuais de necessidade e de reserva protegida.
    - Botão "Copiar Resumo" para facilitar o repasse para ordens internas ou ERP.
- **Javascript `portal/assets/js/transferencia-compra.js`:**
  - Gerenciamento de estado, debounce nas buscas, renderização dinâmica da tabela e modal com SweetAlert2 para memória de cálculo fiscal das guias ST.

---

### 1.4 Módulo Retorno de Carga / Revenda (`retorno.php`, `retorno.js`, `RetornoController.php`)
- **Correção Crítica de Quantidade no ERP (`quant_impressao`):**
  - Identificado que o ERP exibe e opera a quantidade comercial na coluna `quant_impressao` da tabela `pedido_item`.
  - O método `aplicarQuantidadePedidoItem` atualizava apenas `qt = :qt`, deixando `quant_impressao` com o valor antigo (gerando divergência entre o que o portal mostrava e o que o ERP exibia).
  - O método `inserirItemCompleto` chumbava `'quant_impressao' => 1`.
  - Corrigidos ambos os métodos para sincronizarem rigorosamente `quant_impressao = :qt`.
  - Sincronizado o item 2152 no pedido 152057 (cliente 16596 - REVENDA) para 2 UN reais ($296,16 e 20,20 kg).
- **Correção da Soma do Peso Total e Unitário:**
  - Corrigido o erro nas consultas `produtoExisteNoPedido` e `getEstoqueAtivo`, que faziam `(qt * qtpesoliquido) as peso_total`, multiplicando o peso pela quantidade ao quadrado.
  - Ajustado para utilizar o peso real cadastrado no item (`i.pesoliquido`) e o peso da linha (`pi.qtpesoliquido`).
- **Panorama Instantâneo ao Bipar:**
  - Ao bipar o código de barras ou informar a referência, o backend agora busca a situação do produto em **todos os 4 destinos** (Container, Matéria-Prima, Revenda e Trocas Fornecedor) em pedidos abertos (`status = 1`).
  - O frontend exibe no cabeçalho o peso líquido/bruto unitário e nos 4 cards de destino exibe badges em tempo real de saldo (quantidade em UN e peso em KG), além do número do pedido aberto.

---

## 2. Arquivos para Deploy FTP

Para deploy remoto (cPanel/Linux), subir os seguintes arquivos:

1. `v1/routes/api.php`
2. `v1/src/Controllers/TransferenciaCompraController.php`
3. `v1/src/Controllers/RetornoController.php`
4. `v1/src/Controllers/AmostraPedidoController.php`
5. `v1/src/Controllers/ModuleConfigController.php`
6. `v1/src/Controllers/VerbasFlexController.php`
7. `v1/src/Controllers/FlexTransferenciaController.php`
8. `v1/src/Controllers/EstoquePrevisaoController.php`
9. `portal/modules/transferencia-compra.php`
10. `portal/assets/js/transferencia-compra.js`
11. `portal/modules/retorno.php`
12. `portal/assets/js/retorno.js`
13. `portal/modules/pedido-amostra.php`
14. `portal/assets/js/pedido-amostra.js`
15. `portal/modules/admin/configuracoes-modulos.php`
16. `portal/modules/verbas-flex.php`
17. `portal/assets/js/verbas-flex.js`
18. `portal/modules/transferencia-flex.php`
19. `portal/assets/js/transferencia-flex.js`
20. `portal/modules/estoque-previsao.php`
21. `portal/assets/js/estoque-previsao.js`
22. `index_legado.php` (Tolerância inteligente de 5 min para o motor de crons unificado)
23. `docs/fix_verbas_flex_setembro_2026.sql` (Executar no banco de produção)
24. `docs/amostra_estoque_config_migration.sql` (Executar no banco de produção)
25. `docs/CONTINUATION_TOKEN.md`
26. `docs/tokens/CONTINUATION-2026-10-22.md`



---

### 1.5 Módulo Pedido de Amostra / Material de Apoio (`pedido-amostra.php`, `configuracoes-modulos.php`)
- **Regra de Estoque Matriz (1) vs Filial PR (6):**
  - Como o estoque físico de materiais e brindes fica centralizado na Matriz (1), as solicitações geradas para a Filial 6 (PR) agora consultam diretamente a Matriz aplicando uma retenção padrão configurável de -70% do estoque (ou seja, **30% liberado** para a filial PR).
  - Para a Matriz (1), o saldo consultado é integral (100% liberado).
- **Área Administrativa (`portal/modules/admin/configuracoes-modulos.php`):**
  - Criada nova seção dedicada: **Material de Apoio & Amostras · Estoque Filial PR (6)**.
  - **Configuração Geral:** Campo numérico para ajustar livremente o percentual padrão liberado para a Filial PR (ex: 30%, 50%, etc.), com cálculo visual automático do percentual retido na Matriz.
  - **Exceções & Regras por Item:** Sistema com busca interativa de produtos com fotos, permitindo definir um percentual customizado para qualquer item (inclusive 0% para bloquear um item específico para a Filial PR), com botão de pausar/ativar e exclusão.
- **Backend (`AmostraPedidoController.php` & `ModuleConfigController.php`):**
  - Novas tabelas criadas: `amostra_estoque_config` e `amostra_estoque_regra_item` (migração versionada em `docs/amostra_estoque_config_migration.sql`).
  - `itensDisponiveis()` calcula dinamicamente o estoque liberado para a filial de acordo com a regra geral ou override por item, retornando foto e metadados.
  - Validação rigorosa em `criar()` garantindo que o pedido não exceda o limite liberado para a filial.
  - Rota de detalhes de pedido (`GET /v1/amostras/pedidos/{id}`) para carregamento sob demanda com itens e fotos.
- **Frontend & Rastreamento Premium (`portal/modules/pedido-amostra.php` e `portal/assets/js/pedido-amostra.js`):**
  - **Design com Abas:** Aba 1 ("Nova Solicitação") e Aba 2 ("Histórico & Rastreamento").
  - **Catálogo com Fotos:** Grid moderno com foto real de cada produto via CDN (`getImageUrl`), tags de código/ref, estoque matriz, saldo liberado com badge de `%` e seletores de quantidade com limites validados em tempo real.
  - **Painel & Relatórios:** 4 KPI Cards (Total de Pedidos, Aguardando Importação ERP, Em Aberto, Faturados).
  - **Rastreabilidade Completa:**
    - Exibição destacada da **Origem**: Badge Roxa `PORTAL` vs Badge Azul `ERP`.
    - Exibição do **Solicitante**: Nome do operador/gestor que solicitou (inclusive capturando o usuário do portal via Palmtop).
    - Filtros por Origem, Status e busca textual livre.
  - **Modal de Detalhes:** Exibe dados completos do pedido, observação e grid de itens com fotos e quantidades.

---

### 1.6 Módulo Verbas Flex (`verbas-flex.php`, `verbas-flex.js`, `VerbasFlexController.php`)
- **Conciliação e Resolução de Divergência Mercos vs Portal (Setembro/2026):**
  - O Mercos registrava **R$ 69.000,00** em empréstimos de flex concedidos em setembro/2026, enquanto o portal exibia apenas **R$ 63.000,00**.
  - Causa identificada no log `log_debito_flex_gestor`: os registros `id = 3902` (11/09/2026) e `id = 3908` (18/09/2026) sofreram timeout na resposta da API Mercos e foram gravados com `valor_emprestimo = 0.00`, embora a API do Mercos tenha completado o lançamento do empréstimo de R$ 3.000,00 em cada um antes do corte.
  - Criado script de migração para conciliação em produção: `docs/fix_verbas_flex_setembro_2026.sql`.
- **Backend Refatorado (`v1/src/Controllers/VerbasFlexController.php`):**
  - Corrigida a ordenação de consumo no ranking: valores na tabela `log_flex_bonificacao` são gravados negativos; a ordenação anterior `SUM(lfb.valor_tirado) DESC` trazia quem menos consumiu. Corrigido para `ABS(SUM(lfb.valor_tirado)) DESC`.
  - Ranking completo: expandido para retornar todos os representantes que consumiram no período (17 representantes em setembro), com cálculo de percentual de participação (`participacao_pct`).
  - KPIs avançados no resumo: `ticket_medio`, `maior_debito` (`ABS(MIN(valor_tirado))`), `qtd_debitos`, `qtd_creditos`, `maior_credito` e `total_representantes`.
  - Agrupamento diário (`serie_diaria`) por dia no período para alimentar gráficos de tendência diária.
- **Frontend & Dashboard Premium (`portal/modules/verbas-flex.php`, `portal/assets/js/verbas-flex.js`):**
  - Integração com **Chart.js** (`chart.umd.min.js`) com dois gráficos dinâmicos:
    1. **Comparativo Semestral:** Gráfico de barras verticais com débitos vs créditos nos últimos 6 meses.
    2. **Tendência Diária:** Gráfico de área/linha suave com o volume diário de verba flex utilizada no período selecionado.
  - **Aba de Ranking de Consumo Dedicada:**
    - Filtros rápidos: Top 5, Top 10 e Ver Todos.
    - Busca em tempo real por nome ou código do representante.
    - Pódio visual com medalhas para 1º (Ouro), 2º (Prata) e 3º (Bronze).
    - Barra de progresso visual de participação percentual e valor formatado em R$.
    - Botão de exportação para Excel (CSV formatado com UTF-8 BOM).
  - **Exportação Excel em todas as abas:** Botão de download de planilha para Débitos (Notas) e Créditos (Empréstimos).
  - **Filtros Rápidos de Data:** Atalhos pré-definidos (Hoje, Últimos 7 dias, Este Mês, Mês Anterior, Últimos 30 dias, Este Ano).

---

### 1.7 Módulo Transferência de Flex (`transferencia-flex.php`, `transferencia-flex.js`, `FlexTransferenciaController.php`)
- **Regras de Negócio e Filtro de Representantes DE e PARA:**
  - **Ativos no ERP:** Consulta com `INNER JOIN cliforemp c ON c.idcliforemp = um.idcolaborador AND COALESCE(c.inativo, 'N') <> 'S'`, eliminando representantes desligados/inativos da lista.
  - **Vínculo e Saldo Ativo no MERCOS:** Cruzamento em tempo real com `MercosFlexService::saldos()`. Apenas representantes que possuem conta flex e status `excluido = false` na API Mercos são listados para transferência (eliminando erros 404 e tentativas de movimentação em cadastros sem vínculo).
  - Anexado o saldo atualizado diretamente em cada representante, permitindo exibir o saldo ao lado do nome em grupos organizados (Com saldo disponível vs Saldo zerado).
- **Auditoria e Observação Transparente no Mercos:**
  - Atendendo à solicitação do usuário, a observação enviada para o Mercos agora registra explicitamente a origem, destino, operador responsável e motivo:
    `Transf: [Nome DE] (#[ID]) -> [Nome PARA] (#[ID]) por [USUARIO] | Motivo: [Motivo]`
  - Em caso de falha no crédito com débito concluído, o estorno automático também registra observação detalhada de auditoria no Mercos.
- **Cockpit e Experiência do Gestor:**
  - **4 KPI Cards:** Total Transferido no Mês (R$ e operações), Saldo Total da Equipe, Meu Saldo Gestor e Última Transferência realizada.
  - **3 Abas Modernas:**
    1. *Realizar Transferência:* Grid em 3 colunas (Origem DE, Coluna Central de Valor/Atalhos/Motivo com live preview da observação Mercos, e Destino PARA com saldo projetado e badge de incremento).
    2. *Saldos da Equipe:* Tabela panorâmica com todos os representantes da filial, saldo em R$, indicador de status e botões de ação rápida ("Receber" ou "Ceder").
    3. *Histórico & Auditoria:* Tabela rica com busca textual livre, filtros de data rápidos (Hoje, 7 dias, Este Mês), status, e exportação Excel (CSV UTF-8 BOM).
  - Modal SweetAlert2 de dupla confirmação antes do disparo com resumo dos impactos de saldos.

---

### 1.8 Módulo Estoque com Previsão de Chegada (`estoque-previsao.php`, `estoque-previsao.js`, `EstoquePrevisaoController.php`)
- **Unificação de Estoque Matriz 1 (SC) + Filial 6 (PR):**
  - Como a Filial PR (6) mantém estoque reduzido/físico dependente de transferências da Matriz (1), o módulo agora oferece uma visão unificada consolidada (`idfilial = 0`), além de permitir filtrar especificamente por Matriz 1 ou Filial PR 6.
  - Na listagem de produtos e nos detalhes, são discriminados o estoque da Matriz 1, o estoque da Filial PR 6 e o estoque total físico consolidado.
- **Previsão de Chegada Integrada (Compras OCs Matriz + Filial PR):**
  - Atendendo à regra solicitada onde itens recebidos na Filial PR devem ser visíveis para a Matriz e vice-versa, as ordens de compra (`OC` + `OC_ITEM`) com saldo pendente agora consideram ambas as filiais (`oc.idfilial IN (1, 6)`).
  - No modal de detalhes do produto, cada ordem de compra exibe o número da OC, destino (`Matriz 1` vs `Filial 6 PR`), fornecedor, quantidade pendente, data prevista e contagem regressiva de dias para chegada com badges visuais.
- **Cálculo Real de Carteira Reservada:**
  - Substituído o valor estático zerado por cálculo real de pedidos em carteira em aberto/parciais (`pedido.status IN (1, 4)` e `COALESCE(pi.qtsaldo, pi.qt)`).
  - Cálculo de **Estoque Livre Disponível** (Estoque Físico - Carteira Reservada) e **Estoque Futuro Projetado** (Estoque Livre + Previsão de Chegada OCs).
- **Frontend & Layout Especializado:**
  - Hero banner moderno com gradiente esmeralda, seletor de unidade com indicador de visão unificada e relógio em tempo real.
  - **6 KPI Cards:** Estoque Físico Total, Carteira Reservada, Livre Disponível, Previsão Compras, Estoque Futuro e Itens Críticos/Zerados.
  - Tabela responsiva com fotos reais dos produtos via CDN, marcas, estoque por filial, carteira, livre, previsão e futuro com status em tempo real.
  - Modal de Raio-X ampliado do produto com foto via CDN, endereçamento de estoque, comparativo de saldos Matriz vs Filial PR, tabela de OCs e gráfico Chart.js de movimentação dos últimos 30 dias.
  - Exportação para Excel (CSV formatado com UTF-8 BOM e separador `;`).

---

### 1.9 Módulo Análise de Carteira & Gestão de Eventos (`analise-carteira.php`, `analise-carteira.js`, `AnaliseCarteiraController.php`, `api.php`)
- **Organização e Redesenho dos KPI Cards:**
  - Reorganizados os 5 KPI Cards em hierarquia executiva moderna:
    1. *Total Vencido (Inadimplência)* com % do IAG em relação à carteira total.
    2. *IAG (Índice de Atraso Geral)* com indicador dinâmico de performance (Crítico, Atenção ou Saudável).
    3. *Títulos Vencidos & Clientes Afetados* destacando o número de documentos e clientes inadimplentes.
    4. *Carteira À Vencer (Saudável)* com sub-indicador da janela imediata de 30 dias (hoje-3 a hoje+27).
    5. *Carteira Total da Unidade* com soma consolidada, total geral de títulos e clientes atendidos.
- **Detalhamento do Cliente & Ficha Financeira:**
  - Criado endpoint `GET /v1/analise-carteira/cliente/{id}/detalhes`:
    - Retorna dados cadastrais completos (`cliforemp` + `cidade`), filial e contatos (telefone/WhatsApp).
    - Retorna somatórios calculados em tempo real de Total Vencido, À Vencer e Carteira Geral.
    - Retorna histórico cronológico dos últimos 30 eventos de cobrança/contato (`contato_evento`).
    - Retorna todos os títulos em aberto (`receber`) com saldo pendente (`valorsaldo > 0`).
    - Retorna tipos de evento ativos e permitidos (`tipo_evento_contato WHERE idtipoevento NOT IN (2, 8) AND inativo = 'N'`).
- **Resolução de Roteamento da API & Escopo de Gestores:**
  - **Correção da Carga de Gestores:** Ajustada a resolução dinâmica de URL no JavaScript (`resolverUrl()`) com suporte a `window.API_URL` (tanto `/index.php?api_route=` local quanto `/v1/` de produção), eliminando erro de rota e travamento no select.
  - **Escopo no Backend (`AnaliseCarteiraController.php`):** Removido bloqueio indevido que exigia `is_admin = true` ou IDs manuais em `dash_gestores`. Usuários sem restrição específica de gestores no ERP agora listam corretamente os gestores disponíveis.
  - **Ajuste Visual do Topo (Hero Header):** Substituído o fundo vermelho intenso por gradiente executivo escuro em tons de ardósia/indigo (`#0f172a` a `#334155`), preservando a seriedade do dashboard corporativo e reservando o vermelho apenas para alertas e indicadores de inadimplência.
- **Criação de Eventos de Cobrança / Contato:**
  - Criado endpoint `POST /v1/analise-carteira/cliente/{id}/eventos`:
    - Inserção em `contato_evento` utilizando a sequence legada do ERP `gi_contato_evento`.
    - Suporte a múltiplos títulos vinculados por evento (ou evento avulso com `local = '.'`).
    - Validação de regras de negócio legadas: tipos `[9, 8, 2, 10, 4]` (SAC, Sugestão, Pós-Venda, etc.) permitem salvamento sem vincular títulos. Tipos de cobrança exigem a seleção de ao menos um título em aberto.
    - Registro de usuário criador do portal (`request->user`), filial, data/hora atual e cor do evento.
- **Frontend & Modal Interativo SweetAlert2:**
  - Coluna de Cliente na tabela de títulos com link clicável e ícone de ficha financeira.
  - Coluna de "Ações" com botão destacado `+ Evento`.
  - Modal dinâmico em 2 abas:
    1. *Histórico de Eventos:* Timeline com badges na cor oficial do evento (`cor_evento`), data/hora, operador responsável, documento vinculado e observação completa.
    2. *Novo Evento / Cobrança:* Dropdown com tipos de evento e aviso dinâmico da regra de obrigatoriedade de títulos, listagem interativa de títulos em aberto com checkbox "Marcar Todos", campo de motivo/observação e gravação com feedback em tempo real.
  - Recarregamento automático do modal e da grade de títulos após gravação com toast de confirmação.

---

### 1.10 Geração de Solicitação de Transferência no ERP (`TransferenciaCompraController.php`, `transferencia-compra.php`, `transferencia-compra.js`)
- **Implementação dos Botões e Fluxos de Inserção no ERP:**
  - **Matriz $\rightarrow$ Filial PR (`?tipo=matriz-filial`):**
    - Adicionado botão **"Gerar no ERP"** diretamente no card do embarque pendente e botão de ação primária destacado em verde esmeralda no rodapé da **Modal de Prévia** (`#modalPrevia`).
    - Validação atômica de duplicidade no ERP (evita solicitações duplicadas para o mesmo embarque com verificação por regex em `solicitacao_compra.observacao`).
    - **Separação Obrigatória do Processo Legado (Duas Solicitações quando há Remessa):**
      - Quando o embarque possui itens de Remessa/Amostras e Brindes (`idmarca IN (60)`):
        1. Cria a 1ª Solicitação para **Remessa**: observação `'Remessa: ' || idembarque || ' - Placa: ' || placa`, `situacao = 2`, `tipo = 2`, `idfilial = 1`, `idcliforemp = 14523`, gravando em `solicitacao_compra_item` apenas os itens de marca 60.
        2. Cria a 2ª Solicitação para **Embarque Normal**: observação `'Embarque: ' || idembarque || ' - Placa: ' || placa`, `situacao = 2`, `tipo = 2`, `idfilial = 1`, `idcliforemp = 14523`, gravando em `solicitacao_compra_item` os itens normais (`idmarca NOT IN (60)`).
      - Quando o embarque NÃO possui itens de remessa:
        - Cria solicitação única de Embarque: observação `'Embarque: ' || idembarque || ' - Placa: ' || placa`, `situacao = 2`, gravando todos os itens normais.
    - Modal de feedback no frontend adaptado para exibir os dois números de solicitação gerados quando houver remessa.
  - **Filial PR $\rightarrow$ Matriz (`?tipo=filial-matriz`):**
    - Adicionada seção de **"Ordens de Compra Disponíveis (Filial PR $\rightarrow$ Matriz)"** com listagem dinâmica via `GET /v1/transf-compras/filial-matriz/ordens-pendentes`.
    - Cada ordem traz o botão **"Gerar no ERP"** com confirmação prévia (SweetAlert2).
    - Endpoint `POST /v1/transf-compras/filial-matriz/gerar`:
      - Cabeçalho em `solicitacao_compra`: `idfilial = 6`, `tipo = 2`, `situacao = 2`, `idcliforemp = 1` (Nutricional Matriz), observação `Transferencia Ordem de Compra: {idoc}`.
      - Itens em `solicitacao_compra_item`: `idfilial = 6`, `iddeposito = 1`, `idfilial_solicitar = 6`.

---

## 2. Arquivos para Deploy FTP (Consolidado do Dia)
1. `v1/routes/api.php`
2. `v1/src/Controllers/TransferenciaCompraController.php`
3. `portal/modules/transferencia-compra.php`
4. `portal/assets/js/transferencia-compra.js`
5. `v1/src/Controllers/AnaliseCarteiraController.php`
6. `portal/modules/analise-carteira.php`
7. `portal/assets/js/analise-carteira.js`
8. `v1/src/Controllers/EstoquePrevisaoController.php`
9. `portal/modules/estoque-previsao.php`
10. `portal/assets/js/estoque-previsao.js`
11. `v1/src/Controllers/FlexTransferenciaController.php`
12. `portal/modules/transferencia-flex.php`
13. `portal/assets/js/transferencia-flex.js`
14. `v1/src/Controllers/VerbasFlexController.php`
15. `portal/modules/verbas-flex.php`
16. `portal/assets/js/verbas-flex.js`
17. `v1/src/Controllers/AmostraPedidoController.php`
18. `docs/fix_verbas_flex_setembro_2026.sql`
19. `docs/amostra_estoque_config_migration.sql`

---

## 3. Planejamento da Próxima Sessão (Amanhã)
- **Módulo Alvo:** CRM Clientes ([`portal/modules/marketing/clientes.php`](../../portal/modules/marketing/clientes.php)) e Módulo Administrativo de Permissões ([`portal/modules/admin/`](../../portal/modules/admin/)).
- **Objetivos Definidos:**
  1. **Experiência do Usuário & Layout:** Modernizar a interface do CRM para o padrão premium adotado nos módulos recentes (Tailwind, componentes limpos, tipografia consistente, cards rápidos e responsividade).
  2. **Hierarquia e Escopo por Filial / Gestor:**
     - Liberar filtro inteligente por Filial.
     - Permitir que cada Gestor acesse estritamente a carteira de clientes sob sua responsabilidade (sua filial e/ou seus representantes vinculados), além do acesso amplo aos administradores.
  3. **Configuração de Direitos e Permissões no Admin:**
     - Estruturar na área administrativa (`modules/admin/`) os controles de concessão de direitos para visualização e filtragem por filial/gestor no CRM.


