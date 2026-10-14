-- Aparência do menu (Admin > Aparência do Menu).
-- O sistema tenta criar estas colunas sozinho; rode manualmente se o usuário do app não tiver permissão de DDL.
ALTER TABLE sistema_setores  ADD COLUMN IF NOT EXISTS cor_from VARCHAR(9), ADD COLUMN IF NOT EXISTS cor_to VARCHAR(9);
ALTER TABLE sistema_modulos  ADD COLUMN IF NOT EXISTS cor_from VARCHAR(9), ADD COLUMN IF NOT EXISTS cor_to VARCHAR(9);

-- Opcional: cadastra o setor Comercial para aparecer na lista de setores do admin
INSERT INTO sistema_setores (nome, slug, icone, cor_bg, cor_texto, descricao, ordem, ativo)
SELECT 'Comercial', 'comercial', 'fa-handshake', 'bg-emerald-100', 'text-emerald-600', 'Vendas e pedidos', 10, true
WHERE NOT EXISTS (SELECT 1 FROM sistema_setores WHERE nome = 'Comercial');
