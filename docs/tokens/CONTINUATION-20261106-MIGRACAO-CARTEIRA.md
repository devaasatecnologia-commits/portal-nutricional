# CONTINUATION TOKEN: Módulo Migração de Carteira de Clientes (ERP & Mercos -> Portal)

- **Data/Hora:** 06/11/2026 - 12:40
- **Responsável:** Tech Lead & Arquiteto Nutricional
- **Status:** CONCLUÍDO E HOMOLOGADO LOCALMENTE

---

## 1. O que foi Implementado

Migração completa do processo legado em JavaScript/DOX do ERP para o **Portal Nutricional** em `/portal/modules/migracao-carteira.php`, integrando:
1. **Validação em Tempo Real na API Mercos (`saldo_flex`):**
   - Filial 1 (Matriz SC): `http://mercosf1.nutricionalbr.com:8081/saldo_flex`
   - Filial 6 / outras: `http://mercosf1.nutricionalbr.com:8086/saldo_flex`
   - Retorno assíncrono com badge e alerta caso o usuário de destino não esteja ativo no Mercos.
2. **Seleção Inteligente DE -> PARA:**
   - Dropdown com representantes comerciais ativos (`TIPOCLIFOREMP = 1 AND fornecedor.representante = 'S'`).
   - Grade interativa de clientes da carteira de origem com contadores, busca instantânea (fantasia, razão, cnpj, cidade) e botões rápidos (marcar/desmarcar todos).
   - Se nenhum cliente for marcado individualmente, assume toda a carteira de acordo com a regra de negócio legada.
3. **Transação Atômica no Banco de Dados (`PDO::beginTransaction()`):**
   - **Ação 1 (`palmtop`):** Localiza registro ativo por observação, reaproveita o menor inativo ou insere novo dispositivo via sequence `gi_palmtop`.
   - **Ação 2 (`palmtop_vendrepre`):** Atualiza vínculo com o dispositivo ou insere novo registro com `tipovendrepre = 2`.
   - **Ação 3 (`cliforemp`, `cliente`, `cliente_vendedor_mov`):** Atualiza `idvendedor` do representante antigo para o novo.
   - **Ação 4 (`receber`):** Atualiza `idvendrepre` nos títulos em aberto (`status IN (2, 3)`).
   - **Ação 5 (`log_provider`):** Insere registros para `CLIFOREMP` e `CLIENTE` com sequence `gi_log_provider` para sincronização com o integrador Mercos.
   - **Auditoria (`migracao_carteira_historico`):** Tabela de histórico completa com lista de clientes transferidos, operador, títulos atualizados e status de e-mail.
4. **Disparo de E-mails com CC Gerenciável:**
   - Envio de layout HTML moderno para o Novo Representante, Antigo Representante e Supervisor.
   - Inserção da chave `migracao_carteira_cc` em `config_email_listas` para gerenciamento via `/portal/modules/admin/configuracoes-modulos.php`.
5. **Módulo Registrado no Portal:**
   - Cadastrado em `sistema_modulos` (`slug = 'migracao-carteira'`) com permissões para administradores e setor comercial.

---

## 2. Arquivos Modificados / Criados para Deploy FTP

1. `v1/src/Controllers/MigracaoCarteiraController.php` *(Novo backend)*
2. `v1/routes/api.php` *(Novas rotas /migracao-carteira)*
3. `portal/modules/migracao-carteira.php` *(Nova tela do portal)*
4. `portal/assets/js/migracao-carteira.js` *(Novo script do portal)*
5. `email_listas.php` *(Nova chave migracao_carteira_cc no fallback)*
