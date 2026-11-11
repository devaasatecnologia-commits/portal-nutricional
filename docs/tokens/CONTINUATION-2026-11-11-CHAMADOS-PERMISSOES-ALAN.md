# CONTINUATION TOKEN - REDIRECIONAMENTO DE CHAMADOS PARA ALAN, PERMISSÕES GRANULARES E VISÃO DEDICADA

**Data:** 11/11/2026  
**Responsável Técnico:** Tech Lead & Arquiteto de Software (White-Label: MARCON / ALAN)  
**Chamado Relacionado:** Chamado #35 [EVOLUÇÃO - Permissões Granulares & Central de Chamados]  
**Status:** 100% Concluído, Homologado e Testado em Produção

---

## 1. Contexto e Objetivos Entregues

1. **Redirecionamento Automático para o Técnico (ALAN):**
   - Substituída a string estática legada `'DEVAASA'` no backend (`ChamadosController::criar`).
   - Todo chamado criado no Portal agora é atribuído automaticamente para **ALAN** com `status = 0` (Aberto), caindo instantaneamente na fila dele para triagem e solução.
   - Criada a rota `POST /v1/chamados/assumir` permitindo ao técnico assumir a condução do chamado com 1 clique (atualizando `atendente = ALAN` e movendo status de 0 para 1 - Em Análise, registrando a operação na timeline).

2. **Visão Dedicada e Acolhedora para o Usuário Solicitante:**
   - Para usuários sem privilégios de gestor (`!usuario.pode_ver_todos`):
     - Eliminada a poluição do Kanban corporativo e métricas globais.
     - Inserido banner de acolhimento com atalho em destaque para **Abrir Novo Chamado**.
     - Seção limpa **"Meus Chamados & Acompanhamento"** em formato de cards modernos com indicador de status bem legível, responsável técnico visível (ALAN) e botão "Acompanhar Andamento & Mensagens".
     - Empty state com ilustração orientativa quando não houver chamados abertos.

3. **Painel de Service Desk para o Administrador (ALAN):**
   - Central completa com Kanban de 4 estágios, Modo Tabela e Painel Executivo.
   - Filtro rápido na barra superior: **"Todos"**, **"Atribuídos a Mim (ALAN)"** e **"Sem Atendente"**.
   - Botão rápido "Assumir" disponível diretamente nos cards do Kanban, na tabela de listagem e no modal de detalhes.

4. **Filtragem Estrita de Módulos por Permissão (`usuario_permissoes`):**
   - O backend agora consulta diretamente a tabela `usuario_permissoes` vinculada a `sistema_modulos`.
   - No formulário de abertura, se o usuário selecionar **Portal Nutricional**, são listados **exclusivamente os módulos aos quais ele tem acesso concedido**.
   - Validação defensiva no backend: caso tente submeter módulo bloqueado, a API rejeita com erro 400.
   - Respeita o campo `chamados_tipos_permitidos` e `chamados_ver_todos` configurados no painel Admin de Permissões.

---

## 2. Arquivos Modificados para Deploy FTP

```text
v1/src/Controllers/ChamadosController.php
v1/src/Controllers/CronRotinasController.php
v1/routes/api.php
portal/assets/js/chamados.js
portal/modules/chamados.php
portal/modules/admin/crons.php
portal/modules/admin/components/header.php
portal/modules/admin/components/footer.php
docs/tokens/CONTINUATION-2026-11-11-CHAMADOS-PERMISSOES-ALAN.md
docs/CONTINUATION_TOKEN.md
```

---

## 4. Chamado #36 (TALES - Gestor Filial 6) [CONCLUÍDO]

- **Relato Original:** Usuário da Filial 6 necessitava iniciar com a filial 6 pré-selecionada no Verbas Flex e nos chamados, ver apenas seus módulos liberados e ter permissão de abrir chamados para todos os sistemas (Portal, Suporte Comercial, Mercos, Relatórios, Email, etc.).
- **Ações Técnicas Realizadas:**
  1. Chamado #36 assumido formalmente pelo técnico **ALAN** (`status = 1 -> 3`).
  2. Atualizado no banco `usuario.chamados_tipos_permitidos = 'TODOS'` para o usuário TALES (`idusuario = 19`), equalizando com os demais gestores da empresa.
  3. `ChamadosController.php`: injetada a detecção de `idfilial` padrão a partir de `dash_filiais` e adicionados os sistemas `SUPORTE_COMERCIAL` e `RELATORIOS`.
  4. `portal/assets/js/chamados.js`: no `abrirModalNovo()`, inicializa o chamado com a filial nativa do usuário (`usuario.idfilial = 6`).
  5. `VerbasFlexController.php`: endpoint `/contexto` retorna `filial_padrao = 6` e `filtros()` assume filial 6 se não informada.
  6. `portal/assets/js/verbas-flex.js`: carrega e pré-seleciona a filial padrão do usuário logado (Filial 6) com persistência em `localStorage`.
  7. Registrada entrega técnica formal e checklist de 5 passos na timeline de `chamado_operacao` (Operação #3).

---

## 5. Arquivos Modificados para Deploy FTP

| Arquivo Local | Destino Servidor / FTP | Motivo da Alteração |
|---|---|---|
| `portal/modules/admin/crons.php` | `portal/modules/admin/crons.php` | Proteção contra `null` em `innerHTML`/`textContent` no dashboard de crons e auditoria |
| `portal/modules/admin/components/header.php` | `portal/modules/admin/components/header.php` | Normalização de query parameters na montagem da URL em `apiFetch` |
| `portal/modules/admin/components/footer.php` | `portal/modules/admin/components/footer.php` | Normalização de query parameters na montagem da URL em `apiFetch` |
| `portal/modules/chamados.php` | `portal/modules/chamados.php` | Select dinâmico de filial via template Alpine; só exibe filiais liberadas do usuário (Tales só Filial 6) |
| `portal/assets/js/chamados.js` | `portal/assets/js/chamados.js` | Estado reativo de filiais liberadas e inicialização com a filial permitida |
| `portal/assets/js/verbas-flex.js` | `portal/assets/js/verbas-flex.js` | Inicialização automática da filial nativa (Filial 6) com persistência |
| `v1/src/Controllers/ChamadosController.php` | `v1/src/Controllers/ChamadosController.php` | Endpoint `/dashboard` retorna `filiais` filtradas por `dash_filiais` e validação estrita em `/criar` |
| `v1/src/Controllers/VerbasFlexController.php` | `v1/src/Controllers/VerbasFlexController.php` | `filial_padrao` no contexto e fallback automático para filial do usuário |
| `v1/src/Controllers/CronRotinasController.php` | `v1/src/Controllers/CronRotinasController.php` | Implementação do comando `custo_gerencial_auto` e simulador Dry-Run |
| `v1/routes/api.php` | `v1/routes/api.php` | Rota POST `/v1/chamados/assumir` |

---

## 3. Homologação e Testes Realizados

- **Sintaxe PHP:** `php -l` em todos os arquivos alterados (0 erros).
- **Dashboard ALAN vs TIAGO:**
  - ALAN: 42 módulos, 11 sistemas, fila geral completa com 36 chamados.
  - TIAGO: Exatamente os 8 módulos liberados em `usuario_permissoes`, visão restrita aos seus próprios chamados.
- **Validação de Bloqueio:** Tentativa de submissão de módulo não permitido rejeitada com `"Você não possui permissão no módulo '...' do Portal."`.
- **Criação e Direcionamento:** Chamado criado com atendente `ALAN` e status `0`.
- **Assumir Chamado:** Rota `/v1/chamados/assumir` testada com sucesso (moveu para status 1 e gravou timeline).
- **Timeline Chamado #35:** Operação #3 registrada no banco com sucesso.
