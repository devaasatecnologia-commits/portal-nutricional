-- Transferencias de compra Matriz e Filial (consulta) + valores administraveis da guia.

CREATE TABLE IF NOT EXISTS guia_transferencia_config (
    id SERIAL PRIMARY KEY,
    tipo VARCHAR(5) NOT NULL DEFAULT 'ST' CHECK (tipo IN ('ST', 'NCM')),
    ncm VARCHAR(10),
    descricao VARCHAR(120),
    aliquota NUMERIC(10,6) NOT NULL CHECK (aliquota >= 0 AND aliquota <= 1),
    vigencia_inicio DATE NOT NULL,
    ativo BOOLEAN NOT NULL DEFAULT TRUE,
    atualizado_por VARCHAR(80),
    atualizado_em TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- Valor que estava fixo na consulta (0,190322 = 19,0322%)
INSERT INTO guia_transferencia_config (tipo, descricao, aliquota, vigencia_inicio, atualizado_por)
SELECT 'ST', 'Aliquota original da consulta do ERP', 0.190322, DATE '2000-01-01', 'migracao'
WHERE NOT EXISTS (SELECT 1 FROM guia_transferencia_config WHERE tipo = 'ST');

INSERT INTO sistema_modulos (slug, nome, descricao, icon, cor_bg, cor_text, url, ordem, ativo, setor)
VALUES
 ('transferencia-matriz-filial', 'Matriz para Filial', 'Solicitacoes de compra por embarque/remessa e guia ST',
  'fa-truck-ramp-box', 'bg-sky-100', 'text-sky-600', '/portal/modules/transferencia-compra.php?tipo=matriz-filial', 93, true, 'Logística'),
 ('transferencia-filial-matriz', 'Filial para Matriz', 'Solicitacoes de compra geradas por ordem de compra',
  'fa-warehouse', 'bg-teal-100', 'text-teal-600', '/portal/modules/transferencia-compra.php?tipo=filial-matriz', 94, true, 'Logística')
ON CONFLICT (slug) DO NOTHING;
