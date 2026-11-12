# Token de Continuação — 12/11/2026: Dashboard de Vendas & Módulo Recompra com Hierarquia Total

## 1. Contexto e Objetivos Executados
Atendimento das solicitações de ajuste e evolução nos módulos executivos de vendas:
- **Dashboard de Vendas (`portal/modules/dashboard-vendas.php` / `portal/assets/js/dashboard-vendas.js` / `VendasDashboardController.php`):**
  1. **Projeção Oficial = Média dos 3 Últimos Meses Fechados:**
     - A média é calculada estritamente sobre os 3 meses anteriores (M-3, M-2, M-1: Ago, Set e Out), totalizando exatamente **R$ 4.104.106,55** (`12.312.429,64 / 3`).
     - O Mês Atual (Nov/2026) **NÃO** entra no cálculo da média de projeção.
     - No card do Mês Atual, o valor em destaque exibido é a **Média dos 3 Últimos Meses (Projeção/Meta)**, com badge `PROJEÇÃO (MÉDIA 3M)` e peso médio de 559,7 ton.
     - A barra de progresso mede o quanto o faturamento Realizado atual (`R$ 559.873,00`) já alcançou dessa média dos 3 meses.
     - O rodapé do card exibe o Realizado com peso e carteira em aberto, e ao clicar no card abre o modal detalhado com todos os demais indicadores (ritmo diário, por representante e região).
  2. **Correção do Link do Módulo de Recompra:**
     - Ajustado o link no banner do Dashboard para `<?= $appBase ?>/portal/modules/recompra-clientes.php`.
- **Módulo Recompra de Clientes com Hierarquia Total (`portal/modules/recompra-clientes.php` / `portal/assets/js/recompra-clientes.js`):**
  1. **Correção do Carregamento de Filiais:**
     - Corrigido o endpoint de carga para `/v1/vendas/cubo/filiais` (com alias de segurança `/v1/vendas/dashboard/filiais-permitidas` adicionado no backend e liberado no `ModulePermissionMiddleware` para `recompra_clientes`).
     - O select de filiais agora carrega instantaneamente todas as filiais liberadas ao usuário.
  2. **Respeito Estrito à Hierarquia e Escopo do Usuário Logado:**
     - **Filiais Permitidas:** Se o usuário possui apenas 1 filial liberada, ela é pré-selecionada automaticamente e sinalizada como `(fixo)`.
     - **Gestores / Supervisores:** Carrega os supervisores da filial ativa. Usuários com perfil de gestor (como **Tiago**, **Michel** ou **Tales**) têm seu gestor travado automaticamente como `(gestor fixo)`.
     - **Representantes:** Carrega apenas os representantes subordinados ao gestor/filial selecionada. Se for usuário representante, fica travado como `(rep fixo)`.
     - **Regiões Dinâmicas do Escopo:** Montadas dinamicamente com base nas regiões com clientes da carteira do usuário.
     - **Segurança no Backend:** Adicionadas travas SQL `AND EXISTS (SELECT 1 FROM vw_gestor_repre WHERE ... AND idgestor IN (...))` e filtro de filiais em `clientesRisco()`.
- **Chamados & Manuais:** Chamado #47 concluído e documentado; manual de treinamento de Recompra vinculado diretamente ao botão de treinamento do módulo.

## 2. Arquivos para Deploy FTP
```
portal/modules/dashboard-vendas.php
portal/assets/js/dashboard-vendas.js
portal/modules/recompra-clientes.php
portal/assets/js/recompra-clientes.js
portal/assets/js/treinamentos.js
v1/routes/api.php
v1/src/Controllers/VendasDashboardController.php
```
