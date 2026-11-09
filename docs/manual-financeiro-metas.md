# Manual Executivo & Treinamento: Metas Financeiras, Governança e Histórico Temporal

**Módulo:** Controladoria Financeira & Gestão de Metas Corporativas  
**Versão:** 3.1 — Novembro/2026  
**Público-Alvo:** Diretores, Gerentes Financeiros, Controladores e Administradores do Sistema  

---

## 1. Visão Geral da Governança de Metas

A gestão estratégica de metas no ecossistema Nutricional foi desenhada sob os mais rigorosos padrões de BI e Controladoria moderna, unificando dois pilares vitais da operação:
1. **Metas do Financeiro:** Inadimplência Geral (IAG), Atraso Crítico (+30d IAP), Taxa de Recuperação de Crédito, Perda Potencial >60d e Tetos de Carteira Vencida.
2. **Metas do Marketing:** Captação de Leads, Campanhas, Alcance de Redes Sociais, Engajamento e Eventos.

Ambas agora contam com gerenciamento centralizado na **Área Administrativa** do ERP (`/portal/modules/admin/`), oferecendo um **Hub de Metas Corporativo** com navegação rápida e transparente entre os departamentos.

---

## 2. Regras de Permissão e Segurança (Acesso Restrito)

### 2.1 Visibilidade do Botão "GERENCIAR METAS"
* **No Dashboard Financeiro (`portal/modules/financeiro.php`):**
  * O botão **"GERENCIAR METAS"** só é visível para usuários com perfil de **Administrador** (`permissoes.includes('admin')`, `permissoes.includes('financeiro-admin')`, `nivel === 'admin'` ou `uid === 4`).
  * Usuários comuns, operadores ou representantes comerciais **não têm acesso visual** ao botão de gerenciamento. Eles acompanham apenas o painel de progresso e semáforos das metas vigentes.
* **Na Área Administrativa (`metas-financeiro.php` e `metas-marketing.php`):**
  * Caso um usuário não autenticado como administrador tente acessar diretamente via URL, uma guarda defensiva automática o redireciona imediatamente para o dashboard correspondente.

---

## 3. Foco Operacional: Priorização de Metas Ativas

Para garantir foco e eliminar qualquer poluição visual, tanto o módulo operacional quanto o gerenciador administrativo priorizam **exclusivamente as metas ativas**:

### 3.1 No Gerenciador Administrativo (`metas-financeiro.php`)
* **Visão Padrão:** Ao abrir a tela, o sistema aplica automaticamente o filtro **Metas Ativas**.
* **Pílulas de Status Rápidas:**
  * `[ 🟢 Metas Ativas (N) ]` *(Foco principal)*
  * `[ ⏸️ Pausadas (N) ]`
  * `[ 🏁 Concluídas (N) ]`
  * `[ 📁 Todas as Metas (N) ]`
* **Cards de Resumo Clicáveis:** Os 4 cards executivos no topo (Total, Ativas, Inadimplência, Recuperação) são atalhos interativos: ao clicar em qualquer um deles, o grid de metas é filtrado instantaneamente.
* **Ações em 1 Clique:**
  * **Pausar / Ativar:** Alterna a vigência da meta sem necessidade de abrir formulários.
  * **Editar:** Permite calibrar valores, tolerâncias de alerta e datas de início/fim.
  * **Excluir:** Remove metas obsoletas com confirmação segura e preservação de integridade.

### 3.2 No Dashboard Financeiro (`portal/modules/financeiro.php`)
* **Regra de Vigência Rígida:** O backend (`getMetasComProgresso`) seleciona apenas metas onde `status = 'ativa'` E a data atual se encontra entre a data de início e a data de fim (`CURRENT_DATE >= data_inicio AND CURRENT_DATE <= data_fim`).
* **Semáforos de Desempenho:**
  * 🟢 **Dentro da Meta:** Desempenho exemplar (ex: IAG abaixo do teto ou Recuperação acima do alvo).
  * 🟡 **Zona de Alerta:** Valor atingiu a margem de tolerância configurada.
  * 🔴 **Meta Rompida:** Limite ultrapassado, demandando intervenção imediata da equipe de cobrança.

---

## 4. Auditoria Temporal e Métricas Históricas

O bloco de **Histórico de Performance & Auditoria Temporal** permite analisar tendências e sazonalidades nos dados gravados:

1. **Períodos Rápidos de 1 Clique:**
   * `Hoje`, `7 dias`, `Este Mês`, `Mês Passado`, `30 dias`, `Este Ano`, `Todos` e `Personalizado` (com campos de data inicial e final sob medida).
2. **Filtro por Dia da Semana:**
   * Permite isolar um dia específico (ex: todas as segundas-feiras) para neutralizar o acúmulo financeiro do fim de semana e entender o fluxo real de baixas bancárias.
3. **Cards de Auto Soma & Médias Apuradas:**
   * **Dias Apurados:** Contagem exata de snapshots registrados no período.
   * **Média Simples:** Média aritmética direta das taxas diárias apuradas.
   * **Média Ponderada por Volume:** Total de saldo vencido somado dividido pela carteira total somada no período. Expressa o índice corporativo matematicamente exato.
   * **Auto Soma de Vencidos:** Total financeiro acumulado exposto no período.
   * **Média Diária de Vencidos:** Volume médio diário sob cobrança.
   * **Meta de Referência:** Teto corporativo ativo plotado como linha pontilhada guia no Chart.js.

---

## 5. Hub de Metas do Marketing no Admin (`metas-marketing.php`)

Seguindo o mesmo padrão de excelência:
* **Menu do Admin Nutricional:** Adicionados os acessos dedicados:
  * `Metas do Financeiro` (`metas-financeiro.php`)
  * `Metas do Marketing` (`metas-marketing.php`)
* **Hub Superior:** No topo de ambas as telas, um seletor unificado permite transitar de forma fluida entre Controladoria Financeira e Comunicação/Marketing.
* **Gestão de Metas de Marketing:**
  * Acompanhamento de metas ativas por padrão.
  * Botão de alimentação direta de valor alcançado e observações de ciclo.
  * Integração nativa com os modelos de metas dinâmicos em `/portal/modules/marketing/admin/tipos-meta.php`.

---

## 6. Procedimento de Deploy FTP

Arquivos que devem ser sincronizados para produção no cPanel/FTP:
1. `portal/modules/financeiro.php`
2. `portal/assets/js/financeiro.js`
3. `portal/modules/admin/metas-financeiro.php`
4. `portal/modules/admin/metas-marketing.php`
5. `portal/modules/admin/components/sidebar.php`
6. `v1/src/Controllers/FinanceiroController.php`
7. `v1/routes/api.php`
8. `docs/manual-financeiro-metas.md`
9. Tabela `sistema_manuais` (registro ID 19 atualizado para Versão 3.1)
