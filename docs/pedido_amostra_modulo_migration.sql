-- Registra o modulo Pedido de Amostra. Rodar com aprovacao do dono do banco.
-- Depois, liberar o modulo e dash_filiais para cada gestor pelo painel admin.
INSERT INTO sistema_modulos (slug, nome, descricao, icon, cor_bg, cor_text, url, ordem, ativo, setor)
VALUES ('pedido-amostra', 'Pedido de Amostra', 'Criacao de pedidos de amostra e brinde pelo gestor',
        'fa-gift', 'bg-emerald-100', 'text-emerald-600', '/portal/modules/pedido-amostra.php', 90, true, 'Comercial')
ON CONFLICT (slug) DO NOTHING;
