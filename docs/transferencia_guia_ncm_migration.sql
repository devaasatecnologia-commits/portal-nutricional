-- Regras da guia de ICMS-ST por NCM, baseadas na planilha
-- "Calculadora ICMS-ST PR por NCM - Resumo Agrupado".
-- A lista e vigencia devem ser revisadas pelo responsavel fiscal da empresa.

SET client_encoding = 'UTF8';

CREATE TABLE IF NOT EXISTS guia_transferencia_ncm_regra (
    id SERIAL PRIMARY KEY,
    ncm VARCHAR(8) NOT NULL CHECK (ncm ~ '^[0-9]{8}$'),
    descricao VARCHAR(200) NOT NULL DEFAULT '',
    st_pr BOOLEAN NOT NULL DEFAULT FALSE,
    mva_percentual NUMERIC(10,4) NOT NULL DEFAULT 0 CHECK (mva_percentual >= 0 AND mva_percentual <= 1000),
    aliquota_interna_percentual NUMERIC(8,4) NOT NULL DEFAULT 19.5 CHECK (aliquota_interna_percentual >= 0 AND aliquota_interna_percentual <= 100),
    credito_interestadual_percentual NUMERIC(8,4) NOT NULL DEFAULT 12 CHECK (credito_interestadual_percentual >= 0 AND credito_interestadual_percentual <= 100),
    operador_mva VARCHAR(1) NOT NULL DEFAULT '*' CHECK (operador_mva IN ('+', '-', '*', '/')),
    operador_guia VARCHAR(1) NOT NULL DEFAULT '-' CHECK (operador_guia IN ('+', '-', '*', '/')),
    vigencia_inicio DATE NOT NULL,
    ativo BOOLEAN NOT NULL DEFAULT TRUE,
    atualizado_por VARCHAR(80),
    atualizado_em TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE UNIQUE INDEX IF NOT EXISTS uq_guia_transferencia_ncm_vigencia_ativa
    ON guia_transferencia_ncm_regra (ncm, vigencia_inicio)
    WHERE ativo = TRUE;

INSERT INTO guia_transferencia_ncm_regra
    (ncm, descricao, st_pr, mva_percentual, aliquota_interna_percentual,
     credito_interestadual_percentual, operador_mva, operador_guia,
     vigencia_inicio, ativo, atualizado_por)
VALUES
    ('23099010', 'Rações para Cães e Gatos (Monello, Birbo, Bancat, Bandit)', TRUE, 59.60, 19.50, 12.00, '*', '-', DATE '2000-01-01', TRUE, 'planilha'),
    ('23099090', 'Petiscos, Sachês e Bifinhos (Everest, Maskotinho)', TRUE, 59.60, 19.50, 12.00, '*', '-', DATE '2000-01-01', TRUE, 'planilha'),
    ('33051000', 'Xampus e Condicionadores Pet (Sanol, Matacura)', TRUE, 59.60, 19.50, 12.00, '*', '-', DATE '2000-01-01', TRUE, 'planilha'),
    ('33079000', 'Banho Seco Spray / Perfumaria Pet', TRUE, 59.60, 19.50, 12.00, '*', '-', DATE '2000-01-01', TRUE, 'planilha'),
    ('34012090', 'Sabonetes Neutro e Filhotes', TRUE, 59.60, 19.50, 12.00, '*', '-', DATE '2000-01-01', TRUE, 'planilha'),
    ('33029019', 'Colônias Pet', TRUE, 59.60, 19.50, 12.00, '*', '-', DATE '2000-01-01', TRUE, 'planilha'),
    ('25262000', 'Talcos Pet Banho Seco', TRUE, 59.60, 19.50, 12.00, '*', '-', DATE '2000-01-01', TRUE, 'planilha'),
    ('38089429', 'Eliminadores de Odores (Sanol)', TRUE, 59.60, 19.50, 12.00, '*', '-', DATE '2000-01-01', TRUE, 'planilha'),
    ('34023990', 'Lava Roupas Pet (Sanol Plush)', TRUE, 59.60, 19.50, 12.00, '*', '-', DATE '2000-01-01', TRUE, 'planilha'),
    ('33012990', 'Essências e Limpadores', TRUE, 59.60, 19.50, 12.00, '*', '-', DATE '2000-01-01', TRUE, 'planilha'),
    ('28322000', 'Tira Manchas e Odores Pet', TRUE, 59.60, 19.50, 12.00, '*', '-', DATE '2000-01-01', TRUE, 'planilha'),
    ('38089119', 'Repelentes de Citronela', TRUE, 59.60, 19.50, 12.00, '*', '-', DATE '2000-01-01', TRUE, 'planilha'),
    ('23091000', 'Alimentos Pet Acondicionados (Coolors Naturalstick)', FALSE, 0, 19.50, 12.00, '*', '-', DATE '2000-01-01', TRUE, 'planilha'),
    ('48189090', 'Tapetes Higiênicos (Great Pets)', FALSE, 0, 19.50, 12.00, '*', '-', DATE '2000-01-01', TRUE, 'planilha'),
    ('35051000', 'Substratos e Granulados Higiênicos de Milho/Mandioca (Faraó, Bio Gold)', FALSE, 0, 19.50, 12.00, '*', '-', DATE '2000-01-01', TRUE, 'planilha'),
    ('25081000', 'Areias Higiênicas Bentonita (White Snow, Moon)', FALSE, 0, 19.50, 12.00, '*', '-', DATE '2000-01-01', TRUE, 'planilha'),
    ('28112230', 'Areias de Sílica para Gatos', FALSE, 0, 19.50, 12.00, '*', '-', DATE '2000-01-01', TRUE, 'planilha'),
    ('14049090', 'Granulados Higiênicos de Tofu', FALSE, 0, 19.50, 12.00, '*', '-', DATE '2000-01-01', TRUE, 'planilha'),
    ('38029020', 'Granulados Sanitários Ativados (Mitzi, Putz)', FALSE, 0, 19.50, 12.00, '*', '-', DATE '2000-01-01', TRUE, 'planilha'),
    ('25059000', 'Outras Areias e Diatomita', FALSE, 0, 19.50, 12.00, '*', '-', DATE '2000-01-01', TRUE, 'planilha'),
    ('25084090', 'Outras Areias e Diatomita', FALSE, 0, 19.50, 12.00, '*', '-', DATE '2000-01-01', TRUE, 'planilha'),
    ('25120000', 'Outras Areias e Diatomita', FALSE, 0, 19.50, 12.00, '*', '-', DATE '2000-01-01', TRUE, 'planilha'),
    ('30049099', 'Medicamentos e Produtos Veterinários', FALSE, 0, 19.50, 12.00, '*', '-', DATE '2000-01-01', TRUE, 'planilha'),
    ('30049029', 'Medicamentos e Produtos Veterinários', FALSE, 0, 19.50, 12.00, '*', '-', DATE '2000-01-01', TRUE, 'planilha'),
    ('30049021', 'Medicamentos e Produtos Veterinários', FALSE, 0, 19.50, 12.00, '*', '-', DATE '2000-01-01', TRUE, 'planilha'),
    ('30024991', 'Medicamentos e Produtos Veterinários', FALSE, 0, 19.50, 12.00, '*', '-', DATE '2000-01-01', TRUE, 'planilha'),
    ('38089192', 'Sarnicidas e Inseticidas Veterinários', FALSE, 0, 19.50, 12.00, '*', '-', DATE '2000-01-01', TRUE, 'planilha'),
    ('38089294', 'Sarnicidas e Inseticidas Veterinários', FALSE, 0, 19.50, 12.00, '*', '-', DATE '2000-01-01', TRUE, 'planilha'),
    ('38089299', 'Sarnicidas e Inseticidas Veterinários', FALSE, 0, 19.50, 12.00, '*', '-', DATE '2000-01-01', TRUE, 'planilha'),
    ('10064000', 'Arroz para Cães', FALSE, 0, 19.50, 12.00, '*', '-', DATE '2000-01-01', TRUE, 'planilha'),
    ('29141990', 'Educadores Sanitários e Abrilhantadores', FALSE, 0, 19.50, 12.00, '*', '-', DATE '2000-01-01', TRUE, 'planilha'),
    ('38249929', 'Educadores Sanitários e Abrilhantadores', FALSE, 0, 19.50, 12.00, '*', '-', DATE '2000-01-01', TRUE, 'planilha')
ON CONFLICT DO NOTHING;

-- A planilha também contém "548544" sem 8 dígitos. Não foi cadastrado por não
-- ser possível identificar com segurança o NCM pretendido; confirmar com o fiscal.
