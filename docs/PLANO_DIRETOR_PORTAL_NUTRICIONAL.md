# Plano Diretor de Modernização e Estabilização — Portal Nutricional

**Tech Lead & Arquitetura de Software**  
**Versão:** 1.0 — Novembro/2026  
**Escopo:** 40 Módulos Ativos em 9 Setores Estratégicos  

---

## 1. Diagnóstico do Especialista: O Cenário Atual

O Portal Nutricional atingiu uma robustez impressionante, com **40 módulos ativos operando em 9 setores da empresa** (Comercial, Financeiro, Vendas, Logística, Frota, Faturamento, Marketing, RH e TI). 

Entretanto, o crescimento acelerado combinado com emergências diárias de produção gera três desafios clássicos de grandes sistemas:
1. **Dispersão de Foco:** Urgências operacionais (ex: ajuste de saldo Mercos, nota travada, bloqueio de requisições) interrompem o desenvolvimento planejado, fazendo anotações em papel se perderem.
2. **Heterogeneidade de Telas:** Alguns módulos já estão na versão moderna v3.5 (com Alpine.js, Tailwind, Treinamento no BD, cards KPIs e rate limit por rota), enquanto outros ainda utilizam componentes legados ou falta padronização visual.
3. **Consumo de Contexto (Tokens):** Tentar revisar tudo de uma vez consome o limite diário e gera análises superficiais. O segredo do sucesso é a **auditoria cirúrgica degradativa (faseada)**.

---

## 2. A Estratégia Recomendada: Matriz por Ondas de Maturidade

Em vez de auditar por ordem aleatória, dividimos os 40 módulos em **4 Ondas Estratégicas**, organizadas por impacto financeiro e criticidade de produção:

1. **Onda 1: Núcleo Financeiro, Verbas & Análise de Vendas (Prioridade Máxima)**
2. **Onda 2: Logística, WMS & Estoque (Operação Física)**
3. **Onda 3: Frota, Expedição & Faturamento (Rua e Documentos)**
4. **Onda 4: Marketing, RH & Governança (Pessoas & TI)**

---

## 3. Detalhamento das 4 Ondas de Execução

### Onda 1: Núcleo Financeiro, Verbas & Análise de Vendas (Prioridade Máxima)
> **Objetivo:** Blindar o caixa, a remuneração de representantes, créditos e metas corporativas.

| Setor | Módulo | Slug | Status Atual | Ação Principal |
| :--- | :--- | :--- | :--- | :--- |
| **Comercial** | Verbas Flex | `verbas-flex` | ✅ v3.5 Estabilizado | Deploy FTP e validação final |
| **Comercial** | Transferência de Flex | `transferencia-flex` | 🟡 v3.0 Ativo | Padronizar layout com a nova auditoria |
| **Comercial** | Movimentação de Flex | `movimentacao-flex` | 🟡 v2.0 Ativo | Integrar com modal de operadores auditados |
| **Comercial** | Migração de Carteira | `migracao-carteira` | 🟡 v2.5 Ativo | Revisar regras de duplicidade e transição |
| **Financeiro** | Controladoria Financeira | `financeiro` | ✅ v3.3 Estabilizado | Rate limit calibrado, metas e semáforos |
| **Financeiro** | Análise de Carteira (360) | `analise_carteira` | 🟢 v3.0 Ativo | Revisar velocidade da query de inadimplência |
| **Vendas** | Dashboard Executivo | `dashboard_vendas` | 🟢 v3.0 Ativo | Conferir filtros regionais e tooltips |
| **Vendas** | Cubo OLAP Multidimensional | `cubo_vendas` | 🟢 v3.0 Ativo | Otimização de CTEs em grandes períodos |

---

### Onda 2: Logística, Depósito & Movimentação Interna (Operação Física)
> **Objetivo:** Garantir que o produto físico seja separado, conferido e estocado sem divergência de inventário.

| Setor | Módulo | Slug | Foco de Revisão |
| :--- | :--- | :--- | :--- |
| **Logística** | Separação WMS | `separacao` | Validação de bipe, concorrência e estorno seguro |
| **Logística** | Carregamento | `carregamento` | Conferência visual de carga e liberação de doca |
| **Logística** | Monitor Logístico | `monitor` | Atualização em tempo real (polling/SSE eficiente) |
| **Logística** | Auditoria de Estoque | `auditoria` | Rastreio de avarias, perdas e divergências |
| **Logística** | Estoque com Previsão | `estoque-previsao` | Cálculo de compras futuras e curva ABC |
| **Logística** | Inventário Geral | `inventario` | Bloqueio de saldo e contagem cega |
| **Logística** | Transferência Matriz ↔ Filial | `transferencia-matriz-filial` | Apuração de Guia ST e espelho entre SC e PR |

---

### Onda 3: Frota, Expedição, Motoristas & Fiscal (Rua e Documentos)
> **Objetivo:** Rastreamento de cargas, acertos financeiros com motoristas e entrada fiscal automatizada.

| Setor | Módulo | Slug | Foco de Revisão |
| :--- | :--- | :--- | :--- |
| **Frota** | Gestão de Frotas | `frota` | Visão geral de custos, manutenção e sinistros |
| **Frota** | Embarques de Carga | `frota-embarques` | Montagem de romaneios e roteirização |
| **Frota** | Retorno de Carga | `retorno-carga` | Conferência de sobras, devoluções e canhoto |
| **Frota** | Acerto de Embarque | `acerto-embarque` | Prestação de contas financeiras dos motoristas |
| **Frota** | App do Motorista (PWA) | `motorista-offline` | Sincronização offline e envio de fotos de entrega |
| **Faturamento** | Conferência XML | `xml` | Entrada automática de NFe de fornecedores |
| **Faturamento** | Consulta Saldo | `consulta-saldo` | Checagem de disponibilidade antes de emissão |

---

### Onda 4: Marketing, RH, Treinamentos & TI (Governança & Pessoas)
> **Objetivo:** CRM comercial, captação de clientes, engajamento de colaboradores e saúde dos servidores.

| Setor | Módulo | Slug | Foco de Revisão |
| :--- | :--- | :--- | :--- |
| **Marketing** | CRM Clientes 360 | `marketing-clientes` | Funil comercial, histórico e agendamentos |
| **Marketing** | Metas de Marketing | `marketing-metas` | Entrada rápida de resultados e campanhas |
| **Marketing** | Admin Marketing | `marketing-admin` | Configuração de tipos de metas e pesos |
| **RH** | Avaliação C.H.A.P.D | `avaliacao-chapd` | Formulários de competências e feedback 360 |
| **RH** | Plano de Ação (PDI) | `plano-acao-chapd` | Acompanhamento de metas individuais da equipe |
| **RH / Geral** | Nutricional Academy | `treinamentos` | Central unificada de manuais e vídeos |
| **TI** | Motores Cron | `crons` | Painel de monitoramento do dispatcher unificado |

---

## 4. O Checklist Padrão do Especialista (Aplicado Módulo a Módulo)

1. **Interface & Layout:**
   - Padronização em Tailwind CSS e paleta institucional Nutricional.
   - Responsividade e hierarquia de modais (evitar conflito de `z-index`).
   - Cards executivos com títulos e tooltips informativos.
2. **Backend & Performance SQL:**
   - Validação de queries (`WHERE`, `LEFT JOIN`, CTEs) sem travar o PostgreSQL legado.
   - Verificação rigorosa de escopo de permissão (`dash_filiais` e `dash_gestores`).
   - Prevenção contra injeção de parâmetros e validação de tipos numéricos.
3. **Resiliência & Rate Limit:**
   - Chamadas API protegidas com tratamento de HTTP 429 e mensagens amigáveis em pt-BR.
   - Sem loops ou polling descontrolado no JS.
4. **Treinamento & Manual no Banco:**
   - Botão direto `Treinamento & Manual` ativo no cabeçalho do módulo.
   - Registro atualizado na tabela `sistema_manuais` com KPIs, botões e FAQ.
5. **Encerramento & Deploy:**
   - Validação de sintaxe (`php -l`).
   - Registro no token de continuidade com lista exata de arquivos para deploy FTP.

---

## 5. Protocolo para Interrupções Diárias (Urgências de Produção)

Quando surgir uma urgência durante o dia enquanto estamos revisando um módulo:
1. **Pausa Rápida:** Criamos um snapshot no token do módulo em andamento.
2. **Hotfix Cirúrgico:** Aplicamos a correção na urgência, testamos e comitamos isoladamente.
3. **Retomada Imediata:** Voltamos ao módulo do organograma exatamente do ponto onde parou, sem esquecer nada e sem depender de anotações soltas em papel.
