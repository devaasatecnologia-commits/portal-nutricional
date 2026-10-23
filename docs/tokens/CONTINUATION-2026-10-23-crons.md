# Token de Continuidade — Modernização dos Crons (Slim 4) & Handoff para Segunda-feira

**Data:** 23/10/2026 (Fechamento Sexta-feira)  
**Status:** CONCLUÍDO COM SUCESSO & TESTADO  
**Próxima Sessão:** 26/10/2026 (Segunda-feira)  
**Módulos Impactados:**
- API Crons & Dispatcher Slim 4 (`v1/src/Controllers/CronController.php`, `v1/src/Controllers/CronRotinasController.php`, `v1/routes/api.php`)
- Middleware de Autenticação Segura de Cron (`v1/src/Middleware/CronAuthMiddleware.php`)
- Painel Administrativo de Crons (`portal/modules/admin/crons.php`)
- Módulo de Transferência & Memória Fiscal (`portal/modules/transferencia-compra.php`, `portal/assets/js/transferencia-compra.js`, `v1/src/Controllers/TransferenciaCompraController.php`)

---

## 1. O que foi Concluído Hoje

### A. Desacoplamento do `index_legado.php` & Nova Rota Segura no Slim 4
1. **Fim do acoplamento ao legado:** Eliminada a necessidade de chamar URLs com `acao=...&key=...` no `index_legado.php`.
2. **Nova Rota Segura da API:**
   - `GET|POST /v1/cron/dispatcher` — Dispatcher unificado que roda a cada minuto via cPanel e orquestra todos os jobs do banco `cron_jobs`.
   - `GET|POST /v1/cron/executar/{comando}` — Execução individual e direta de qualquer rotina.
3. **Novo Middleware PSR-15:** [`CronAuthMiddleware.php`](file:///c:/xampp/htdocs/API/v1/src/Middleware/CronAuthMiddleware.php)
   - Valida `CRON_TOKEN` com comparação segura (`hash_equals`).
   - Aceita tanto cabeçalho HTTP `X-Cron-Token: <token>` quanto query string `?token=<token>`.
   - Bloqueia acessos sem autorização com `HTTP 403 Forbidden`.

### B. Migração e Unificação de Todos os Crons (10 Rotinas Cadastradas)
Todas as 10 rotinas foram migradas para o [`CronRotinasController.php`](file:///c:/xampp/htdocs/API/v1/src/Controllers/CronRotinasController.php), com suporte a execução real e simulação Dry-Run (somente leitura):
1. `representantes`: Relatório diário de alterações em itens de pedidos (`0 6 * * *`).
2. `gestores`: Relatório mensal de inadimplência no último dia útil (`0 7 * * 1-5`).
3. `historico_kpi`: Gravação diária de histórico KPI no banco (`26 23 * * *`).
4. `representantes_mensal`: Relatório mensal individual de carteira por representante (`0 4 1 * *`).
5. `clientes_isentos`: Relatório de clientes com IE Isento/Nulo com planilha Excel gerada (`0 10 * * *`).
6. `bonificacoes_flex`: Processamento automático de débito na API Mercos Flex (`0 22 * * *`).
7. `notas_nutrire`: Envio de notas fiscais marca 8 (Nutrire) para API GoFind em chunks de 100 (`0 20 * * *`).
8. `flex_minimo_gestor`: Empréstimo automático de verba flex quando saldo baixo (`0 8 * * *`).
9. `alerta_certificado`: **Nova rotina.** Monitora vencimento dos certificados digitais NFe das filiais ativas (1, 2, 6) e alerta por e-mail quando <= 30 dias ou expirado (`0 8 * * 1`).
10. `refresh_cubo_vendas`: Atualização periódica da Materialized View de vendas (`0 */2 * * *`).

### C. Painel de Controle no Portal (`portal/modules/admin/crons.php`)
- **Linha Única para o cPanel:** Reduz todos os agendamentos do cPanel para apenas 1 linha:
  ```bash
  * * * * * /usr/bin/curl -s -H "X-Cron-Token: SEU_CRON_TOKEN" "https://api.nutricionalbr.com/v1/cron/dispatcher" > /dev/null 2>&1
  ```
- **Botões Rápidos com Teste (Dry-Run):** Adicionados botões no topo para executar ou simular qualquer uma das rotinas sem risco a dados.
- **Toggle On/Off & Schedule:** Gerenciamento total dos horários de execução pela tela do portal.

### D. Módulo de Transferência & Memória Fiscal ST
- Esclarecida a dúvida do cliente 14523 (Depósito PR para estoque de reserva).
- Solicitações aparecem imediatamente no histórico como `Pendente NF` antes de faturar e `Faturado` após emissão da NF.
- Modal de detalhes enriquecido com Dados do Embarque, Pesos (Líquido e Bruto), Itens Transferidos, Pedidos Atendidos e NF emitida.

---

## 2. Status dos Testes
- Validação de sintaxe PHP (`php -l`): **100% OK** em todos os arquivos alterados.
- Teste HTTP da rota `/v1/cron/dispatcher`:
  - Sem Token: **HTTP 403 Forbidden** (Bloqueio correto).
  - Com Header `X-Cron-Token`: **HTTP 200 OK** (Status: completed, 0 erros).
  - Com Query `?token=...`: **HTTP 200 OK** (Status: completed, 0 erros).
- Teste Dry-Run de `alerta_certificado`, `clientes_isentos` e `representantes_mensal`: **100% OK**.

---

## 3. Pauta para Segunda-Feira (26/10/2026)
1. **Validação do cPanel de Produção:**
   - Acompanhar a execução real das 22h da rotina `bonificacoes_flex` faturada na sexta-feira.
   - Atualizar a linha única do cPanel para apontar para a rota `/v1/cron/dispatcher` (ou manter a transição gradativa).
2. **Deploy FTP dos arquivos da sessão:**
   - `v1/src/Middleware/CronAuthMiddleware.php`
   - `v1/routes/api.php`
   - `v1/src/Controllers/CronController.php`
   - `v1/src/Controllers/CronRotinasController.php`
   - `portal/modules/admin/crons.php`
   - `portal/modules/transferencia-compra.php`
   - `portal/assets/js/transferencia-compra.js`
3. **Próximos Módulos e Prioridades do Projeto.**
