# Handoff de Continuidade — CRM Clientes & Relacionamento Comercial v3.5 Executivo

## 1. Contexto e Objetivos Concluídos
Nesta sessão foi realizada a refatoração e redesenho executivo completo do módulo **CRM CLIENTES & RELACIONAMENTO COMERCIAL** (`portal/modules/marketing/clientes.php`):

1. **Reordenação Hierárquica e Visual Executiva Solicitada:**
   - **1. Topo Executivo:** Cabeçalho corporativo escuro com identificação 360°, status do mês corrente, botão de alertas com suporte a notificações nativas, botão amarelo de destaque **Treinamento & Manual**, botões de ação rápida (+ Novo Cliente, Agendar Visita, Exportar) e sincronizador.
   - **2. Filtros Avançados & Hierarquia Comercial (`#secaoFiltrosCRM`):** Posicionado imediatamente após o Topo. Card container branco com borda suave, seletores de Filial (SC/PR), Gestor Responsável, Representante Comercial e Status de Pedidos, barra de busca em tempo real com botões Limpar e Buscar, botões de períodos rápidos (Hoje, Este Mês, Mês Passado, 30 dias, Este Ano, Todos, Personalizado), dropdowns avançados de filtro e chips de filtros ativos.
   - **3. Cards de Indicadores (6 KPIs do Mês Corrente):** Posicionados logo abaixo dos Filtros, refletindo instantaneamente o escopo filtrado:
     - 1. Carteira (`stats.total`)
     - 2. Positivados Mês (`stats.fechados` + `% conversão`)
     - 3. Sem Compras Mês (`stats.sem_compras_mes` + badge 'Pendente positivação')
     - 4. Em Negociação (`stats.emNegociacao` + `formatarMoeda(stats.pipelineValue)`)
     - 5. Faturado Mês (`formatarMoeda(stats.total_faturado)`)
     - 6. Agenda Mês (`stats.concluidosMes` / `stats.totalCompromissosMes`)
   - **4. Barra Rápida de Navegação & Atalhos:** Alternância em pílula entre `Carteira de Clientes (Grade)` e `Agenda de Visitas & Compromissos`, com atalhos de `+ Novo Agendamento` e `🛒 Pedido Mercos`.
   - **5. Grade Analítica da Carteira de Clientes (`#secaoCarteiraClientes`):**
     - **Desktop:** Tabela completa com cabeçalho refinado, linhas detalhadas com badges (SC/PR, ERP ID, CRM, termômetro, faturamento, último contato, status de compra e ações diretas).
     - **Mobile:** Cards touch-friendly com WhatsApp direto, Agendamento, Mercos e Ficha.
     - **Paginação:** Rodapé com total de clientes, páginas e navegação suave.

2. **Diagnóstico e Correção Definitiva do Bug da Tabela Desktop:**
   - **Causa Raiz Identificada:** A classe utilitária global `.hidden { display: none !important; }` em `module-base.css` e `chat.css` possuía `!important`, sobrescrevendo o utilitário `.md:block` do Tailwind CSS em telas desktop e tornando a tabela invisível.
   - **Solução Cirúrgica:**
     - Adicionados fallbacks com `!important` para `.hidden.md\:block`, `.hidden.md\:flex`, `.hidden.lg\:block`, etc. em `portal/assets/css/module-base.css`.
     - Escopada a regra `.hidden` em `portal/assets/css/chat.css` exclusivamente para os elementos do chat.
     - Criadas classes dedicadas e blindadas `.crm-desktop-table` e `.crm-mobile-cards` no CSS e no módulo `clientes.php`, garantindo que no Desktop a tabela SEMPRE seja exibida e no Mobile os cards touch-friendly assumam o controle.

3. **Diagnóstico & Correção Definitiva do Loop de Carregamento:**
   - Resolvidos os métodos faltantes em `crmProfissional()`, removido o loop no bloco `catch` e eliminada a instanciação concorrente no `DOMContentLoaded`.

4. **Correção do Bug Crítico de Foreign Key no Agendamento:**
   - **Causa:** `mkt_compromissos_id_cliente_fkey` exige que `id_cliente` exista em `mkt_clientes(id)`. Ao selecionar um cliente oriundo do ERP (`idcliforemp = 16818`), ocorria violação de FK pois o cliente ainda não possuía registro primário em `mkt_clientes`.
   - **Solução Implementada:** Criado o método privado `resolverOuCriarClienteCrm(int $idClienteOuErp): int` em `v1/src/Controllers/MarketingController.php`. Ao criar compromisso, se o ID for do ERP e não constar no CRM, o backend sincroniza automaticamente os dados cadastrais (`fantasia`, `razao`, `fone`, `email`, `endereco`, `cidade`, `cgc_cpf`, `idvendedor`) do ERP para `mkt_clientes` e retorna o ID válido para gravação em `mkt_compromissos`.
   - **Correção Adicional:** Coluna de cidade corrigida de `cid.nome` para `cid.descricao as cidade_nome`.

5. **Sistema Universal de Notificações em Tempo Real (Web Notifications API + PWA / Service Worker):**
   - **Web Audio API Sintetizado:** Toca chime suave corporativo (880Hz / 1320Hz) sem depender de arquivos de áudio externos que quebravam anteriormente.
   - **Navegador Desktop (PC):** Notificação nativa no Windows Action Center e barra do sistema via Notification API.
   - **Celular & PWA Mobile:** Suporte completo com Service Worker (`portal/sw.js`) via `registration.showNotification` e manipulação do evento `notificationclick` para focar/abrir a aplicação.
   - **Agenda & Compromissos:** Monitoramento automático a cada 20 segundos; compromissos iminentes (nos próximos 15 minutos ou no horário) disparam notificação sonora e push nativo no aparelho.
   - **Chat Corporativo:** Criado endpoint `GET /v1/chat/ultimas-nao-lidas` e integrado monitoramento a cada 15 segundos; novas mensagens exibem o nome de quem enviou e o texto na barra do sistema operacional.
   - **Central de Notificações CRM:** Adicionado botão "Ativar Barra" no sino do cabeçalho e banner superior de ativação em 1 clique.
   - **Chamado Documentado:** Registrado chamado oficial nº 57 no PostgreSQL (`[CRM & Notificações] Correção de FK Violation no Agendamento e Sistema Universal de Notificações Web/Mobile (PC e Celular)`).

6. **Sincronização Dinâmica dos Cards de Indicadores com o Período:**
   - **Causa Raiz:** O backend (`getCRMDashboard`) calculava faturamento e positivação fixados em `date('Y-m-01')`, ignorando filtros de data. No frontend, a troca de período (`aplicarPeriodoRapido`, datas customizadas e seletor de representante) só recarregava a grade de clientes e não disparava `carregarDashboard()`.
   - **Solução Implementada:**
     - Backend (`MarketingController::getCRMDashboard`): Implementada resolução dinâmica de datas para `hoje`, `este_mes`, `mes_passado`, `30dias`, `este_ano`, `todos` e intervalo customizado (`data_inicio` / `data_fim`).
     - Frontend (`clientes.php`): `carregarDashboard()` agora envia os parâmetros de período e datas.
     - Eventos: Ao alterar período rápido, datas personalizadas, representante, status de pedido, botão "Buscar" ou "Limpar", `carregarDashboard()` é disparado em sincronia com a grade.
     - Rótulos Dinâmicos: Títulos dos cards e badges agora exibem dinamicamente o período correspondente ("Positivados Hoje", "Positivados Mês Passado", "Positivados Ano", etc.).
     - Implementado método geral `limparFiltros()` restaurando o mês corrente e redefinindo os cards.

7. **Sistema Global e Unificado de Notificações, Chamados e Badges:**
   - **Central Unificada no Backend (`/v1/notificacoes/resumo-geral`):** Criado endpoint em `MarketingController.php` que reúne em uma única requisição ultraleve: mensagens não lidas do chat corporativo, compromissos iminentes da agenda, novos chamados abertos/atualizados e avisos gerais do sistema.
   - **Onipresença em Todo o Portal:** `portal-notificacoes.js` é executado a partir de `header.php`, operando em qualquer tela ou módulo onde o usuário estiver navegando.
   - **Badging API da W3C (`navigator.setAppBadge`):** Projeta a contagem de notificações não lidas diretamente no ícone do aplicativo na **Barra de Tarefas do Windows** e no ícone do PWA na tela inicial do celular.
   - **Favicon Dinâmico com Canvas:** Renderiza uma bolinha vermelha estilizada com o número de pendências (`1`, `2`, `9+`) diretamente na aba do navegador.
   - **Alerta de Aba Minimizada:** Faz o título da aba piscar alternadamente (`🔔 Chamado #X: ...`) quando a página estiver em segundo plano ou minimizada, chamando a atenção do operador no Windows.
   - **Notificação Nativa Multiplataforma:** Dispara alertas com sintetizador Web Audio API e notificações no Windows Action Center e Android/iOS PWA.
8. **Diagnóstico Cirúrgico & Solução Completa do Sino, Favicon e Badges (Chamado #58):**
   - **Diagnóstico da Causa Raiz:**
     1. Em ambiente local (`isLocal = true`), `apiFetch` concatenava `window.API_URL + '/' + cleanEndpoint`, gerando barras duplas (`api_route=//notificacoes/resumo-geral`), retornando HTTP 404 Not Found no Slim e abortando silenciosamente as checagens em segundo plano.
     2. `portal-notificacoes.js` esperava um objeto Response com `.ok` e `.json()`, mas `apiFetch` já retornava o JSON parsed, gerando interrupção prematura.
     3. O Chrome/Edge em segundo plano mantinha o cache de `<link rel="icon">` quando apenas o atributo `href` era mutado. Foi implementada a remoção e reinjeção de nós SVG Data URI com `type="image/svg+xml"` para repaint imediato.
     4. `portal/index.php` continha um `notificacoesHandler()` desatualizado apontando para `/notificacoes` (que ignorava chamados, compromissos e chat).
   - **Implementações Realizadas:**
     1. Normalização rigorosa de rotas em `portal/assets/js/config.js` e método `requisitar` autônomo em `portal/assets/js/portal-notificacoes.js`.
     2. Endpoint `/v1/notificacoes/resumo-geral` enriquecido com a chave `lista_unificada`, ordenação cronológica decrescente, categorização visual (Agenda, Chat, Chamado, Sistema) e inclusão de compromissos de hoje e iminentes.
     3. Favicon dinâmico SVG com bolinha vermelha injetado no DOM garantindo repaint na aba do navegador.
     4. Suporte W3C Badging API (`navigator.setAppBadge`) atualizando a barra de tarefas do Windows e o ícone do PWA no celular.
     5. Reconstrução de `notificacoesHandler()` em `portal/index.php` e `notificacoesCRM()` em `portal/modules/marketing/clientes.php`, conectando aos eventos globais reativos com botões de Teste e Ativação.
     6. Chamado oficial #58 aberto e documentado com 4 operações técnicas em `chamado_operacao`.

8. **Desacoplamento do Chat, Persistência de Leitura e Módulo Premium Split-Screen com Status Online (Chamado #59):**
   - **Desacoplamento do Chat & Alertas:** Mensagens de bate-papo foram removidas da lista unificada do Sino do topo. O chat alimenta exclusivamente os badges dedicados (`#sidebarChatBadge` e `#chatBadge`) e as notificações sonoras e push nativas.
   - **Persistência de Leitura no Sino:** Sincronização inteligente de chamados recentes para a tabela `notificacoes` com flags `lida = FALSE` e `lida_em`. Quando o usuário clica em uma notificação ou clica no botão "✕" / "Limpar", a API marca `lida = TRUE` e o alerta **some imediatamente do sino e da contagem**.
   - **Isolamento do Sino do CRM:** `notificacoesCRM()` em `clientes.php` consome exclusivamente a tabela `crm_notificacoes` via `/crm/notificacoes`, sem misturar com chamados de TI nem chat.
   - **Módulo de Bate-Papo Corporativo Split-Screen Permanente:** `portal/modules/minhas-mensagens.php` construído com layout split-screen permanente (estilo WhatsApp Web / Slack). A lista de colaboradores permanece fixa à esquerda no desktop para alternância imediata de conversas com apenas 1 clique.
   - **Status Online em Tempo Real:** Tabela `usuario_online` com heartbeat automático integrado a cada 15 segundos. Identificação visual instantânea: bolinha verde esmeralda com anel pulsante (`Online agora`), âmbar (`Visto há instantes`) e cinza (`Offline`), além de horário de visualização e contagem de mensagens não lidas.
   - **Widget Flutuante Integrado:** `portal/assets/js/chat.js` e `portal/estrutura/footer.php` atualizados com status online em cada contato e botão de atalho para expandir em tela cheia split-screen.
   - **Chamado oficial #59** cadastrado com 4 etapas registradas em `chamado_operacao`.

9. **Estabilização de Balões (Flicker Zero), Confirmação de Leitura e Digitando... em Tempo Real (Chamado #60):**
   - **Limpeza de Dados:** Truncados e zerados todos os registros legados de `chat_mensagens` (32 mensagens), `notificacoes` (8 registros), `crm_notificacoes` (0 registros) e `mkt_compromissos` (9 registros), permitindo testes a partir de base limpa.
   - **Eliminação de Flicker / Piscadas nos Balões:** 
     - Corrigida a chave reativa do Alpine para usar `:key="m.id || m.idmensagem"` (evitando recriação de nós virtuais).
     - Implementada comparação de conteúdo antes da reatribuição de `mensagens` (só altera o DOM quando há mensagens novas ou transição de status).
     - Removidas animações `animate-ping` e `animate-pulse` contínuas, mantendo indicadores sólidos e de alta elegância.
   - **Confirmação de Entrega & Visualização (Estilo WhatsApp):**
     - 1 check cinza (`✓`): Mensagem entregue no servidor (`lida = FALSE`).
     - 2 checks azuis (`✓✓`): Mensagem lida e visualizada pelo destinatário (`lida = TRUE`).
   - **Detecção de Digitação em Tempo Real (`Fulano está digitando...`):**
     - Criada tabela `chat_digitando (idusuario_de, idusuario_para, atualizado_em)` no PostgreSQL.
     - Novos endpoints `/v1/chat/digitando` e `/v1/chat/parar-digitando`.
     - Indicador visual animado no cabeçalho e na lista lateral quando o outro usuário está digitando.
   - **Correção de Áudio no Gerenciador Universal:**
     - Implementado o sintetizador harmônico nativo via Web Audio API em `portal-notificacoes.js` (`tocarSom`), gerando um chime discreto de alta fidelidade sem dependência de MP3 externos.

10. **Módulo Chamados Service Desk Pro (`portal/modules/chamados.php`, `portal/assets/js/chamados.js`, `v1/src/Controllers/ChamadosController.php`, `v1/routes/api.php`):**
    - Interface avançada Service Desk Pro com quadro **Kanban Drag & Drop HTML5** (movimentação fluida entre colunas com atualização imediata de status).
    - Barra **Quick Ticket** para abertura rápida, filtros por **Solicitante**, **Responsável** e **Prioridade**, edição inline completa, comentários cronológicos e galeria de prints com colagem direta via **`Ctrl+V`**.
    - Painel de notificações com baixa automática ao abrir o chamado notificado (`lida = TRUE`) e botão **"Limpar Todas"** (além do fechamento individual `X`) via endpoint `POST /v1/chamados/limpar-notificacoes`.

11. **Renomeação do Módulo `pedido-amostra` (`portal/modules/pedido-amostra.php`, `portal/assets/js/pedido-amostra.js`, `sistema_modulos`, `sistema_manuais`):**
    - Módulo `pedido-amostra` renomeado para **Pedidos de Material de Apoio & Amostras** com descrição focada na geração de pedidos de material de apoio, brindes e amostras para apoiar o gestor comercial.

12. **Retorno de Item: Precificação pelo Último Custo Gerencial (`v1/src/Controllers/RetornoController.php`):**
    - Implementado `usaCustoGerencial()` para o cliente **`16595`** (Misturão / Matéria-Prima) e transações com `tipo_preco = 4` (`idtransacao = 21`).
    - Aplicado `ROUND(ef.custogerencial, 4)` nos campos unitários do `pedido_item` (`valor`, `valorunitarioimpressao`, `valorsugerido`, `valorprecovendadia`, `valorunit_comst`, `quantmoedaunitario`) e `TRUNC(ef.custogerencial, 2)` em `valorcustogerencial`.
    - Atualizado `atualizarTotaisPedido()` para também somar `valorbaseisentoicms` no cabeçalho do `pedido` e ajustado SQL manual para o pedido em aberto `160918`.

---

## 2. Arquivos para Deploy FTP
- `v1/src/Controllers/MarketingController.php`
- `v1/src/Controllers/ChatController.php`
- `v1/src/Controllers/ChamadosController.php`
- `v1/src/Controllers/RetornoController.php`
- `v1/src/Controllers/VendasCuboController.php`
- `v1/src/Controllers/VendasDashboardController.php`
- `v1/routes/api.php`
- `portal/sw.js`
- `portal/index.php`
- `portal/modules/chamados.php`
- `portal/assets/js/chamados.js`
- `portal/modules/pedido-amostra.php`
- `portal/assets/js/pedido-amostra.js`
- `portal/modules/minhas-mensagens.php`
- `portal/assets/js/chat.js`
- `portal/estrutura/footer.php`
- `portal/assets/js/config.js`
- `portal/assets/js/portal-notificacoes.js`
- `portal/estrutura/header.php`
- `portal/modules/marketing/clientes.php`
- `portal/assets/css/module-base.css`
- `portal/assets/css/chat.css`
- `portal/assets/css/style.css`
- `portal/modules/recompra-clientes.php`
- `portal/assets/js/recompra-clientes.js`
- `portal/assets/js/treinamentos.js`
