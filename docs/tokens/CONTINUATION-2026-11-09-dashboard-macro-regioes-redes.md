# Continuation Token — Reestruturação de Macro-Regiões Oficiais e Regra de Redes no Dashboard de Vendas

- **Data/Hora:** 09/11/2026 - 10:23
- **Branch:** main
- **Status:** Concluído e Validado

---

## 1. O que foi solicitado
1. Reestruturação da divisão de regiões no **Dashboard de Vendas** agrupando os IDs exatos da tabela `regiao` do PostgreSQL:
   - **RMC:** IDs 24, 25, 26, 27, 28, 29 (Curitiba & Litoral PR)
   - **AVI:** IDs 2, 3, 4, 5 (Vale do Itajaí & Litoral)
   - **GRF:** IDs 6, 7, 8, 9 (Grande Florianópolis)
   - **NOR:** IDs 1, 10, 11, 12, 13 (Norte Catarinense & Vale do Itapocu)
   - **PLN:** IDs 19, 20, 21, 22 (Planalto Serrano & Oeste)
   - **SUL:** IDs 14, 15, 16, 17 (Sul Catarinense)
   - **RED:** ID 18 (`[SC]*RED (TODAS AS REDES)`)
2. **Regra de Permissão da Região 18 (RED):**
   - Apenas usuários com **acesso a todas as filiais e a todos os usuários** (ex: Admin ou permissão `permite_ver_usuarios = 'S'` e filiais `1,6` liberadas, como Alan #4 e Robson #5) podem visualizar o grupo RED e seus dados.
   - Gestores com escopo restrito (ex.: Tales #19, Michel #95, Tiago #15, etc.) não veem o grupo RED nem suas vendas (não vaza nem é somado ao SUL).
   - Bloqueio de segurança 403 caso um usuário restrito tente abrir o card da região RED ou visualizar clientes de redes corporativas.
3. **Identificação das Sub-regiões:**
   - Exibir os nomes das sub-regiões que compõem cada grupo nos cards de região e nos modais de auditoria.

---

## 2. Modificações Realizadas
1. **`v1/src/Utils/UserScope.php`:**
   - Corrigido campo `idrepresentante` na view `vw_gestor_repre`.
   - Adicionado `$permiteVerUsuarios` e método `temAcessoTotal(): bool`.
2. **`v1/src/Controllers/VendasDashboardController.php`:**
   - `REGIOES_MACRO`: 7 grupos com cores, siglas, descrições completas e flag `restrita_redes`.
   - `regiaoMacroSql()`: mapeamento unificado via SQL `CASE WHEN`.
   - `distribuicaoRegiao()`, `getDetalhesCard()`, `clientesRisco()`, `topClientes()` e `getDetalhesCliente()`: todos respeitam a permissão de redes, filtrando e bloqueando se o usuário for restrito.
3. **`portal/modules/dashboard-vendas.php`:**
   - Cards com badge `Redes` para o grupo RED.
   - Exibição das sub-regiões abaixo da barra de progresso com ícone e tooltip.
   - Modal de detalhes com subtítulo das sub-regiões.
   - Select de filtro de região dinâmico em todos os modais.
4. **`portal/assets/js/dashboard-vendas.js`:**
   - Suporte a `cardSubregioes` e passagem de dados ao abrir o modal de auditoria.

---

## 3. Arquivos para Deploy FTP
```text
portal/assets/js/dashboard-vendas.js
portal/modules/dashboard-vendas.php
v1/src/Controllers/VendasDashboardController.php
v1/src/Utils/UserScope.php
```
