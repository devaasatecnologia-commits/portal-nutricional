# Token de Continuidade — Modernização CRM Clientes & Hierarquia por Filial/Gestor

**Data:** 23/10/2026  
**Status:** CONCLUÍDO COM SUCESSO  
**Módulos Impactados:** CRM Clientes (`portal/modules/marketing/clientes.php`), API Marketing (`v1/src/Controllers/MarketingController.php`, `v1/routes/api.php`), Banco de Dados PostgreSQL (`consultar_clientes_otimizado_v2`).

---

## 1. O que foi Implementado

### A. Backend & Banco de Dados (PostgreSQL + Slim 4)
1. **Filtro Estrito de Clientes Ativos do ERP:**
   - Adicionada a restrição `AND (v.id_erp IS NULL OR COALESCE(e.inativo, 'N') <> 'S')` na função PostgreSQL.
   - Clientes inativados no ERP são automaticamente excluídos das consultas do CRM.
2. **Filtro por Status de Pedidos (Pré-carregado como Padrão):**
   - Parâmetro `p_status_pedido` com valor default `'abertos_ou_faturados'` (`status IN (1, 5)` no ERP: 1 = Em Aberto, 5 = Faturado).
   - Suporte adicional aos filtros: `'abertos'`, `'faturados'`, `'sem_pedidos'` e `'todos'`.
3. **Retorno Enriquecido:**
   - `status_erp`: 'Ativo' ou 'Inativo'.
   - `qtd_pedidos_abertos`: Contagem de pedidos com `status = 1`.
   - `qtd_pedidos_faturados`: Contagem de pedidos com `status = 5`.
   - `idfilial` e `nome_filial` (`SC (Filial 1)` ou `PR (Filial 6)`).
   - `idvendedor` e `nome_vendedor`.
   - `idgestor` e `nome_gestor`.
4. **Controle de Escopo com `UserScope`:**
   - Usuário restrito opera apenas nas filiais liberadas em `usuario.dash_filiais` e gestores liberados em `usuario.dash_gestores`.
   - Endpoints auxiliares criados: `/v1/marketing/filiais`, `/v1/marketing/gestores` e `/v1/marketing/representantes`.

### B. Frontend CRM Clientes (`portal/modules/marketing/clientes.php`)
1. **Barra de Hierarquia & Escopo (Grid 4 Colunas):**
   - Seletor de **Filial** (Filial 1 - SC, Filial 6 - PR ou Todas).
   - Seletor de **Gestor Responsável** (carregado dinamicamente).
   - Seletor de **Representante Comercial** (carregado dinamicamente).
   - Seletor de **Status Pedidos** pré-selecionado com **"📦 Abertos ou Faturados (Padrão)"**.
2. **Badges e Feedback Visual:**
   - Badge `ERP: Ativo` ao lado do nome do cliente.
   - Badges de pedidos em aberto (`📦 X Aberto(s)`) e faturados (`✅ Y Faturado(s)`) na coluna de status de compra.
   - Badges de filial (`SC` / `PR`) e dados de Gestor/Representante destacados.

---

## 2. Arquivos para Deploy FTP
- `v1/src/Controllers/MarketingController.php`
- `v1/routes/api.php`
- `portal/modules/marketing/clientes.php`
- Executar no PostgreSQL de produção: `docs/sql/consultar_clientes_otimizado_v2.sql`
