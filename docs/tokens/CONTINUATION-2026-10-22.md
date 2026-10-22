# Token de Continuidade — 2026-10-22

**Módulo:** Transferência de Compra (Matriz $\leftrightarrow$ Filial PR)  
**Status:** Implementado, validado e pronto para deploy FTP.  
**Autor:** Antigravity Tech Lead / Arquiteto Especialista

---

## 1. Resumo Técnico das Entregas

### 1.1 Regra de Negócio de Necessidade com Reserva 14523
- Implementado cálculo inteligente da quantidade necessária a transferir da Matriz para a Filial PR:
  $$\text{Estoque Livre PR} = \max(\text{Saldo Físico Filial 6} - \text{Qtd Pendente Pedidos Abertos (Status 1) Cliente 14523},\; 0)$$
  $$\text{Qtd a Solicitar} = \max(\text{Qtd Necessária no Embarque} - \text{Estoque Livre PR},\; 0)$$
- Proteção garantida do estoque reservado para a Filial PR (`idcliforemp = 14523`).
- Separação fiscal automática entre itens de **Remessa** (`idmarca = 60`, amostras/brindes/apoio a vendas) e itens **Normais** (produtos comerciais).

### 1.2 Backend (API Slim 4 / PSR-7)
- **Nova Rota:** `GET /v1/transf-compras/matriz-filial/previa/{embarque}`
  - Retorna cabeçalho do embarque, histórico de solicitações geradas no ERP, resumo executivo de KPIs e tabela completa de itens analisados com badges de proteção.
- **Aprimoramento de Rota:** `GET /v1/transf-compras/matriz-filial/pendentes`
  - Suporte a filtros de busca por número ou placa (`?q=...`) e flag para auditoria retroativa de embarques já faturados (`?faturados=1`).

### 1.3 Frontend (Portal Modular)
- **Tela `portal/modules/transferencia-compra.php`:**
  - Layout refinado com Tailwind CSS e Plus Jakarta Sans.
  - Hero com navegação ágil entre fluxos (Matriz $\rightarrow$ Filial vs Filial $\rightarrow$ Matriz).
  - 4 Cards de KPI no topo: Solicitações no filtro, Valor Guia ST apurado, Embarques Filial 6 aguardando e Indicador da Proteção Cliente 14523.
  - Grade de Embarques Pendentes da Filial PR com badges de status ("Aguardando" vs "Solicitado #ID") e botão direto para consulta de prévia.
  - **Modal Premium de Prévia (`modalPrevia`):**
    - Sub-dashboard interno com 4 KPIs: Total Itens, Qtd Total a Solicitar, Subtotais Normais e Remessas (Marca 60).
    - Tabs de filtro interno: Todos, Apenas a Solicitar, Normais, Remessas.
    - Campo de busca instantânea de itens.
    - Tabela rica e clara com indicadores visuais de necessidade e de reserva protegida.
    - Botão "Copiar Resumo" para facilitar o repasse para ordens internas ou ERP.
- **Javascript `portal/assets/js/transferencia-compra.js`:**
  - Gerenciamento de estado, debounce nas buscas, renderização dinâmica da tabela e modal com SweetAlert2 para memória de cálculo fiscal das guias ST.

---

### 1.4 Módulo Retorno de Carga / Revenda (`retorno.php`, `retorno.js`, `RetornoController.php`)
- **Correção Crítica de Quantidade no ERP (`quant_impressao`):**
  - Identificado que o ERP exibe e opera a quantidade comercial na coluna `quant_impressao` da tabela `pedido_item`.
  - O método `aplicarQuantidadePedidoItem` atualizava apenas `qt = :qt`, deixando `quant_impressao` com o valor antigo (gerando divergência entre o que o portal mostrava e o que o ERP exibia).
  - O método `inserirItemCompleto` chumbava `'quant_impressao' => 1`.
  - Corrigidos ambos os métodos para sincronizarem rigorosamente `quant_impressao = :qt`.
  - Sincronizado o item 2152 no pedido 152057 (cliente 16596 - REVENDA) para 2 UN reais ($296,16 e 20,20 kg).
- **Correção da Soma do Peso Total e Unitário:**
  - Corrigido o erro nas consultas `produtoExisteNoPedido` e `getEstoqueAtivo`, que faziam `(qt * qtpesoliquido) as peso_total`, multiplicando o peso pela quantidade ao quadrado.
  - Ajustado para utilizar o peso real cadastrado no item (`i.pesoliquido`) e o peso da linha (`pi.qtpesoliquido`).
- **Panorama Instantâneo ao Bipar:**
  - Ao bipar o código de barras ou informar a referência, o backend agora busca a situação do produto em **todos os 4 destinos** (Container, Matéria-Prima, Revenda e Trocas Fornecedor) em pedidos abertos (`status = 1`).
  - O frontend exibe no cabeçalho o peso líquido/bruto unitário e nos 4 cards de destino exibe badges em tempo real de saldo (quantidade em UN e peso em KG), além do número do pedido aberto.

---

## 2. Arquivos para Deploy FTP

Para deploy remoto (cPanel/Linux), subir os seguintes arquivos:

1. `v1/routes/api.php`
2. `v1/src/Controllers/TransferenciaCompraController.php`
3. `v1/src/Controllers/RetornoController.php`
4. `portal/modules/transferencia-compra.php`
5. `portal/assets/js/transferencia-compra.js`
6. `portal/modules/retorno.php`
7. `portal/assets/js/retorno.js`
8. `index_legado.php` (Tolerância inteligente de 5 min para o motor de crons unificado)
9. `docs/CONTINUATION_TOKEN.md`
10. `docs/tokens/CONTINUATION-2026-10-22.md`

