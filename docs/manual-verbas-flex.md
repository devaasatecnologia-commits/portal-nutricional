# Manual do Especialista — Gestão de Verbas Flex & Saldo Mercos v3.0

## 1. Visão Geral e Propósito do Módulo
O módulo de **Gestão de Verbas Flex** (`portal/modules/verbas-flex.php` e `v1/src/Controllers/VerbasFlexController.php`) é responsável pelo monitoramento e auditoria em tempo real do uso de verba comercial e bonificações na Nutricional.

---

## 2. Nomenclatura Padronizada: ENVIOU / RECEBEU
Para eliminar qualquer ambiguidade de interpretação na equipe de vendas e supervisão:
- **ENVIOU (anteriormente "Cedeu"):** Operação de transferência onde o colaborador transfere parte do seu saldo de verba flex para um colega (débito/saída).
- **RECEBEU:** Operação de transferência onde o colaborador recebe verba flex de outro colega ou de um gestor (crédito/entrada).
- **Consumo em Bonificações:** Retirada direta de saldo flex aplicada em notas fiscais de bonificação faturadas para clientes no período.

---

## 3. Consolidação de Múltiplas Filiais (`filial = 0`)
- Quando o usuário logado possui mais de 1 filial liberada, o seletor de filial inclui no topo a opção **"Todas as Filiais Liberadas" (`filial = 0`)**.
- O backend (`VerbasFlexController.php`) agrupa todos os débitos, créditos e transferências das filiais autorizadas via `IN (...)`, garantindo consolidação imediata sem violar o escopo do usuário.

---

## 4. Cards Executivos de Performance (com Title Detalhado ao Passar o Mouse)
Seguindo o mesmo padrão do Dashboard de Vendas, Financeiro e CRM:
1. **Empréstimos Concedidos:** Total de créditos aportados pela diretoria/gestores.
2. **Consumo em Bonificações:** Total de verba flex consumida em bonificações faturadas.
3. **Saldo Líquido:** Resultado do período ([Aportes (+)] - [Bonificações (-)]).
4. **Ticket Médio:** Média financeira por nota fiscal de bonificação.
5. **Maior Retirada Única:** Maior valor individual de bonificação concedido em um pedido/nota.
6. **Equipe com Consumo Ativo:** Contagem de colaboradores com movimentação no período.

---

## 5. Treinamento e Central de Manuais
- Manual registrado na tabela `sistema_manuais` (IDs 26 e 27) com versão **3.0**, cobrindo fórmulas, atalhos de períodos rápidos, filtros e dúvidas frequentes.
