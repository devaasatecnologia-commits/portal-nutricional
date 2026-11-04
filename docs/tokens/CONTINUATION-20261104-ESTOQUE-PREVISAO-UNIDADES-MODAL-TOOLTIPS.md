# CONTINUATION TOKEN: ESTOQUE-PREVISAO — REMOÇÃO SELETOR UNIDADE, LIMPEZA DE TOOLTIPS E RASTREIO DE CARTEIRA

**Data:** 04/11/2026  
**Módulo:** `portal/modules/estoque-previsao.php` & `portal/assets/js/estoque-previsao.js`  
**Backend:** `v1/src/Controllers/EstoquePrevisaoController.php` & `v1/routes/api.php`

---

## 1. Contexto e Solicitações do Usuário Atendidas

1. **Remoção do Seletor de Unidade de Estoque no Topo:**
   - Retirado o elemento `#select-filial` e seu invólucro do cabeçalho hero verde.
   - O módulo agora trabalha fixo e consolidado em `Matriz + Filial PR (1 + 6)` com visual limpo.
   - Preservados o botão de "Exportar CSV", relógio e usuário logado.

2. **Eliminação de Tooltips Indesejados no Topo e Nomes de Itens:**
   - Retirados todos os balões flutuantes (`data-tooltip` e `title`) da barra hero verde (badge, botões e cabeçalho).
   - O seletor de tooltips modernos foi restrito a `[data-tooltip]` em vez de capturar `[title]` genérico.
   - Removidos atributos `title` redundantes nos nomes dos produtos da tabela (ex: caso do "ARROZ CÃES HAPPY 5KG", que repetia o título em balão cinza/escuro).

3. **Rastreio de Reservados com Permissão de Filial & Formatação de Datas:**
   - Endpoint `GET /v1/estoque-previsao/item/{id}/pedidos-reservados` implementado com validação de escopo de filiais do usuário logado (`UserScope`).
   - Se o usuário não possuir acesso à filial consultada, retorna aviso sem quebrar a visão global.
   - Modal com tabela não condensada (`min-w-[760px]`, `whitespace-nowrap` e largura 980px).
   - Datas de previsão formatadas no padrão brasileiro `DD/MM/YYYY`.

---

## 2. Arquivos Modificados para Deploy FTP

1. `v1/routes/api.php`
2. `v1/src/Controllers/EstoquePrevisaoController.php`
3. `portal/modules/estoque-previsao.php`
4. `portal/assets/js/estoque-previsao.js`

---

## 3. Validação Executada
- Sintaxe PHP: `php -l portal/modules/estoque-previsao.php`, `v1/routes/api.php` e `v1/src/Controllers/EstoquePrevisaoController.php` sem erros.
- Sintaxe JS: `node -c portal/assets/js/estoque-previsao.js` sem erros.
