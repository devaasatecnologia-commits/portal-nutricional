# Token de Continuidade — Avaliação C.H.A.P.D & Nutricional Academy & Correções Gerais

**Data:** 30/10/2026  
**Branch:** `main`  
**Autor:** Antigravity Tech Lead  

---

## 1. O que foi feito nesta sessão

1. **Carga Completa de Treinamentos para 100% dos Módulos (38 Manuais Oficiais):**
   - Criados e homologados na tabela `sistema_manuais` os guias oficiais completos para todos os 37 módulos ativos do ERP mais alias `transferencia-compra`.
   - Cobertura completa de todos os departamentos: **Logística** (Separação, Carregamento, Monitor, Desembarque, Depósito, Auditoria, Previsão, Inventário, Transferências Matriz-Filial e Filial-Matriz), **Frota** (Gestão, Embarques, Retorno, Acerto, Motorista Offline, Cadastro), **Financeiro** (Painel Financeiro, Análise de Carteira), **Faturamento** (Consulta de Saldo, Conferência XML), **Análise de Vendas** (Cubo OLAP, Dashboard Executivo), **Comercial / Mercos** (Movimentação Flex, Verbas Flex, Transferência Flex, Amostras), **Marketing** (Central, Clientes/CRM, Metas Foco, Relatórios, Admin, Dashboard, Alimentação PDV), **Recursos Humanos** (C.H.A.P.D, Nutricional Academy), **TI & Infraestrutura** (Crons, Admin do Sistema).
   - Todos os textos gravados com acentuação estrita UTF-8 sem erros de conversão WIN1252.

2. **Controle Estrito de Permissões por Usuário na Central de Treinamentos:**
   - [`TreinamentoController.php`](file:///c:/xampp/htdocs/API/v1/src/Controllers/TreinamentoController.php):
     - `listar()`: Administradores (`is_admin = true` ou perfil `admin`) visualizam **todos** os 38 manuais cadastrados. Usuários comuns visualizam **exclusivamente os manuais dos módulos autorizados** para seu usuário em `usuario_permissoes`.
     - `obterPorSlug()`: Se um usuário comum tentar acessar diretamente o manual de um módulo que não possui permissão, o backend retorna `HTTP 403 Forbidden` com `bloqueado: true` e mensagem explicativa.
     - `salvar()`: Apenas administradores master podem editar ou atualizar manuais.

3. **Layout Premium "Nutricional Academy" & Experiência de Especialista:**
   - [`portal/modules/treinamentos.php`](file:///c:/xampp/htdocs/API/portal/modules/treinamentos.php) e [`portal/assets/js/treinamentos.js`](file:///c:/xampp/htdocs/API/portal/assets/js/treinamentos.js):
     - Hero header com visual escuro gradiente esmeralda, badges translúcidos e campo de busca rápida com filtro em tempo real por título, fórmula, botão ou regra.
     - Pílulas dinâmicas de filtro rápido por setor no topo da barra lateral (Todos, Logística, Frota, Financeiro, Vendas, Comercial, RH, TI).
     - Leitor estruturado em 5 seções ricas:
       1. **Objetivo & Visão Geral:** Finalidade, público-alvo e governança.
       2. **Dicionário de KPIs:** Cards com fórmulas técnicas em fonte monospace, metas e significados.
       3. **Ações & Botões:** Badges coloridos simulando os botões reais da tela com passo a passo.
       4. **Relatórios & Tabelas:** Guia de colunas, filtros e exportações.
       5. **Regras de Negócio & FAQ:** Travas, condutas e perguntas frequentes.
     - Badge inteligente com estimativa de tempo de leitura (ex: "⏱ 4 min de leitura") e status de autorização do perfil.
     - Tela de bloqueio elegante (*Acesso Restrito*) caso o usuário tente carregar um manual não autorizado por URL direta.
     - Modo de impressão limpo com atalho para Acessar o Módulo com um clique.

4. **Tratamento Amigável de Acesso Restrito no Módulo C.H.A.P.D:**
   - [`portal/assets/js/avaliacao-chapd.js`](file:///c:/xampp/htdocs/API/portal/assets/js/avaliacao-chapd.js):
     - Corrigida mensagem genérica de erro que exibia *"Erro de Conexão"* quando um operador comum sem permissão abria o módulo. Agora exibe pop-up de aviso formal: *"Acesso Restrito: Você não possui permissão para acessar a Avaliação de Desempenho C.H.A.P.D. Solicite liberação ao administrador."*

5. **Transferência Matriz → Filial & Notas Canceladas:**
   - Validação da NF #179 cancelada no ERP desconsiderada do faturamento e da Guia ST (fixando o valor correto de R$ 11.751,16 sem duplicações).
   - Ocultação padrão de embarques já solicitados no grid inicial.

---

## 2. Arquivos para Deploy FTP

```text
v1/src/Controllers/CronRotinasController.php
v1/src/Controllers/TreinamentoController.php
v1/src/Controllers/AvaliacaoChapdController.php
v1/src/Controllers/TransferenciaCompraController.php
v1/src/Controllers/SistemaController.php
v1/src/Middleware/ModulePermissionMiddleware.php
v1/routes/api.php
portal/index.php
portal/modules/treinamentos.php
portal/modules/avaliacao-chapd.php
portal/modules/transferencia-compra.php
portal/modules/movimentacao-flex.php
portal/assets/js/treinamentos.js
portal/assets/js/avaliacao-chapd.js
portal/assets/js/transferencia-compra.js
```
