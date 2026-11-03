# Continuation Token — Logística (Separação, Carregamento & Ajuste de Fotos) e Verbas Flex (Menu & Ranking)

**Data:** 03/11/2026  
**Responsável:** Antigravity Tech Lead  
**Branch:** main  

---

## 1. Contexto e Objetivos Atendidos
1. **Verbas Flex — Menu Superior e Abas Limpas:**
   - O menu que quebrava/cortava ("Movim...") foi limpo e otimizado com design responsivo.
   - As 5 abas principais do módulo agora são: `Painel & Dash`, `Ranking Consumo`, `Consumo Bonificações`, `Créditos / Empréstimos` e `Transferências`.
   - As opções complementares ("Movimentações", "Status Mercos" e "Treinamentos") ficam no Hero Banner superior direito de forma elegante, sem sobrecarregar a barra de navegação.
2. **Verbas Flex — Visões do Ranking ("Quem Mais Cedeu", "Quem Mais Recebeu" e "Balanço da Equipe"):**
   - Corrigido o problema onde os cliques não tinham efeito:
     - Vinculação de eventos do DOM agora é síncrona e imediata na largada, independente do ciclo de vida assíncrono ou de `document.readyState`.
     - Contrastes dos chips ativos corrigidos (`.vf-chip.on i, .vf-chip.on span { color: #fff !important; }`).
     - Criado Empty State com botões de atalho inteligentes (`[ Últimos 30 Dias ]`, `[ Mês Anterior ]`) para quando o período atual (ex: início de mês) ainda não possui transferências no Mercos.
     - Corrigidos links de transferência dinâmica para usar `appBase` (`/API/portal/...` vs `/portal/...`).
3. **Logística — Separação e Carregamento (Embarques dos Últimos 35 Dias):**
   - Filtros de `ep.data` corrigidos e otimizados com janela de 35 dias para trás até a data atual, garantindo que embarques não conferidos (`pex_conferido = 'N'`) apareçam sem exclusão indevida.
   - Removida incompatibilidade de sintaxe MySQL (`DATE_ADD`) no PostgreSQL.
4. **Logística — Ajuste Rápido de Fotos Pendentes no Carregamento:**
   - Novo endpoint `POST /v1/carregamento/ajustar-foto` registrado em `v1/routes/api.php`.
   - No frontend (`carregamento.js`), ao receber `codigo: 'FOTOS_PENDENTES'`, o sistema abre o modal interativo `abrirModalAjustarFotosPendentes(itensSemFoto)`, permitindo tirar/anexar a foto de cada item faltante na hora e concluir o carregamento sem estornar a carga.

---

## 2. Arquivos Modificados para Deploy FTP
```plaintext
portal/assets/js/carregamento.js
portal/assets/js/verbas-flex.js
portal/modules/verbas-flex.php
v1/routes/api.php
v1/src/Controllers/CarregamentoController.php
v1/src/Controllers/SeparacaoController.php
```
*(Mais os arquivos do módulo de Marketing da sessão anterior, se ainda não deployados)*
```plaintext
portal/modules/marketing.php
portal/modules/marketing/alimentacao.php
portal/modules/marketing/dashboard.php
portal/modules/marketing/metas.php
portal/modules/marketing/relatorios.php
v1/src/Controllers/MarketingController.php
v1/src/Controllers/MetaBuilderController.php
v1/src/Middleware/JwtMiddleware.php
v1/src/Services/MarketingService.php
```

