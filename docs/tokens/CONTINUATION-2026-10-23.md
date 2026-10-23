# Token de Continuidade — Modernização CRM Clientes & Hierarquia por Filial/Gestor

**Data:** 23/10/2026  
**Status:** CONCLUÍDO COM SUCESSO  
**Módulos Impactados:** CRM Clientes (`portal/modules/marketing/clientes.php`), API Marketing (`v1/src/Controllers/MarketingController.php`, `v1/routes/api.php`), Banco de Dados PostgreSQL (`consultar_clientes_otimizado_v2`).

---

## 1. O que foi Implementado

### A. Backend & Banco de Dados (PostgreSQL + Slim 4)
1. **Função SQL `consultar_clientes_otimizado_v2` Estendida:**
   - Adicionado suporte nativo a filtros de **Filial** (`p_filiais`), **Gestor** (`p_gestores`) e **Representante Comercial** (`p_vendedor`).
   - Retorno JSON enriquecido com dados de vínculo real do ERP/CRM:
     - `idfilial`: `COALESCE(NULLIF(e.idfilial, 0), um.filial, 1)`
     - `nome_filial`: `SC (Filial 1)` ou `PR (Filial 6)`
     - `idvendedor` e `nome_vendedor`
     - `idgestor` e `nome_gestor`
   - Script SQL documentado e salvo em [`docs/sql/consultar_clientes_otimizado_v2.sql`](file:///c:/xampp/htdocs/API/docs/sql/consultar_clientes_otimizado_v2.sql).
2. **Controle de Escopo com `UserScope`:**
   - Integrado o `Nutricional\Utils\UserScope` em `MarketingController::consultarClientesUnificadoOtimizado`.
   - Se o usuário possui `dash_filiais` restrito, apenas clientes da sua filial são exibidos e filtrados.
   - Se o usuário possui `dash_gestores` restrito (Gestores), apenas os clientes associados aos seus representantes/gestão são acessíveis.
   - Administradores têm visão irrestrita (Todas as Filiais / Todos os Gestores).
3. **Novos Endpoints Auxiliares no Slim:**
   - `GET /v1/marketing/filiais`: Retorna filiais liberadas para o usuário respeitando o escopo.
   - `GET /v1/marketing/gestores`: Retorna gestores liberados (com filtro opcional por filial).
   - `GET /v1/marketing/representantes`: Retorna representantes comerciais vinculados à filial e gestor.

### B. Frontend CRM Clientes (`portal/modules/marketing/clientes.php`)
1. **Barra de Hierarquia & Escopo no Topo dos Filtros:**
   - Dropdown de **Filial** com badge de restrição quando o usuário tem escopo limitado.
   - Dropdown de **Gestor Responsável** carregado dinamicamente com base na filial selecionada.
   - Dropdown de **Representante Comercial** atualizado reativamente por filial/gestor.
2. **Aprimoramento Visual e Experiência do Usuário (UX/UI):**
   - Badges visuais em cada linha de cliente identificando a Filial com destaque (`SC (Filial 1)` em verde esmeralda e `PR (Filial 6)` em azul celeste).
   - Exibição elegante do Gestor Responsável (`fa-user-tie`) e Representante Comercial (`fa-id-badge`) abaixo da razão social/telefone do cliente.
   - Preservadas e integradas 100% das funcionalidades legadas: Agenda de Compromissos, Notificações, Modal de Detalhes, Edição CRM, Pedidos ERP e Importação.

---

## 2. Arquivos para Deploy FTP
- `v1/src/Controllers/MarketingController.php`
- `v1/routes/api.php`
- `portal/modules/marketing/clientes.php`
- Executar no PostgreSQL de produção: `docs/sql/consultar_clientes_otimizado_v2.sql`
