# MANUAL DO USUÁRIO & GUIA DE TREINAMENTO: MOTOR DE CRONS & LISTAS DE DESTINATÁRIOS

**Versão:** 4.0 (Novembro/2026)  
**Público-Alvo:** Gestores, TI, Controladoria, Faturamento e Diretoria  
**Módulos Relacionados:** Admin > Motor de Crons (`portal/modules/admin/crons.php`) e Admin > Configurações de Módulos (`portal/modules/admin/configuracoes-modulos.php`)  
**Chamado Relacionado:** Chamado #33 [Projeto Migração e Aposentadoria do Portal DOX]

---

## 1. Visão Geral do Sistema

O **Motor de Crons do Portal Nutricional** é o núcleo de orquestração de rotinas em segundo plano da empresa, responsável por processar relatórios corporativos, sincronizações de estoque e custo, recálculo de crédito e integrações externas (Mercos, GoFind, ERP EMA).

Recentemente, as rotinas legadas do sistema **DOX (Ema Studio)** foram absorvidas nativamente pelo Portal:
1. **DOX #6 (Limite de Crédito Mensal):** Recálculo de limite de crédito dos clientes todo dia 1º com envio de relatório executivo por e-mail e atualização de `cliente.valorlimitecredito`.
2. **DOX #5 (Altera Custo Gerencial):** Sincronização inteligente a cada 5 minutos do custo gerencial das filiais 1 (SC) e 6 (PR), com verificação prévia ultrarrápida (<40ms), garantindo que os custos gerenciais reflitam com precisão as últimas compras sem sobrecarregar o banco.

---

## 2. Gestão de Destinatários & Listas de E-mails

A tela **Admin > Configurações de Módulos > Aba Destinatários & Listas** permite ao gestor definir quem recebe as notificações automáticas de cada rotina sem necessidade de alterações no código-fonte.

### 2.1. Funcionalidades da Interface
- **Barra de Indicadores Estatísticos:** Apresenta em tempo real o Total de Listas, Destinatários Únicos Ativos, Listas de Processamento/Crons e Listas de Gestão/Comercial.
- **Filtros por Categoria:** Navegação rápida com contadores dinâmicos:
  - **Todas:** Visualização geral de todas as listas cadastradas.
  - **Processamento / Crons:** Altera Custo Gerencial, Limite de Crédito, Clientes Isentos.
  - **Representantes & Gestores:** Relatórios diários de alterações, fechamento mensal e inadimplência de gestores.
  - **Comercial & Pedidos:** Pedidos aguardando aprovação, migração de carteira.
  - **Fiscal & XML:** Divergências de XML de notas fiscais.
- **Busca em Tempo Real:** Localize instantaneamente qualquer rotina digitando o nome do cron, a chave ou um e-mail específico.
- **Chips Visuais de E-mails:** Cada endereço cadastrado é exibido como uma tag com pré-visualização.
- **Botão "Copiar E-mails" em 1 Clique:** Copia todos os destinatários daquela lista diretamente para a área de transferência.

### 2.2. Como Alterar os Destinatários de uma Lista
1. Acesse **Admin > Configurações de Módulos** e selecione a aba **Destinatários & Listas**.
2. Localize a lista desejada (ex: *Altera Custo Gerencial (DOX #5)* ou *Limite de Crédito Mensal*).
3. No campo de texto **E-mails (separados por vírgula)**, adicione ou remova os endereços desejados.
4. Clique no botão **Salvar Lista** (ícone do disquete).
5. A alteração entra em vigor imediatamente para as próximas execuções do Cron.

### 2.3. Regra Especial: Clientes Isentos ST
Para a rotina de Clientes Isentos ST (10:00), a distribuição é separada em 3 blocos:
- **Visão Geral (Diretoria):** Recebe o consolidado geral com todos os clientes isentos de ambas as filiais.
- **Filial 1 · Matriz SC:** Recebe apenas os clientes pertencentes à filial de Santa Catarina.
- **Filial 6 · PR:** Recebe apenas os clientes pertencentes à filial do Paraná.

---

## 3. Rotina Absorvida: Altera Custo Gerencial (DOX #5)

### 3.1. Objetivo da Rotina
Assegurar que o `custogerencial` na tabela `estoque_filial` reflita com exatidão o custo de compra mais recente registrado nas entradas do ERP EMA, mantendo o espelhamento perfeito entre a Matriz (Filial 1) e a Filial 6 (PR), além de registrar auditoria completa.

### 3.2. Mecanismo de Execução Inteligente (4 Eventos)
1. **Verificação Preliminar (Evento 0 - Duração ~30ms):**
   - O cron consulta se houve qualquer inserção em `item_historico` nos últimos 7 minutos com alteração de custo compra (`CUSTOCOMPRANOVO > 0 AND CUSTOCOMPRAANTERIOR <> CUSTOCOMPRANOVO`).
   - Se **NÃO** houver alterações, a rotina encerra imediatamente em 30 milissegundos sem executar updates pesados.
2. **Atualização do Estoque (Evento 1):**
   - Atualiza `custogerencial`, `datahoraultimaatualizacao` e `custogerencialanterior` em `estoque_filial` com `identificacaolocal = 'AUTOMATICO'`, priorizando custo da Matriz.
3. **Histórico do Item (Evento 2):**
   - Grava novo registro em `item_historico` com `usuario = 'AUTOMATICO'` e `origem = 'C.G. Auto.'`.
4. **Log de Auditoria Técnica (Evento 3):**
   - Registra na tabela `item_altera_custogerencial` com sequence oficial `nextval('gi_item_altera_custogerencial')`.
5. **Alinhamento Filial 6 (Evento 4):**
   - Sincroniza o custo gerencial da Filial 6 (PR) com o valor da Filial 1 (SC).

---

## 4. Painel de Auditoria & Histórico Completo de Crons

Na tela **Admin > Motor de Crons (`portal/modules/admin/crons.php`)**, foi introduzida a seção **Histórico Completo & Auditoria de Execuções**.

### 4.1. Como Filtrar e Auditar um Job
1. **Seletor de Job / Rotina:** Selecione um job específico (ex: *Altera Custo Gerencial (DOX #5)*) para visualizar exclusivamente as execuções daquela rotina ao longo do tempo.
2. **Seletor de Status:** Filtre por *Sucesso*, *Falha* ou *Em Execução*.
3. **Seletor de Registros:** Escolha entre os 25, 50, 100 ou 200 registros mais recentes.
4. **Busca Rápida:** Filtre por nome do usuário que disparou (ex: `SISTEMA`, `MARCON`) ou IP de origem.

### 4.2. Visualização dos Detalhes da Auditoria
Ao clicar no botão azul **Auditoria** de qualquer linha do histórico, abre-se a janela técnica detalhada:
- **Resumo Executivo:** Status, Duração exata (em segundos ou ms), Usuário disparador e IP.
- **Relatório Formatado:** Visualização do HTML gerado e enviado por e-mail (se aplicável).
- **Payload JSON:** Exibição estruturada com realce de sintaxe do retorno completo da rotina.
- **Botão "Copiar JSON":** Copia todo o payload para envio ao suporte técnico ou análise de TI.

---

## 5. Acesso Unificado no Portal

No Portal Nutricional (dashboard principal de usuários fora do Admin), o módulo **Motores Cron** agora:
- Possui o badge visual **ADMIN** destacado no card.
- Redireciona de forma unificada e segura diretamente para `portal/modules/admin/crons.php`.
- O endpoint legado `portal/modules/crons.php` executa redirecionamento HTTP 302 com fallback automático em JavaScript, impedindo telas em branco ou erros de navegação.

---

## 6. Procedimentos de Troubleshooting (Resolução de Dúvidas)

| Sintoma | Causa Mais Provável | Ação Recomendada |
| :--- | :--- | :--- |
| Job executou como "Falha" | Erro de conexão com banco ou parâmetro inválido | Clicar em **Auditoria** na linha da execução e inspecionar a mensagem de erro no payload JSON. |
| E-mails não estão chegando | E-mail digitado incorretamente ou lista vazia | Acessar **Configurações de Módulos > Destinatários & Listas**, verificar a sintaxe dos e-mails e salvar. |
| Custo Gerencial rodou em 20ms e não alterou nada | Comportamento normal quando não houve compra recente | A rotina monitora alterações nos últimos 7 minutos. Se ninguém digitou NF de compra no ERP, ela encerra rapidamente para economizar recursos. |
| Testar rotina sem alterar dados | Necessidade de verificar conexões e destinatários | Usar o botão **Testar (Dry-Run)** no topo do painel. Ele simula a execução sem gravar alterações. |
