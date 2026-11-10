# Token de Continuação: Central de Chamados (Prints/Ctrl+V, Lightbox, Filtros de Período) & Rastreio ERP Ema (Item 1989)

**Data/Hora:** 10/11/2026 - 14:35  
**Autor:** Tech Lead & Arquiteto de Software Antigravity  
**Status:** ✅ Concluído e Validado  

---

## 1. Rastreio Técnico Especialista: Item 1989 (`BIRBO CÃES CARNE 15KG`)

O usuário solicitou rastrear no ERP Ema a origem exata da quantidade que consta como reservada para transferência do produto `iditem = 1989` (`BIRBO CÃES CARNE 15KG`, referência `03145`).

### Diagnóstico no Banco de Dados:
- **Tabela `item`:** `[1989] 03145 - BIRBO CÃES CARNE 15KG`.
- **Saldos Físicos e Reservas Registradas em `estoque_filial_reserva`:**
  - **Filial 1 (Matriz SC):** Saldo Atual = 2.916 un | **Reserva = 241 un**.
  - **Filial 6 (Paraná PR):** Saldo Atual = 3 un | **Reserva = 18 un**.

### Origem Exata das Reservas de Transferência / Pedidos Internos:
1. **Filial 6 (PR) — 18 unidades reservadas:**
   - **Pedido #159298 (Data: 09/10/2026):**
     - **Tipo:** Pedido Interno / Transferência (`idtransacao = 4`).
     - **Cliente:** `[14523] NUTRICIONAL DISTRIBUIDORA LTDA`.
     - **Status:** 1 (Aberto).
     - **Quantidade Reservada:** **3 unidades** (`quant_reserva = 3.000000`, `reserva_estoque = 'S'`).
     - Usuário: `ROBSON`, editado por `CAROL`.
   - **Pedido #160327 (06/11/2026):** 5 unidades (Venda normal).
   - **Pedido #160328 (06/11/2026):** 10 unidades (Venda normal).
   - *Total:* 3 + 5 + 10 = **18 unidades exatas**.
2. **Filial 1 (Matriz SC) — 241 unidades reservadas:**
   - **Pedido Interno #152057 (05/11/2026):**
     - **Tipo:** Pedido Interno (`idtransacao = 4`).
     - **Cliente:** `[16596] NUTRICIONAL REVENDA (AVARIAS)`.
     - **Status:** 1 (Aberto).
     - **Quantidade Reservada:** **17 unidades** (`quant_reserva = 17.000000`, `qtsaldo = 17.000000`).
   - Os outros 12 pedidos em aberto somam 224 unidades, totalizando as **241 unidades**.
3. **Solicitação de Compra / Transferência Matriz ↔ Filial:**
   - **SC #207 (30/10/2026):** 235 un (embarque 9364 - cancelada 235).
   - **SC #205 (23/10/2026):** 3 un da Filial 6 pendente de faturamento.
   - **SC #29 (26/07/2024):** 20 un com Status 1 (Aberta) pendente.

---

## 2. Central de Chamados: Upgrade Premium, Prints/Ctrl+V e Filtros

### Novas Capacidades Implementadas:
1. **Upload & Printscreen via Clipboard (Ctrl+V):**
   - Suporte total para colar imagens diretamente da área de transferência (`Ctrl+V`) na abertura de chamados ou nas interações da timeline.
   - Detecção inteligente de eventos `paste` e conversão de base64/blob direto para PNG/JPG.
   - Salvamento seguro em `portal/uploads/chamados/`.
   - Suporte alternativo a seleção de arquivos (input file clássico) e drag-and-drop.
2. **Visualizador Lightbox em Tela Cheia:**
   - Modal com `z-[80]` com efeito backdrop blur para inspecionar prints em alta resolução.
   - Botão para download do arquivo original e atalho `Esc` para fechar.
3. **Filtro Completo por Período de Datas:**
   - Barra com inputs de Data Inicial e Data Final (`type="date"`).
   - Pílulas de filtro rápido: **Hoje**, **7 Dias**, **Este Mês** e **Todos**.
   - Integração direta no endpoint `GET /v1/chamados/listar` com filtragem SQL via `c.data`.

---

## 3. Arquivos para Deploy FTP:
- `v1/routes/api.php`
- `v1/src/Controllers/ChamadosController.php`
- `portal/modules/chamados.php`
- `portal/assets/js/chamados.js`


---

## 8. Incidente Crítico Registrado: Chamado #30
- **Título:** `[CRÍTICO] ERP EMA: Saldo fantasma em (+) Qtd transfe na Transação 1003`
- **Categoria:** Bug / Rotina de Produção
- **Criticidade:** Crítica
- **Status:** Concluído / Resolvido (3)
- **Causa Raiz:** Flag de transferência automática ativada indevidamente na transação 1003 entre 04/09 e 29/10/2026, gerando 114 transferências fantasmas pendentes em `nf_transf_auto` e inflando a coluna `(+) Qtd transfe` de 57 itens.
- **Validação:** Teste unitário executado na NF #150432 (Transf #379), comprovando recálculo instantâneo no ERP EMA de 19 para 18 un no item 1989.
- **Entrega Técnica:** Queries DBeaver e comandos UPDATE em lote entregues na timeline de `chamado_operacao`.
