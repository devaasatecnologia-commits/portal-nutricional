# Manual do Especialista & Treinamento: Central de Chamados & Suporte Inteligente com IA v1.0

**Módulo:** Central de Chamados & Suporte Inteligente (`chamados`)  
**Versão:** 1.0 — Novembro/2026  
**Setor:** TI & Governança Corporativa  
**Público-Alvo:** Todos os Colaboradores, Gestores de Setor, Suporte Técnico e Desenvolvedores  

---

## 1. Visão Geral e Propósito

A **Central de Chamados & Suporte Inteligente** (`portal/modules/chamados.php`) é a central oficial de governança, resolução de problemas e solicitações de melhorias do ecossistema Nutricional.

Integrada diretamente às tabelas nativas `chamado` e `chamado_operacao` do ERP, a ferramenta foi projetada para eliminar o uso de papéis, mensagens soltas de WhatsApp ou planilhas descentralizadas, unificando todo o ciclo de vida dos chamados em um fluxo auditado e transparente.

---

## 2. O Diferencial: Triagem Assistida por Inteligência Artificial (IA)

O grande diferencial do módulo é o motor de **IA Triagem**, que atua no exato momento da abertura do chamado:

1. **Auto-Formatação de Título:** Transforma relatos informais em títulos objetivos e padronizados no formato `[Nome do Módulo] Descrição do Problema`.
2. **Reconhecimento Automático do Módulo:** Analisa o vocabulário do usuário e vincula a ocorrência diretamente a um dos 40 módulos oficiais cadastrados no Portal (ex: `Verbas Flex`, `Financeiro`, `Separação WMS`, `Cubo Vendas`).
3. **Classificação de Criticidade:**
   - 🔴 **Crítica:** Situações que paralisam o faturamento, emissão de notas ou expedição de cargas.
   - 🟡 **Alta:** Divergências de cálculos ou falhas com contorno operacional.
   - 🔵 **Média:** Ajustes de layout, melhorias de filtros ou usabilidade.
   - 🟢 **Baixa:** Dúvidas operacionais ou ideias para versões futuras.
4. **Geração do Dossiê Técnico de Engenharia:**
   - Um parecer técnico preliminar gerado automaticamente para os desenvolvedores e para o assistente Antigravity, apontando as **tabelas prováveis do ERP**, **arquivos envolvidos no backend/frontend** e **sugestões de ação corretiva**.

---

## 3. Modos de Visualização: Kanban & Lista

O módulo oferece dois formatos de visualização complementares:

### 3.1 Quadro Kanban Interativo
Organizado em 4 colunas com contadores em tempo real:
* **Abertos (Status 0):** Fila de novas solicitações aguardando triagem.
* **Em Análise (Status 1):** Chamados sob investigação técnica da equipe.
* **Em Correção (Status 2):** Hotfixes ou desenvolvimentos em andamento.
* **Concluídos (Status 3):** Problemas resolvidos e validados em produção.

### 3.2 Tabela Lista Executiva
Visão tabular com ordenação inteligente por criticidade (críticos e urgentes sempre no topo), buscas textuais e filtros por módulo do sistema.

---

## 4. Como Abrir um Chamado com IA Passo a Passo

1. Clique no botão **`+ Abrir Chamado`** no topo da tela.
2. (Opcional) Selecione o módulo ou deixe para a IA detectar.
3. Descreva o que aconteceu no campo de texto com suas próprias palavras.
4. Clique no botão **`🤖 Analisar com IA`**.
5. A IA preencherá o título otimizado, a criticidade e o parecer técnico.
6. Clique em **`Confirmar & Abrir Chamado`**.

---

## 5. Como Resolver e Concluir um Chamado

Ao abrir os detalhes de um chamado:
1. Analise o relato do usuário e o **Dossiê Técnico da IA**.
2. Adicione comentários na linha do tempo para manter o solicitante informado.
3. Ao concluir a correção:
   - Marque a caixa de seleção **`Marcar como Concluído / Resolvido`**.
   - Informe a versão de entrega (ex: `v3.5`).
   - Descreva a solução técnica aplicada (ex: commits, arquivos alterados).
   - Clique em **`Registrar`**. O chamado é movido automaticamente para a coluna de Concluídos.

---

## 6. Arquivos do Módulo e Deploy FTP

1. `portal/modules/chamados.php` — Interface visual (Kanban, lista e modais).
2. `portal/assets/js/chamados.js` — Lógica reativa Alpine.js e chamadas de API.
3. `v1/src/Controllers/ChamadosController.php` — Endpoints REST com suporte a Postgres WIN1252.
4. `v1/src/Services/ChamadosIaService.php` — Motor de IA para triagem e dossiê técnico.
5. `v1/routes/api.php` — Mapeamento das rotas do grupo `/v1/chamados`.
6. `docs/manual-chamados.md` — Documentação e manual operacional.
