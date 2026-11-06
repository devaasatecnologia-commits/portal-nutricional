# CONTINUATION TOKEN: Módulo Fluxo E-commerce (AZ PETS CO • Hierarquia Dinâmica Cliente 1º -> Tabela 2º)

- **Data/Hora:** 06/11/2026 - 15:10
- **Responsável:** Tech Lead & Arquiteto Nutricional
- **Status:** CONCLUÍDO E HOMOLOGADO LOCALMENTE

---

## 1. Processo de Faturamento & Separação (STAND-BY)

Documentado integralmente em [`docs/ESTUDO_TECNICO_FATURAMENTO_ECOMERCE.md`](file:///c:/xampp/htdocs/API/docs/ESTUDO_TECNICO_FATURAMENTO_ECOMERCE.md):
- **Raio-X do Pedido 160322:** `pedido` (Transação 4, Série B/0) -> `nfs` (ID 300622, NF 150481, Transação 1003, CFOP 5102) -> `receber` (Título 150481-B/1).
- **Série B:** Nota interna (`gera_nf_eletronica = 'N'`), porém gera SPED Fiscal (`exportaescritafiscal = 'S'`).
- **Decisão:** Manter faturamento fiscal a cargo do ERP desktop para garantir integridade contábil.
- **Módulo de Separação / Alerta:** Desenhado e mantido em stand-by até autorização do usuário.

---

## 2. Hierarquia Dinâmica de Condição e Método de Pagamento (Atualizado)

Conforme regra de negócio solicitada:
1. **1º Prioridade (Cliente):** Busca primeiro o que está configurado no cadastro do cliente `16736 - AZ PETS CO`:
   ```sql
   SELECT c.idcondicaopagto, cli.idmetodo
   FROM cliforemp c
   JOIN cliente cli ON cli.idcliforemp = c.idcliforemp
   WHERE c.idcliforemp = 16736
   ```
   - **Resultado Atual:** Condição `1072 - PRAZO [ECOMERCE] CARTEIRA` e Método `14 - CREDIARIO`.
2. **2º Prioridade (Tabela 15):** Se não houver no cliente (nulo ou zerado), busca os padrões da Tabela de Preço 15 (`tabela_preco.idcondicao` e `tabela_preco.idmetodo`).
3. **Dinâmico em Tempo Real:** Se o cadastro do cliente ou da tabela for alterado no ERP, o Portal detecta imediatamente na API `/contexto` e grava os novos IDs no pedido sem necessidade de alteração de código.

---

## 3. Interface e Observação

1. **Barra Superior Compacta:** Exibe os dados dinamicamente:
   - `Condição: 1072 (PRAZO [ECOMERCE] CARTEIRA)`
   - `Método: 14 (CREDIARIO)`
2. **Observação Fiel:** Grava estritamente o que o usuário digitar; se não digitar nada, grava vazio (`''`).

---

## 4. Arquivos para Deploy FTP

1. [`v1/src/Controllers/FluxoEcomerceController.php`](file:///c:/xampp/htdocs/API/v1/src/Controllers/FluxoEcomerceController.php)
2. [`portal/modules/fluxo-ecomerce.php`](file:///c:/xampp/htdocs/API/portal/modules/fluxo-ecomerce.php)
3. [`portal/assets/js/fluxo-ecomerce.js`](file:///c:/xampp/htdocs/API/portal/assets/js/fluxo-ecomerce.js)
4. [`docs/ESTUDO_TECNICO_FATURAMENTO_ECOMERCE.md`](file:///c:/xampp/htdocs/API/docs/ESTUDO_TECNICO_FATURAMENTO_ECOMERCE.md)
