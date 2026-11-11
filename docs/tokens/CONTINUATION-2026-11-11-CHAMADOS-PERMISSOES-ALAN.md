# CONTINUATION TOKEN - REDIRECIONAMENTO DE CHAMADOS PARA ALAN, PERMISSÕES GRANULARES E VISÃO DEDICADA

**Data:** 11/11/2026  
**Responsável Técnico:** Tech Lead & Arquiteto de Software (White-Label: MARCON / ALAN)  
**Chamado Relacionado:** Chamado #35 [EVOLUÇÃO - Permissões Granulares & Central de Chamados]  
**Status:** 100% Concluído, Homologado e Testado em Produção

---

## 1. Contexto e Objetivos Entregues

1. **Redirecionamento Automático para o Técnico (ALAN):**
   - Substituída a string estática legada `'DEVAASA'` no backend (`ChamadosController::criar`).
   - Todo chamado criado no Portal agora é atribuído automaticamente para **ALAN** com `status = 0` (Aberto), caindo instantaneamente na fila dele para triagem e solução.
   - Criada a rota `POST /v1/chamados/assumir` permitindo ao técnico assumir a condução do chamado com 1 clique (atualizando `atendente = ALAN` e movendo status de 0 para 1 - Em Análise, registrando a operação na timeline).

2. **Visão Dedicada e Acolhedora para o Usuário Solicitante:**
   - Para usuários sem privilégios de gestor (`!usuario.pode_ver_todos`):
     - Eliminada a poluição do Kanban corporativo e métricas globais.
     - Inserido banner de acolhimento com atalho em destaque para **Abrir Novo Chamado**.
     - Seção limpa **"Meus Chamados & Acompanhamento"** em formato de cards modernos com indicador de status bem legível, responsável técnico visível (ALAN) e botão "Acompanhar Andamento & Mensagens".
     - Empty state com ilustração orientativa quando não houver chamados abertos.

3. **Painel de Service Desk para o Administrador (ALAN):**
   - Central completa com Kanban de 4 estágios, Modo Tabela e Painel Executivo.
   - Filtro rápido na barra superior: **"Todos"**, **"Atribuídos a Mim (ALAN)"** e **"Sem Atendente"**.
   - Botão rápido "Assumir" disponível diretamente nos cards do Kanban, na tabela de listagem e no modal de detalhes.

4. **Filtragem Estrita de Módulos por Permissão (`usuario_permissoes`):**
   - O backend agora consulta diretamente a tabela `usuario_permissoes` vinculada a `sistema_modulos`.
   - No formulário de abertura, se o usuário selecionar **Portal Nutricional**, são listados **exclusivamente os módulos aos quais ele tem acesso concedido**.
   - Validação defensiva no backend: caso tente submeter módulo bloqueado, a API rejeita com erro 400.
   - Respeita o campo `chamados_tipos_permitidos` e `chamados_ver_todos` configurados no painel Admin de Permissões.

---

## 2. Arquivos Modificados para Deploy FTP

```text
v1/src/Controllers/ChamadosController.php
v1/src/Controllers/CronRotinasController.php
v1/routes/api.php
portal/manifest.json (NOVO - PWA)
portal/sw.js (NOVO - Service Worker PWA)
portal/assets/icons/ (NOVO - Ícones PWA 192/512/apple-touch)
portal/estrutura/header.php
portal/assets/css/style.css
portal/modules/verbas-flex.php
portal/assets/js/verbas-flex.js
portal/modules/transferencia-flex.php
portal/assets/js/transferencia-flex.js
portal/assets/js/chamados.js
portal/modules/chamados.php
portal/modules/admin/crons.php
portal/modules/admin/components/header.php
portal/modules/admin/components/footer.php
docs/tokens/CONTINUATION-2026-11-11-CHAMADOS-PERMISSOES-ALAN.md
docs/CONTINUATION_TOKEN.md
```

---

## 3. PWA & Otimizações Mobile-First para Gestores (Tiago, Michel e Tales) [CONCLUÍDO]

- **Diagnóstico da Causa Raiz das Quebras no Celular:**
  1. *Tooltips flutuantes fixos:* A função `initModernTooltips` ativava no toque (`mouseover`) e travava na tela permanentemente com `position: fixed` cobrindo números, botões e selects. Desativada de forma absoluta no mobile via CSS `@media (hover: none) and (pointer: coarse), (max-width: 1024px)`.
  2. *Auto-zoom involuntário no iOS Safari:* Inputs com tamanho de fonte menor que 16px provocavam zoom forçado na tela, quebrando o alinhamento lateral. Normalizado para `16px` em dispositivos móveis.
  3. *Hero Banners com quebras desalinhadas:* Os botões e atalhos foram reorganizados com carrossel horizontal suave (`flex flex-nowrap overflow-x-auto no-scrollbar`), evitando esmagamento do título e quebras caóticas.
  4. *Grid de KPIs Adaptativo:* `grid-cols-1 sm:grid-cols-2 lg:grid-cols-3` com tipografia truncada e números legíveis no celular (`text-xl sm:text-2xl font-black truncate`).
- **PWA (Progressive Web App):**
  - Manifesto `portal/manifest.json` com atalhos de Verbas Flex, Transferências, Chamados e Dashboard de Vendas.
  - Service Worker `portal/sw.js` com cache de assets e modo offline resiliente.
  - Toast interativo touch-friendly de instalação no celular e instruções passo a passo para iOS ("Adicionar à Tela de Início").
- **Filial Inteligente dos Gestores:**
  - `transferencia-flex.js` e `verbas-flex.js` agora inicializam diretamente com a filial padrão do gestor (`vf_filial_pref` ou `dash_filiais`, Filial 6 para Tales, Filial 1 para Tiago e Michel).

---

## 4. Chamado #36 (TALES - Gestor Filial 6) [CONCLUÍDO]

- **Relato Original:** Usuário da Filial 6 necessitava iniciar com a filial 6 pré-selecionada no Verbas Flex e nos chamados, ver apenas seus módulos liberados e ter permissão de abrir chamados para todos os sistemas (Portal, Suporte Comercial, Mercos, Relatórios, Email, etc.).
- **Ações Técnicas Realizadas:**
  1. Chamado #36 assumido formalmente pelo técnico **ALAN** (`status = 1 -> 3`).
  2. Atualizado no banco `usuario.chamados_tipos_permitidos = 'TODOS'` para o usuário TALES (`idusuario = 19`), equalizando com os demais gestores da empresa.
  3. `ChamadosController.php`: injetada a detecção de `idfilial` padrão a partir de `dash_filiais` e adicionados os sistemas `SUPORTE_COMERCIAL` e `RELATORIOS`.
  4. `portal/assets/js/chamados.js`: no `abrirModalNovo()`, inicializa o chamado com a filial nativa do usuário (`usuario.idfilial = 6`).
  5. `VerbasFlexController.php`: endpoint `/contexto` retorna `filial_padrao = 6` e `filtros()` assume filial 6 se não informada.
  6. `portal/assets/js/verbas-flex.js`: carrega e pré-seleciona a filial padrão do usuário logado (Filial 6) com persistência em `localStorage`.
  7. Registrada entrega técnica formal e checklist de 5 passos na timeline de `chamado_operacao` (Operação #3).

---

## 5. Arquivos Modificados para Deploy FTP

| Arquivo Local | Destino Servidor / FTP | Motivo da Alteração |
|---|---|---|
| `portal/modules/admin/crons.php` | `portal/modules/admin/crons.php` | Proteção contra `null` em `innerHTML`/`textContent` no dashboard de crons e auditoria |
| `portal/modules/admin/components/header.php` | `portal/modules/admin/components/header.php` | Normalização de query parameters na montagem da URL em `apiFetch` |
| `portal/modules/admin/components/footer.php` | `portal/modules/admin/components/footer.php` | Normalização de query parameters na montagem da URL em `apiFetch` |
| `portal/modules/chamados.php` | `portal/modules/chamados.php` | Select dinâmico de filial via template Alpine; só exibe filiais liberadas do usuário (Tales só Filial 6) |
| `portal/assets/js/chamados.js` | `portal/assets/js/chamados.js` | Estado reativo de filiais liberadas e inicialização com a filial permitida |
| `portal/assets/js/verbas-flex.js` | `portal/assets/js/verbas-flex.js` | Inicialização automática da filial nativa (Filial 6) com persistência |
| `v1/src/Controllers/ChamadosController.php` | `v1/src/Controllers/ChamadosController.php` | Endpoint `/dashboard` retorna `filiais` filtradas por `dash_filiais` e validação estrita em `/criar` |
| `v1/src/Controllers/VerbasFlexController.php` | `v1/src/Controllers/VerbasFlexController.php` | `filial_padrao` no contexto e fallback automático para filial do usuário |
| `v1/src/Controllers/CronRotinasController.php` | `v1/src/Controllers/CronRotinasController.php` | Implementação do comando `custo_gerencial_auto` e simulador Dry-Run |
| `v1/routes/api.php` | `v1/routes/api.php` | Rota POST `/v1/chamados/assumir` |

---

## 3. Homologação e Testes Realizados

- **Sintaxe PHP:** `php -l` em todos os arquivos alterados (0 erros).
- **Dashboard ALAN vs TIAGO:**
  - ALAN: 42 módulos, 11 sistemas, fila geral completa com 36 chamados.
  - TIAGO: Exatamente os 8 módulos liberados em `usuario_permissoes`, visão restrita aos seus próprios chamados.
- **Validação de Bloqueio:** Tentativa de submissão de módulo não permitido rejeitada com `"Você não possui permissão no módulo '...' do Portal."`.
- **Criação e Direcionamento:** Chamado criado com atendente `ALAN` e status `0`.
- **Assumir Chamado:** Rota `/v1/chamados/assumir` testada com sucesso (moveu para status 1 e gravou timeline).
- **Timeline Chamado #35:** Operação #3 registrada no banco com sucesso.


---

## 5. Refatoração Responsiva Mobile App-First v2.0 (6 Módulos dos Gestores — Chamado #41)
- **Causa Raiz Global Corrigida (`portal/assets/css/style.css` & `portal/assets/css/module-base.css`):**
  - Substituído `repeat(2, 1fr)` por `repeat(2, minmax(0, 1fr)) !important` e adicionado `.grid > * { min-width: 0; }`, impedindo o vazamento lateral da 2ª coluna de cards no celular.
  - Desativado `#nutri-tooltip` em telas touch/mobile (`@media (hover: none) and (pointer: coarse), (max-width: 1024px)`).
- **Módulos Refatorados:**
  1. **Estoque com Previsão (`portal/modules/estoque-previsao.php` & `portal/assets/js/estoque-previsao.js`):** Cards de KPI com `min-w-0`/`truncate`, barra de busca sem `sticky` no mobile (`lg:sticky`), **Cards Interativos Mobile de Produtos** (`#mobile-cards-body`) e modal de detalhes com `pr-7` no header e **Cards Mobile de OCs** (eliminando a quebra vertical letra por letra).
  2. **Cubo de Vendas (`portal/modules/cubo-vendas.php` & `portal/assets/css/cubo-vendas.css`):** Removido `position: sticky; top: 56px;` de `#cuboFiltros` no celular (que cobria a tela inteira), ocultado o header desktop duplicado (`hidden lg:flex`), adicionado seletor rápido **"Agrupar por"** em 1 toque e painel de filtros recolhível (`expandirFiltrosMobile`).
  3. **Dashboard Executivo de Vendas (`portal/modules/dashboard-vendas.php` & `portal/assets/js/dashboard-vendas.js`):** Ocultado header desktop duplicado no mobile, painel de filtros recolhível (`expandirFiltrosMobile`), barras de botões com scroll horizontal e ajuste responsivo do gráfico `chartEvolucao` no celular.
  4. **CRM Clientes (`portal/modules/marketing/clientes.php`):** Hero Header e 6 KPIs sem corte lateral, **Barra Rápida de Campo (Modo App Gestor)**, **Cards Mobile de Clientes** (`#clientesMobileCards`) com 4 ações diretas (**💬 WhatsApp**, **📅 Agendar**, **🛒 Mercos**, **👁️ Ficha**), função `abrirPedidoMercos()` (copia CNPJ/Nome e abre `https://app.mercos.com/`) e coluna de **Próximos Compromissos + Novo Compromisso** priorizada no topo no celular (`order-1 lg:order-2`).
  5. **Análise de Carteira (`portal/modules/analise-carteira.php` & `portal/assets/js/analise-carteira.js`):** Removidas 2 tags `</div>` órfãs que quebravam o escopo Alpine, KPIs truncados (`min-w-0`), tabelas com `min-w` e modal de cobrança responsivo.
  6. **Material de Apoio & Amostras (`portal/modules/pedido-amostra.php` & `portal/assets/js/pedido-amostra.js`):** Abas do Hero Header em `grid-cols-2` no mobile (sem corte lateral), KPIs ajustados e **Cards Mobile no Histórico de Pedidos** (`#pedidosMobileCards`).
