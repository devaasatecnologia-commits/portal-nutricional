# Manual do Especialista — Cubo OLAP Multidimensional de Vendas v3.0

## 1. Visão Geral e Propósito
O **Cubo de Vendas** (`portal/modules/cubo-vendas.php` e `v1/src/Controllers/VendasCuboController.php`) é o motor de inteligência e auditoria multidimensional (OLAP) da Nutricional, proporcionando relatórios customizados com velocidade e fidelidade fiscal estrita.

---

## 2. As Três Dimensões Fiscais e Quantitativas
O Cubo processa simultaneamente:
1. **Faturamento Líquido (R$):** Valor faturado com impostos (IPI, ST), frete, seguro e despesas acessórias idênticas ao faturamento oficial do ERP.
2. **Peso Líquido Total (Kg / Ton):** Apuração física real baseada no cadastro oficial de itens.
3. **Volume de Itens e Peças (Un):** Contagem consolidada de unidades comercializadas.
4. **Preço Médio por Quilo (R$/Kg):** Indicador de rentabilidade apurado dividindo o Faturamento pelo Peso Líquido.

---

## 3. Matriz de Dimensões Disponíveis
- **Comercial:** Filial, Gestor Responsável e Representante Comercial.
- **Geográfica:** 7 Macro-Regiões Oficiais (RMC, AVI, GRF, NOR, PLN, SUL e RED), UF e Município.
- **Produtos:** Grupo de Produtos, Família, Marca e Código do Item.
- **Clientes:** Razão Social, Nome Fantasia e CNPJ.

---

## 4. Recursos de Usabilidade para Gestores
- **Botões de Período Rápido:** *Hoje, Este Mês, Mês Passado, Últimos 30 Dias, Este Ano*.
- **Personalizador de Métricas:** Selecione quais colunas exibir no relatório.
- **Exportação CSV:** Download da grade formatada para Excel em um clique.
- **Treinamento & Manual Integrado:** Atalho no topo para consulta rápida no Nutricional Academy (`portal/modules/treinamentos.php?modulo=cubo_vendas`).
