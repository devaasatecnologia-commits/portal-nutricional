# Continuation Token — Consulta de Saldo: Remoção Segura de Itens com Histórico no ERP, Recálculo & Tooltips Flutuantes

**Data/Hora:** 04/11/2026 08:45  
**Contexto:** Módulo Consulta de Saldo Disponível por Embarque (`consulta-saldo.php`)  
**Status:** Concluído com Sucesso e Validado

---

## 1. Análise Comparativa: Remoção de Item no ERP e no Retorno de Carga

### Comprovação no Banco de Dados PostgreSQL:
1. **Comportamento do ERP Desktop:**
   - Ao excluir um item de um pedido, o ERP:
     1. Insere o registro de auditoria em `pedido_item_log` (`excluido = 'S'`, `motivo = 'Excluiu item'`, valores e quantidades antigas/novas, operador e timestamp).
     2. Executa a exclusão da linha da tabela: `DELETE FROM pedido_item WHERE idpedido = ? AND iditempedido = ?`.
     3. A linha de fato **deixa de existir** em `pedido_item`, e o histórico permanece 100% auditado em `pedido_item_log`.
2. **Comportamento do Módulo de Retorno de Carga (`RetornoController.php` linhas 441-446):**
   - Quando um item é zerado/removido:
     ```php
     if ($qt <= 0) {
         $stmt = $this->pdo->prepare("DELETE FROM pedido_item WHERE iditempedido = ? AND idpedido = ? AND iditem = ? AND ativo = 'S'");
         $stmt->execute([$iditempedido, $idpedido, $iditem]);
         return;
     }
     ```
   - O Retorno de Carga remove a linha de `pedido_item` e registra o histórico na tabela de auditoria `retorno_carga` / `pedido_item_log`.

---

## 2. Implementação no `ConsultaController.php` (Fluxo Idêntico ao ERP)

1. **Trava e Validação:** `SELECT pi.*, i.descricao FROM pedido_item pi ... WHERE pi.idpedido = :ped AND pi.iditempedido = :itemped AND pi.ativo = 'S' FOR UPDATE`.
2. **Registro de Log Oficial no ERP (`pedido_item_log`):**
   - Grava `excluido = 'S'`, `motivo = 'Excluiu item via Consulta Saldo'`, usuário operador autenticado, datahora atual e dados de quantidade/valor.
3. **Limpeza de Dependências da Linha:**
   - Limpa tabelas dependentes vinculadas àquela linha do pedido (`DELETE FROM pedido_item_lote WHERE idpedido = :ped AND iditempedido = :itemped`).
4. **Exclusão da Linha do Pedido:**
   - `DELETE FROM pedido_item WHERE idpedido = :ped AND iditempedido = :itemped AND ativo = 'S'`.
5. **Recálculo Integral:**
   - Recalcula `valortotalitens`, `valortotalcalculado`, `valortotalsaldo`, `pesobruto`, `pesoliquido` e impostos em `pedido`.
   - Recalcula e redistribui parcelas em `pedido_parcela` (ou limpa se o pedido zerar).
   - Recalcula `embarque_pedido` (`totalpesobruto`, `valortotal`, `qt_pedido`) e `embarque_pedido_item` (desvinculando o pedido caso não reste nenhum item ativo).

---

## 3. Correções no Frontend (`portal/assets/js/consulta-saldo.js`)
- Corrigido o `ReferenceError: getUserId is not defined` implementando os métodos `getUserId()` e `getUserNome()` com leitura de inputs de sessão e fallback para localStorage.
- Mapeamento correto de `estoque_fisico`, `qtd_reservada`, `estoque_disponivel`, `qtd_embarque` e `saldo_final`.
- Adicionado sistema de Tooltips flutuantes modernos rich-hover (`#modernTooltipSaldo`) com explicações ao passar o mouse.
- Adicionado alternador de visualização: "Apenas Falta (Negativos)" vs "Todos os Itens do Embarque".
- Adicionada busca rápida instantânea por REF ou nome do produto.

---

## 4. Arquivos para Deploy FTP:
1. `v1/src/Controllers/ConsultaController.php`
2. `portal/modules/consulta-saldo.php`
3. `portal/assets/js/consulta-saldo.js`
