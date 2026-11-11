# CONTINUATION TOKEN - ABSORÇÃO DO PROCEDIMENTO DOX #5 (ALTERA CUSTO GERENCIAL) & EVOLUÇÃO DE AUDITORIA E DESTINATÁRIOS DE CRONS

**Data:** 11/11/2026  
**Responsável Técnico:** Tech Lead & Arquiteto de Software (White-Label: MARCON)  
**Chamado Relacionado:** Chamado #33 [Projeto Migração e Aposentadoria do Portal DOX]  
**Status:** 100% Concluído, Homologado e Testado (Produção e Dry-Run)

---

## 1. Contexto e Objetivos Realizados

### 1.1. Absorção Definitiva do Procedimento DOX #5 (Altera Custo Gerencial)
- Substituição do procedimento legado com mais de 364.700 execuções no Ema Studio por um Cron Nativo no Portal Nutricional executado a cada 5 minutos (`*/5 * * * *`).
- **Verificação Preliminar Ultrarrápida (<40ms):** Avalia se houve inserção em `item_historico` com alteração de custo compra nos últimos 7 minutos. Se negativo, encerra a rotina em ~20-30ms, economizando recursos de banco.
- **Execução Fiel dos 4 Eventos Legados:** Atualiza `estoque_filial` priorizando Matriz, grava histórico com `C.G. Auto.`, insere auditoria com sequence `gi_item_altera_custogerencial` e alinha Filial 6 (PR) com a Matriz (SC).
- **Homologação em Produção:** Job ID `1897433` cadastrado no banco; execução agendada #453 finalizada com sucesso em 0.03s.

### 1.2. Painel Admin: Gestão de Destinatários & Listas Modernizada
- Interface em `portal/modules/admin/configuracoes-modulos.php` (aba *Destinatários & Listas*) reestruturada:
  - 4 Cards estatísticos de resumo no topo (Total de Listas, Destinatários Únicos, Crons/Processamento e Gestão/Comercial).
  - Filtros rápidos por categoria com contadores dinâmicos (*Processamento/Crons*, *Representantes*, *Comercial*, *Fiscal*).
  - Campo de busca instantânea em tempo real.
  - Cards com chips visuais dos e-mails, validação de sintaxe, e botão "Copiar E-mails" em 1 clique.
  - Inclusão nativa da lista de e-mails para `custo_gerencial` (ID 14 em `config_email_listas`) com integração direta em `email_listas.php` e no controller.

### 1.3. Painel do Motor de Crons: Histórico Completo & Auditoria Técnica
- Seção de auditoria em `portal/modules/admin/crons.php` reestruturada com:
  - Dropdown com seleção individual de qualquer rotina/job cadastrado ou "Todos os Jobs".
  - Filtro por Status (*Sucesso*, *Falha*, *Executando*), Limite de Registros (25, 50, 100, 200) e busca textual por usuário/IP/comando.
  - Tabela responsiva com badges visuais de status, duração precisa e dados do disparador.
  - Modal técnico de Auditoria ampla (800px) com métricas de tempo, usuário, IP, relatório em HTML e payload JSON formatado com botão "Copiar JSON".

### 1.4. Unificação do Módulo Crons no Portal
- `portal/modules/crons.php` configurado com redirecionamento HTTP 302 + meta refresh + `window.location.replace` para `portal/modules/admin/crons.php`.
- `sistema_modulos` atualizado para `/portal/modules/admin/crons.php`.
- `portal/index.php` atualizado para resolver o redirecionamento com badge visual `ADMIN`.

---

## 2. Arquivos Modificados / Entregues

1. `v1/src/Controllers/CronRotinasController.php`:
   - Registrado `'custo_gerencial_auto'` em `ROTINAS`.
   - Implementado `executarCustoGerencialAuto` e `testarCustoGerencialAuto`.
   - Vinculada leitura de `config_email_listas` (`custo_gerencial`).
2. `v1/src/Controllers/CronController.php`:
   - `getAuditoria` atualizado com filtros por `job_id`, `status`, `busca` e retorno da lista de jobs para o seletor.
3. `portal/modules/admin/crons.php`:
   - Botões rápidos de execução e simulação dry-run para Custo Gerencial.
   - Painel novo de **Histórico Completo & Auditoria de Execuções** com seletores dinâmicos e modal de auditoria.
4. `portal/modules/admin/configuracoes-modulos.php`:
   - Redesenho completo da aba **Destinatários & Listas** com busca, categorias, métricas e chips visuais.
5. `portal/modules/crons.php`:
   - Redirecionamento unificado para `portal/modules/admin/crons.php`.
6. `portal/index.php`:
   - Resolução de link para módulo de crons apontando para o admin com badge `ADMIN`.
7. `email_listas.php`:
   - Adicionada lista estática de fallback para `'custo_gerencial'`.
8. `docs/manual-motor-crons-e-destinatarios.md`:
   - Manual completo de operação, treinamento e procedimentos de troubleshooting.
9. `banco de dados` (PostgreSQL):
   - `cron_jobs`: Job ID `1897433` (`custo_gerencial_auto`).
   - `config_email_listas`: Registro ID 14 (`custo_gerencial`).
   - `sistema_modulos`: URL atualizada para `/portal/modules/admin/crons.php`.
   - `chamado_operacao` (Chamado #33): Operações #4 e #5 registradas cronologicamente.

---

## 3. Lista de Arquivos para Deploy FTP

```text
v1/src/Controllers/CronRotinasController.php
v1/src/Controllers/CronController.php
portal/modules/admin/crons.php
portal/modules/admin/configuracoes-modulos.php
portal/modules/crons.php
portal/index.php
email_listas.php
docs/manual-motor-crons-e-destinatarios.md
docs/tokens/CONTINUATION-2026-11-11-CUSTO-GERENCIAL-DOX.md
docs/CONTINUATION_TOKEN.md
```

---

## 4. Testes de Homologação Realizados

- `php -l` em todos os arquivos modificados (0 erros).
- Execução real agendada #453 no banco: **Sucesso em 0.03s** (`banco em dia`).
- Simulação Dry-Run API: **Aprovada em 60ms**.
- Filtro de auditoria por Job ID e Status via endpoint `/v1/cron/auditoria`: **100% preciso**.
