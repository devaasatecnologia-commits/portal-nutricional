# Token de Continuação — Cubo de Vendas (Tooltips, Rastreio nos Cards, Top Clientes e Alinhamento Modal) & Resolução Definitiva de Fotos e Menus

**Data:** 04/11/2026  
**Status:** Concluído e Validado  

---

## 1. Escopo das Implementações Realizadas

### A. Hierarquia de Acesso & Segurança Estrita
- **`v1/src/Utils/UserScope.php`**: Usuários não-admin agora são restritos por padrão a seus próprios dados (`idcliforemp` ou `[-1]`), impedindo qualquer vazamento inadvertido de dados corporativos ou de outras filiais/gestores.
- **`v1/src/Controllers/VendasCuboController.php`**: Endpoints de listagem de filiais e gestores agora aplicam a verificação de escopo estrito, e `getDetalhes()` suporta o drill-down global e por tipo (`todos`).

### B. Módulo Cubo de Vendas (`cubo-vendas.php` e `cubo-vendas.js`)
- **Agrupamento Padrão**: Filtro inicial configurado por padrão para **Tipo (Pedido/NF/Devolução)** (`tipo_documento`).
- **Tooltips Modernos Flutuantes**: Implementação do Rich Hover Popover (`#modernTooltipCubo`) em todos os cards de KPIs, filtros e itens do ranking, com categorização dinâmica por cores (Vendas, Devolução, Comissão, Filtro).
- **Rastreio nos Cards (Drill-Down)**: Cards de Hero KPIs (Comissão Ajustada, Valor Líquido, Devoluções) e operacionais agora contam com cliques interativos (`@click="abrirRastreioCard('...')"`), abrindo os documentos e itens detalhados correspondentes.
- **Top 10 Clientes**: Tipografia reduzida (`text-[11px] sm:text-xs font-bold`) e remoção do limite rígido de largura para exibir o nome do cliente completo com elegância.
- **Modal de Detalhes / Devoluções**: Reestruturação da tabela eliminando a `<table>` aninhada desalinhada e utilizando elementos `<tbody>` compartilhados por item, garantindo alinhamento milimétrico entre o cabeçalho (`DOCUMENTO`, `CLIENTE`, `VALOR`, `COMISSÃO`, `QTD`, `PESO`, `TIPO`, `ITENS`) e as linhas de dados.

### C. Resolução Definitiva de Fotos e Uploads em Localhost/Produção
- **`index.php`**: Interceptação aprimorada de requisições de arquivos estáticos em `uploads/` (mesmo quando chamadas via `?api_route=/uploads/...` ou com parâmetros de cache `?t=...`), servindo o binário diretamente com o MIME correto e cache.
- **`v1/src/Controllers/AdminController.php`**: Método `uploadFotoPerfil` agora detecta o host local dinamicamente (`localhost:8080`), evitando retornar URLs externas que geravam 404 localmente.
- **`portal/modules/admin/components/sidebar.php`**: Resolução de fotos adaptada tanto para localhost quanto para produção.

### D. Menu do C.H.A.P.D (`avaliacao-chapd.php`)
- **Eliminação de Barras de Rolagem**: O container de abas (`.ch-tabs`) foi refatorado para CSS Grid responsivo (`grid-cols-2 sm:grid-cols-3 lg:grid-cols-6`), acomodando os 6 botões perfeitamente sem gerar barras de rolagem horizontais em nenhuma resolução.

---

## 2. Arquivos para Deploy FTP

1. `v1/src/Utils/UserScope.php`
2. `v1/src/Controllers/VendasCuboController.php`
3. `v1/src/Controllers/AdminController.php`
4. `index.php`
5. `portal/modules/cubo-vendas.php`
6. `portal/assets/js/cubo-vendas.js`
7. `portal/modules/avaliacao-chapd.php`
8. `portal/modules/admin/components/sidebar.php`

