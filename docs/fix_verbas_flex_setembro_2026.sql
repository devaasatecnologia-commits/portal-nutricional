-- Correção de divergência de empréstimos Flex em Setembro/2026
-- Os registros #3902 e #3908 sofreram timeout na resposta da API mas foram efetivados no Mercos (R$ 3.000,00 cada).
-- Total corrigido de R$ 63.000,00 para R$ 69.000,00 (23 lançamentos).

UPDATE log_debito_flex_gestor
SET valor_emprestimo = 3000.00,
    motivo = 'Empréstimo para o TIAGO (MOV) para Suprir desfalque de Verba Flex (Efetivado no Mercos)'
WHERE id IN (3902, 3908);

