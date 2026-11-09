# Manual do Especialista — Controladoria Financeira & Gestão de Metas v3.3

## 1. Visão Geral e Propósito
O módulo de **Controladoria Financeira** (`portal/modules/financeiro.php` e `v1/src/Controllers/FinanceiroController.php`) é a central estratégica de monitoramento de crédito, recebíveis e metas corporativas da Nutricional.

---

## 2. Indicadores Principais de Cobrança
- **Carteira Total:** Soma de todos os títulos em aberto (a vencer + vencidos).
- **A Vencer:** Recebíveis dentro do prazo regular de vencimento.
- **Vencidos 1 a 30 dias:** Inadimplência recente (fase preventiva).
- **Vencidos 31 a 60 dias (IAP):** Inadimplência crítica que compõe o Índice de Atraso Prolongado.
- **Vencidos >60 dias:** Inadimplência crônica sujeita a medidas jurídicas e provisão de perda.

---

## 3. Metas Corporativas & Painel com Semáforos
- **Indicadores Contemplados:** IAG (Meta Geral), IAP (Atraso Crítico +30d), Taxa de Recuperação e Teto de Perda >60 dias.
- **Semáforo Inteligente:** Classificação dinâmica em *Atingida (Verde)*, *Atenção (Amarelo)* e *Crítica (Vermelho)* com barra de progresso.
- **Filtros por Ciclo:** Avaliação instantânea por *Este Mês*, *Mês Passado*, *Trimestre*, *Este Ano* ou *Todas*.
- **Sem Duplicações:** O backend filtra filiais válidas com subquery dedicada para evitar duplicações de registros da base legada.

---

## 4. Otimização de Rate Limiting
- O middleware de limitação de taxa (`RateLimitMiddleware`) agora opera com escopo por rota (`user:ID:path:ROTA`), garantindo que a navegação do usuário em outros módulos ou telas não consuma o teto do dashboard financeiro.
- Limite operacional da rota de dashboard expandido para 200 requisições por minuto.

---

## 5. Treinamento Integrado
O módulo possui acesso direto no topo: `Treinamento & Manual`, direcionando para a Nutricional Academy (`portal/modules/treinamentos.php?modulo=financeiro`).
