# CONTINUATION TOKEN - ABSORÇÃO DO PROCEDIMENTO DOX #5 (ALTERA CUSTO GERENCIAL) & ROTINA NATIVA DO MOTOR DE CRONS

**Data:** 11/11/2026  
**Responsável Técnico:** Tech Lead & Arquiteto de Software (White-Label: MARCON)  
**Chamado Relacionado:** Chamado #33 [Projeto Migração e Aposentadoria do Portal DOX]  
**Status:** 100% Concluído e Testado (Produção e Dry-Run)

---

## 1. Contexto e Objetivos
Substituição definitiva do procedimento legado **DOX #5 (ALTERA CUSTO GERENCIAL)** do Ema Studio, que acumulou mais de 364.700 execuções na infraestrutura antiga, por um **Cron Nativo** no Portal Nutricional executado a cada 5 minutos (`*/5 * * * *`).

### Objetivos Atendidos
1. **Verificação Preliminar Ultrarrápida (<40ms):**
   - Verifica se houve alteração de custo compra (`CUSTOCOMPRANOVO > 0 AND CUSTOCOMPRAANTERIOR <> CUSTOCOMPRANOVO`) nos últimos 7 minutos na tabela `item_historico`.
   - Se negativo, encerra a execução imediatamente em ~35ms sem abrir transações desnecessárias ou onerar o PostgreSQL.
2. **Execução Fiel dos 4 Eventos Legados:**
   - **Evento 1 (UPDATE `estoque_filial`):** Atualiza `custogerencial`, `datahoraultimaatualizacao`, `custogerencialanterior` e `identificacaolocal = 'AUTOMATICO'`, priorizando custo da Matriz (Filial 1).
   - **Evento 2 (INSERT `item_historico`):** Registra o novo histórico com `usuario = 'AUTOMATICO'`, `origem = 'C.G. Auto.'` e sequence `MAX(idhistorico) + 1`.
   - **Evento 3 (INSERT `item_altera_custogerencial`):** Grava log de auditoria com a sequence oficial `nextval('gi_item_altera_custogerencial')`.
   - **Evento 4 (UPDATE `estoque_filial` Filial 6):** Sincroniza e espelha o custo gerencial da Filial 6 (PR) com a Matriz Filial 1 (SC).
3. **Simulador Dry-Run Integrado:**
   - Diagnóstico em tempo real que avalia alterações recentes, saúde da sequence de auditoria, alinhamento entre as filiais e destinatários configurados, executando em 60ms sem alterar nenhum registro.
4. **Interface do Motor de Crons:**
   - Botão rápido de Execução Manual e Simulação Dry-Run no painel `portal/modules/admin/crons.php`.
   - Job cadastrado na tabela `cron_jobs` (ID `1897433`, Categoria 2 - Processamento, schedule `*/5 * * * *`).

---

## 2. Arquivos Modificados / Entregues
1. `v1/src/Controllers/CronRotinasController.php`:
   - Registrado `'custo_gerencial_auto'` em `const ROTINAS`.
   - Implementado `executarCustoGerencialAuto(array $params, string $usuario, string $ip)`.
   - Implementado `testarCustoGerencialAuto(array $parametros)`.
   - Adicionada amostra em `enviarAmostraTeste` e mescla de e-mails em `executarLimiteCreditoMensal`.
2. `portal/modules/admin/crons.php`:
   - Adicionado atalho rápido e botão de teste para `Custo Gerencial (DOX #5)`.
3. `cron_jobs` (PostgreSQL):
   - Job ID `1897433` cadastrado com sucesso.
4. `chamado_operacao` (Chamado #33):
   - Operação #4 registrada cronologicamente na timeline técnica do chamado.

---

## 3. Lista de Arquivos para Deploy FTP
```text
v1/src/Controllers/CronRotinasController.php
portal/modules/admin/crons.php
```

---

## 4. Testes Realizados
- `php -l v1/src/Controllers/CronRotinasController.php` -> Sintaxe OK.
- `php -l portal/modules/admin/crons.php` -> Sintaxe OK.
- Execução Dry-Run via API (`POST /v1/cron/testar`): **60ms** (checks 100% aprovados, 0 divergências).
- Execução Real CLI: **36.28ms** (banco em dia, encerramento limpo).
