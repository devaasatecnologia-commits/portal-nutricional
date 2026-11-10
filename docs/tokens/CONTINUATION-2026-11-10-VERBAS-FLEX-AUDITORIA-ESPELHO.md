# Token de Continuidade — Verbas Flex: Resolução da Nota 300834 e Aba de Auditoria & Espelho Mercos

**Data:** 10/11/2026  
**Módulo:** Gestão de Verbas Flex (`portal/modules/verbas-flex.php`) & Cron de Bonificações (`v1/src/Controllers/CronRotinasController.php`)  
**Status:** Concluído e Validado em Produção Local  

---

## 1. Resumo das Implementações e Correções

### 1.1 Resolução da Falha da Nota 300834 & Resiliência
- **Causa Raiz:** O motor externo concorrente do ERP antigo tentou injetar saldo de crédito simultaneamente durante o processamento noturno, causando discrepância na checagem rígida do saldo final em `movimentarFlexSeguro`.
- **Correção:**
  - Ajustado `movimentarFlexSeguro` em `CronRotinasController.php` para validar o retorno HTTP 200 da API oficial Mercos sem quebrar por concorrência simultânea externa.
  - Reprocessada com sucesso a nota fiscal **300834** (Welliton Cardoso Ramos - Hospital Veterinário Maria Schmitt, R$ 363,09), com cobertura integral pelo gestor Tiago (MOV) e gravação em `log_flex_bonificacao` (ID 2153).
  - A view `verificabonificacao` agora está 100% limpa (0 pendências).

### 1.2 Nova Aba: Auditoria & Espelho Completo Mercos no Portal
- **Localização:** Módulo **`verbas-flex.php`**, preservando a hierarquia estrita de permissões do usuário logado (`UserScope`).
- **Capacidades:**
  - **Filtros:** Filial, Colaborador/Representante (com gestores inclusos), Origem (Todas, Bonificações, Transferências, Manuais, Flex Mínimo), Natureza (Créditos/Débitos) e Busca textual.
  - **Cards Totalizadores:** Total de Créditos (+), Total de Débitos (-), Balanço Líquido e Total de Lançamentos rastreados.
  - **Espelho Consolidado:** Tabela cronológica unificada com Data/Hora, Origem, Operador responsável (ex: `CRON SISTEMA`, `ROBSON`, `TIAGO`, etc.), Colaborador, Tipo, Valor, Saldo Antes ➔ Depois, Gestor de Cobertura e Documento/Motivo.
  - **Modal de Detalhes:** Inspeção de payloads JSON, metadados técnicos e notas associadas.
  - **Exportação Excel:** Download de extrato em `.csv` (UTF-8 com BOM).

### 1.3 Correção Arquitetural do Campo `valor` vs `valor_tirado` (Caso Anaildo Pereira)
- **Diagnóstico da Causa Raiz:** Na tabela `log_flex_bonificacao`, a coluna `valor` contém o valor integral da bonificação descontada do representante (ex: -247.90, -155.44, etc.), enquanto `valor_tirado` armazena exclusivamente o montante coberto pelo **gestor**. Quando o representante possui saldo suficiente (débito direto, como o Anaildo Pereira com R$ 965,31 de saldo), `valor_tirado` é gravado como `0.00` (pois zero foi tirado do gestor).
- **Correção em `VerbasFlexController.php`:**
  - Ajustado para selecionar `ABS(lfb.valor) AS valor` (e não `lfb.valor_tirado`) em todas as agregações, rankings, estatísticas e na auditoria espelho.
  - Ajustado `usou_gestor` para `(b.valor_tirado IS NOT NULL AND ABS(b.valor_tirado) > 0)` — eliminando a exibição indevida do badge de cobertura do gestor quando o colaborador pagou com recursos próprios.
  - Validado no Anaildo Pereira: 6 bonificações somando **R$ 776,14** agora exibidas com precisão cirúrgica no extrato.

---

## 2. Arquivos Modificados para Deploy FTP

1. `v1/src/Controllers/CronRotinasController.php`
2. `v1/src/Controllers/VerbasFlexController.php`
3. `v1/routes/api.php`
4. `portal/modules/verbas-flex.php`
5. `portal/assets/js/verbas-flex.js`
