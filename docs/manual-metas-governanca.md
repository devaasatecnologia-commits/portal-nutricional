# Manual & Treinamento Executivo — Governança de Metas (Financeiro & Marketing)

**Versão:** 3.2  
**Data:** 09/11/2026  
**Módulos Abrangidos:**
- **Financeiro:** `portal/modules/financeiro.php` e `portal/modules/admin/metas-financeiro.php`
- **Marketing:** `portal/modules/marketing/metas.php` e `portal/modules/admin/metas-marketing.php`
- **APIs:** `v1/src/Controllers/FinanceiroController.php` e `v1/src/Controllers/MetaBuilderController.php`

---

## 1. Visão Geral e Arquitetura de Metas

O ecossistema corporativo conta com governança centralizada de metas dividida estritamente em duas camadas:
1. **Camada Operacional (Portal):** Telas de visualização e alimentação diária de alta velocidade (foco em metas ativas vigentes).
2. **Camada Administrativa (Admin):** Telas restritas a supervisores e gestores para criação, edição de parâmetros, calibração via inteligência histórica e encerramento de ciclos.

Ambos os módulos contam com o **Hub Superior de Governança**, permitindo alternar instantaneamente entre a gestão de **Metas do Financeiro** e **Metas do Marketing**.

---

## 2. Metas do Módulo Financeiro

### 2.1 Indicadores Suportados
- **IAG (Inadimplência Geral %):** Razão entre duplicatas vencidas e carteira total a receber. Direção: *menor é melhor*.
- **IAP (Atraso Crítico +30d %):** Razão entre duplicatas com atraso superior a 30 dias e a carteira ativa. Direção: *menor é melhor*.
- **Recuperação de Crédito (%):** Razão entre clientes/títulos renegociados e cobrados com sucesso. Direção: *maior é melhor*.
- **Perda Potencial >60d (%):** Exposição crítica de risco de perdas em títulos com mais de 60 dias de atraso.
- **Carteira Vencida Máxima (R$):** Teto corporativo em valor absoluto para o montante total vencido.

### 2.2 Filtro de Ciclo & Vigência
Tanto no painel executivo quanto na área administrativa, as metas contam com seletor de ciclos rápidos:
- **Este Mês (Ciclo Vigente):** Filtra metas vigentes no mês corrente.
- **Mês Passado:** Exibe o atingimento de metas encerradas no mês anterior.
- **Trimestre:** Consolida o ciclo trimestral.
- **Este Ano:** Consolidação anual.
- **Todas:** Histórico geral de metas.
- **Personalizado:** Intervalo livre de datas `De` / `Até` baseado em intersecção de datas de vigência no banco.

---

## 3. Metas do Módulo de Marketing

### 3.1 Portal do Marketing (`portal/modules/marketing/metas.php`)
- **Foco Exclusivo em Alimentação:** Removidos todos os modais pesados de criação ou calibração.
- **Entrada Rápida / Alimentação Diária:**
  - Seletor de data (`Hoje`, `Ontem` ou data específica).
  - Tabela com todas as metas ativas, acumulados atuais e inputs numéricos.
  - Gravação em lote com 1 clique (`Salvar Todos os Lançamentos`).
- **Filtros de Ciclo:** Botões rápidos (`Este Mês`, `Mês Passado`, `Trimestre`, `Este Ano`, `Todos`, `Personalizado`) para auditar ciclos passados.
- **Acesso Restrito ao Admin:** Botão `[ ⚙️ ADMINISTRAR METAS ]` no cabeçalho exclusivo para administradores e supervisores.

### 3.2 Painel Administrativo (`portal/modules/admin/metas-marketing.php`)
- **Projeção Inteligente de Ciclo Sob Demanda:**
  - O banner de sugestão do novo ciclo abre em destaque automaticamente **apenas quando as metas ativas do ciclo atual já venceram**.
  - Durante a vigência do ciclo atual, o banner fica recolhido para não poluir a tela, com acesso sob demanda via botão `[ Projeção de Ciclo ]`.
  - Permite criar o novo ciclo em 1 clique ou calibrar valores manualmente antes da confirmação.
- **Filtro de Períodos e Status:** Filtro rápido por ciclo e abas de status (`Ativas`, `Pausadas`, `Concluídas`, `Todas`).

---

## 4. Matriz de Permissões e Segurança
- **Operador de Marketing:** Alimenta valores diários, visualiza acumulados e histórico de progresso.
- **Operador Financeiro:** Acompanha semáforos, barras de progresso e filtros de ciclo vigentes.
- **Administrador / Supervisor:** Acesso total à criação de metas, definição de tolerâncias, calibração via IA, exclusão e transição de ciclos.

---

## 5. Registros na Central de Manuais (`sistema_manuais`)
- **ID 19:** `Painel Financeiro, Metas Corporativas & Auditoria Temporal` (v3.2)
- **ID 86:** `Metas de Marketing & Entrada Rápida de Alimentação` (v3.0)
- **ID 88:** `Administração Executiva de Metas de Marketing` (v3.0)
