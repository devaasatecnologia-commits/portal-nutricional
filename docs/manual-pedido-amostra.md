# Manual do Especialista — Pedidos de Amostra & Material de Apoio v3.0

## 1. Visão Geral e Propósito
O módulo de **Material de Apoio e Amostras** (`portal/modules/pedido-amostra.php` e `v1/src/Controllers/AmostraPedidoController.php`) gerencia as solicitações comerciais de amostras físicas, catálogos e brindes de apoio a vendas enviados aos clientes da Nutricional.

---

## 2. Nova Funcionalidade: Visualização de Fotos em Alta Resolução
- **Zoom de Fotos no Catálogo:** Todas as miniaturas de produtos possuem indicação visual de zoom ao passar o mouse.
- **Clique para Ampliar:** Ao clicar na foto de qualquer item do catálogo, abre-se um modal limpo exibindo a foto ampliada com código e descrição do item.
- **Visualização no Histórico:** No modal de detalhes e rastreamento da solicitação, os itens do pedido também possuem miniaturas clicáveis para ampliação da imagem.

---

## 3. Fluxo de Emissão de Solicitações
1. **Cliente:** Busca inteligente por CNPJ, CPF ou Razão Social diretamente na base de clientes ativos do ERP.
2. **Filial de Expedição:** Selecione Matriz (SC) ou Filial (PR). O saldo liberado dos itens é filtrado de acordo com a unidade selecionada.
3. **Tipo de Frete e Endereço:** Validação do endereço cadastrado ou definição de entrega especial.
4. **Seleção de Produtos:** Adição de quantidades com trava de segurança impedindo pedidos acima do saldo livre disponível.
5. **Observações e Envio:** Justificativa comercial da amostra e gravação com status inicial *Pendente*.

---

## 4. Esteira de Rastreamento
- **Pendente:** Aguardando aprovação da supervisão/gerência.
- **Aprovado:** Liberado para separação física no armazém.
- **Separado:** Conferido e embalado pela equipe logística.
- **Faturado:** Nota fiscal de bonificação emitida (CFOP 5.911 / 6.911).
- **Entregue:** Carga recebida pelo cliente.

---

## 5. Treinamento Integrado
O módulo conta com botão direto no cabeçalho: `Treinamento & Manual`, direcionando para a Nutricional Academy (`portal/modules/treinamentos.php?modulo=pedido-amostra`).
