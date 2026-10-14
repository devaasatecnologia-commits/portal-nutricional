-- Registra o modulo Transferencia de Flex. Rodar com aprovacao do dono do banco.
-- Depois, liberar o modulo, dash_filiais e dash_gestores para cada usuario pelo painel admin.
INSERT INTO sistema_modulos (slug, nome, descricao, icon, cor_bg, cor_text, url, ordem, ativo, setor)
VALUES ('transferencia-flex', 'Transferência de Flex', 'Transferencia de saldo Flex entre representantes e gestores',
        'fa-right-left', 'bg-violet-100', 'text-violet-600', '/portal/modules/transferencia-flex.php', 91, true, 'Comercial')
ON CONFLICT (slug) DO NOTHING;
