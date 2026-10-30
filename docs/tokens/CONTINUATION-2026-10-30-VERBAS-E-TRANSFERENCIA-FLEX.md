# Continuation Token — Hierarquia de Transferência, Ações do Ranking & Tooltips Modernos

**Data / Horário:** 30/10/2026 17:15  
**Autor:** Tech Lead & Arquiteto Especialista  
**Status:** Concluído, Validado e Testado

---

## 1. Resumo das Implementações Cirúrgicas

### A. Hierarquia de Acesso nos Módulos de Transferência
1. **Blindagem de Permissões no Backend:**
   - [`FlexTransferenciaController.php`](file:///c:/xampp/htdocs/API/v1/src/Controllers/FlexTransferenciaController.php): `transferir()` valida se o usuário possui a permissão `transferencia-flex` ou é `admin`.
   - [`FlexMovimentacaoController.php`](file:///c:/xampp/htdocs/API/v1/src/Controllers/FlexMovimentacaoController.php): `movimentar()` valida a permissão `movimentacao-flex` ou `admin`.
   - `movimentacoes()` agora suporta o parâmetro opcional `colaborador={id}` para buscar o histórico de remanejamentos de um vendedor específico (enviadas e recebidas).
2. **Blindagem e Controle Dinâmico no Frontend:**
   - [`verbas-flex.php`](file:///c:/xampp/htdocs/API/portal/modules/verbas-flex.php) e [`verbas-flex.js`](file:///c:/xampp/htdocs/API/portal/assets/js/verbas-flex.js): as abas e botões de atalho para *Transferências*, *Movimentação* e *API Mercos* são ocultados dinamicamente se o usuário logado não tiver as permissões necessárias.
   - [`transferencia-flex.js`](file:///c:/xampp/htdocs/API/portal/assets/js/transferencia-flex.js): se um usuário sem permissão carregar a tela diretamente pela URL, é exibido card de **Acesso Restrito ao Módulo** impedindo a execução de transferências.
   - [`movimentacao-flex.js`](file:///c:/xampp/htdocs/API/portal/assets/js/movimentacao-flex.js): bloqueio semelhante caso o usuário não tenha `movimentacao-flex`.
   - [`transferencia-compra.js`](file:///c:/xampp/htdocs/API/portal/assets/js/transferencia-compra.js): validação estrita das permissões `transferencia-matriz-filial` e `transferencia-filial-matriz`.

---

### B. Layout Moderno ao Passar o Mouse (Micro-interações e Tooltips Flutuantes)
1. **Limpeza do Seletor de Visões do Ranking:**
   - Removidos emojis soltos duplicados com FontAwesome (evitando o visual polúido apontado na captura de tela).
   - Ícones SVG/FontAwesome estilizados com cores corporativas:
     - `fa-receipt text-rose-500` (Consumo em Bonificações)
     - `fa-arrow-up-from-bracket text-purple-600` (Quem Mais Cedeu / Transferiu)
     - `fa-arrow-down-to-bracket text-emerald-600` (Quem Mais Recebeu / Creditou)
     - `fa-scale-balanced text-indigo-600` (Balanço da Equipe)
2. **Componente de Tooltip Flutuante Moderno (`ModernTooltip`):**
   - Substituição do `title` cinza padrão do Windows por popover flutuante ultra-rápido:
     - Fundo escuro translúcido com glassmorphism (`bg-slate-900/95 backdrop-blur-md border border-slate-700/60 rounded-2xl shadow-2xl`).
     - Acompanhamento suave da posição do cursor do mouse.
     - Badge de contexto, título em negrito e descrição rica formatada.
3. **Efeito Hover nos Cards do Ranking:**
   - Elevação suave (`hover:-translate-y-0.5 hover:shadow-md hover:border-violet-300`).
   - Aparição das barras de ações rápidas.

---

### C. Ações Analíticas e Interativas no Ranking
Cada linha do ranking agora possui ações contextuais diretas:
1. **Ver Notas / Extrato (`verExtratoVendedor`):**
   - Alterna para a aba *Consumo Bonificações*, seleciona o vendedor no dropdown e exibe todas as notas faturadas com rolagem suave.
2. **Transferir Verba (`transferirParaVendedor`):**
   - Se o usuário tiver permissão, redireciona para `transferencia-flex.php?para={id}`, abrindo a tela de transferência com o colaborador já pré-selecionado no campo **Destino (PARA)**.
3. **Histórico Flex (`abrirHistoricoColaborador`):**
   - Abre o modal interativo (`z-[75]`) carregando em tempo real todas as transferências recentes onde o colaborador cedeu ou recebeu saldo via Mercos, com valores, datas, motivos e quem autorizou.

---

## 2. Arquivos para Deploy FTP

```plaintext
v1/src/Controllers/FlexTransferenciaController.php
v1/src/Controllers/FlexMovimentacaoController.php
portal/modules/verbas-flex.php
portal/assets/js/verbas-flex.js
portal/modules/transferencia-flex.php
portal/assets/js/transferencia-flex.js
portal/assets/js/movimentacao-flex.js
portal/assets/js/transferencia-compra.js
```
