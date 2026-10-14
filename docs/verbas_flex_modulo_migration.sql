-- Registra o modulo Verbas Flex (painel, debitos e creditos). Rodar com aprovacao do dono do banco.
-- Acesso: liberar o modulo, dash_filiais e dash_gestores de cada usuario pelo painel admin.
INSERT INTO sistema_modulos (slug, nome, descricao, icon, cor_bg, cor_text, url, ordem, ativo, setor)
VALUES ('verbas-flex', 'Verbas Flex', 'Painel, debitos de bonificacao e emprestimos ao gestor',
        'fa-sack-dollar', 'bg-violet-100', 'text-violet-600', '/portal/modules/verbas-flex.php', 92, true, 'Comercial')
ON CONFLICT (slug) DO NOTHING;
