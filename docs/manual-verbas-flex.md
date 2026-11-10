# Manual do Especialista & Treinamento: Gestão de Verbas Flex, Auditoria & Espelho Mercos v3.5

**Módulo:** Gestão de Verbas Flex, Bonificações e Saldos Mercos  
**Versão:** 3.5 — Novembro/2026  
**Público-Alvo:** Gestores de Filial, Supervisores Comerciais, Controladoria e Administradores  

---

## 1. Visão Geral e Propósito do Módulo

O módulo de **Gestão de Verbas Flex** (`portal/modules/verbas-flex.php`) é a central de governança financeira e auditoria de saldos comerciais da Nutricional, integrado diretamente à API Mercos de Força de Vendas e ao ERP PostgreSQL.

Ele unifica em um único painel:
1. **Monitoramento de Bonificações Faturadas:** Rastreamento nota a nota das bonificações concedidas a clientes, diferenciando débitos diretos da conta do representante daqueles cobertos pelo gestor da filial.
2. **Auditoria & Espelho Mercos:** Extrato completo e em tempo real de todas as movimentações de verba, com saldo vivo consultado na API Mercos e badges explicativos.
3. **Aportes Automáticos de Flex Mínimo:** Garantia de que os gestores de filial mantenham sempre um saldo operacional de segurança para que o faturamento de bonificações nunca seja travado.
4. **Governança de Transferências Comerciais:** Registro seguro de cessões e transferências de saldo entre representantes e supervisores com operador identificado.

---

## 2. A Nova Aba: Auditoria & Espelho Mercos

A aba **Histórico & Auditoria Mercos** foi projetada para que os gestores de filial acompanhem todas as entradas e saídas de verba flex sem a necessidade de acessar o painel administrativo da Mercos.

### 2.1 Card Hero Dinâmico de Saldo no Mercos (Tempo Real)
No topo da aba de auditoria, um card executivo exibe o saldo ao vivo obtido diretamente da API Mercos:
* **Quando "Todos os Colaboradores" estiver selecionado:** O card exibe o **Saldo Total da Equipe (Mercos)**, somando ao vivo o saldo de todos os representantes ativos da filial.
* **Quando um Colaborador Específico for selecionado:** O card exibe o **Saldo Atual no Mercos (Tempo Real)** daquele profissional, com o nome e o código ERP.
* **Botão de Atualização Ao Vivo (`Atualizar Saldo`):** Permite recarregar o saldo instantaneamente sem recarregar a página inteira do portal.

### 2.2 Filtro Inteligente de Representantes Ativos
* O seletor de colaboradores filtra e exibe **apenas os representantes que possuem conta Flex ativa na Mercos** (`excluido != true`), eliminando contas inativas, desligadas ou de teste da visualização.

### 2.3 Chips Rápidos de Filtragem em 1 Clique
No cabeçalho do extrato, botões de atalho rápido permitem isolar instantaneamente o tipo de movimentação desejado:
* `[ Todas as Operações ]` — Visão cronológica completa de entradas e saídas.
* `[ Apenas Débitos (-) ]` — Foco em saídas de saldo (bonificações faturadas e transferências enviadas).
* `[ Apenas Créditos (+) ]` — Foco em entradas de saldo (aportes de flex mínimo e transferências recebidas).
* `[ Bonificações ]` — Descontos vinculados a notas fiscais de bonificação faturadas.
* `[ Transferências ]` — Movimentações operacionais realizadas entre contas flex.
* `[ Flex Mínimo ]` — Aportes automáticos de recomposição de saldo dos gestores.

### 2.4 Exportação para Excel
O botão **Exportar Extrato Excel** gera uma planilha formatada contendo todas as colunas de auditoria: *Data/Hora, Origem, Operador, Colaborador, Tipo, Valor, Saldo Antes/Depois, Gestor/Cobertura e Documento/Motivo*.

---

## 3. Entendendo as Bonificações Faturadas: Débito Direto vs Cobertura do Gestor

Uma das regras de negócio mais importantes do ecossistema é o fluxo de débito de notas de bonificação:

### 3.1 Débito Direto (`Saldo Próprio`)
* Ocorre quando o representante comercial possui **saldo flex suficiente** em sua conta Mercos para quitar integralmente o valor da bonificação concedida na nota.
* **Comportamento no Extrato:**
  * O lançamento é registrado com o valor real da bonificação (ex: `- R$ 247,90`).
  * O badge exibido é verde: **`Saldo Próprio (Débito Direto)`**.
  * O valor retirado do gestor é `R$ 0,00` porque a operação utilizou 100% de recursos próprios do colaborador.

### 3.2 Cobertura pelo Gestor (`Via Gestor`)
* Ocorre quando o representante possui **saldo insuficiente** para absorver a bonificação faturada.
* **Comportamento no Extrato:**
  * O motor do portal verifica o saldo do gestor da filial e realiza automaticamente uma **transferência de cobertura** do gestor para o representante, efetuando em seguida a liquidação da bonificação.
  * O badge exibido é âmbar/roxo com o nome do gestor responsável (ex: **`Gestor TIAGO (MOV)`**).
  * O gestor e a diretoria conseguem rastrear exatamente quais representantes estão demandando suporte financeiro da filial.

---

## 4. Regras e Travas de Flex Mínimo dos Gestores

Para assegurar que o faturamento de pedidos com bonificação nunca seja interrompido por falta de saldo, o sistema conta com a rotina automática de **Flex Mínimo**:

### 4.1 Parâmetros por Filial
* **Filial 1 (Matriz SC):**
  * **Gestor:** Tiago MOV (ID ERP 12335)
  * **Piso Mínimo:** R$ 3.000,00
  * **Valor do Aporte Automático:** R$ 3.000,00
* **Filial 6 (PR):**
  * **Gestor:** Tales (ID ERP 15816)
  * **Piso Mínimo:** R$ 1.000,00
  * **Valor do Aporte Automático:** R$ 1.000,00

### 4.2 Eliminação de Concorrência Externa
* O agendamento legado que rodava a cada 4 minutos no ERP antigo foi **definitivamente desativado**.
* Toda a verificação e injeção de saldo agora é coordenada de forma segura e atômica pelas rotinas oficiais do Portal, evitando aportes duplicados ou desalinhamentos de saldo na Mercos.

---

## 5. Dúvidas Frequentes & Perguntas Operacionais (FAQ)

### P1: Por que alguns lançamentos de bonificação apareciam antigamente com "- R$ 0,00"?
**Resposta:** Isso ocorria devido a uma leitura da coluna técnica `valor_tirado` (que registra quanto foi retirado do gestor). Quando o representante tinha saldo e pagava com recursos próprios, o valor tirado do gestor era legitimamente `0,00`. Na versão 3.5, a auditoria exibe com precisão o valor real da nota faturada (`b.valor`) acompanhado do badge `Saldo Próprio`.

### P2: O portal consegue trazer o extrato de flex consumido ou gerado diretamente em cada pedido de venda da Mercos?
**Resposta:** O serviço intermediário (bridge Linux) nas portas 8081 e 8086 expõe exclusivamente o endpoint `/saldo_flex` (saldo atual consolidado). A abertura de verba pedido a pedido fica na nuvem fechada da Mercos e o robô de importação de pedidos do ERP grava esses campos com `0.00`. Por essa razão, o portal foca com 100% de precisão nos eventos que efetivamente movimentam saldo (bonificações, aportes, transferências e ajustes manuais). O backend do portal já foi preparado para consumir novos endpoints de pedidos no momento em que a equipe de infraestrutura os disponibilizar.

### P3: Quem pode visualizar a aba de Auditoria e realizar movimentações?
**Resposta:** O módulo respeita rigorosamente a hierarquia de usuários do ERP:
* **Administradores:** Enxergam todas as filiais e todos os colaboradores.
* **Gestores de Filial:** Enxergam apenas as filiais vinculadas em seu cadastro (`dash_filiais`) e os representantes sob sua supervisão (`dash_gestores`).
* **Operadores/Representantes:** Têm acesso restrito ao próprio saldo e movimentações permitidas.

---

## 6. Procedimento de Deploy e Arquivos Relacionados

1. `portal/modules/verbas-flex.php` — Interface visual com aba de auditoria, chips e card live.
2. `portal/assets/js/verbas-flex.js` — Lógica reativa Alpine.js, consulta Mercos e exportação Excel.
3. `v1/src/Controllers/VerbasFlexController.php` — Endpoints de auditoria, colaboradores ativos e saldos.
4. `v1/src/Services/MercosFlexService.php` — Conexão resiliente à API Mercos e métodos futuros.
5. `v1/src/Controllers/CronRotinasController.php` — Processamento seguro de bonificações e aportes.
6. `docs/manual-verbas-flex.md` — Documentação técnica oficial e treinamento corporativo.
