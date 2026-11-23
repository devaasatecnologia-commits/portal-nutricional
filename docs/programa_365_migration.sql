-- ============================================================================
-- NUTRICIONAL — PROGRAMA 365 COMERCIAL (TEMPORADA 2027 - TEMA JIU-JITSU)
-- Chamado #80 | Schema nativo PostgreSQL (ema) com prefixo p365_
-- ============================================================================

CREATE TABLE IF NOT EXISTS p365_campanhas (
    id SERIAL PRIMARY KEY,
    codigo VARCHAR(20) NOT NULL UNIQUE,
    nome VARCHAR(120) NOT NULL,
    temporada_ano INTEGER NOT NULL,
    tema VARCHAR(60) NOT NULL DEFAULT 'JIU-JITSU',
    subtitulo VARCHAR(160) NULL,
    data_inicio DATE NOT NULL,
    data_fim DATE NOT NULL,
    piso_carteira_positivacao INTEGER NOT NULL DEFAULT 60,
    teto_pontos_mes INTEGER NOT NULL DEFAULT 120,
    xp_maximo_ano INTEGER NOT NULL DEFAULT 1440,
    ativo BOOLEAN NOT NULL DEFAULT TRUE,
    config_json JSONB NOT NULL DEFAULT '{}'::jsonb,
    criado_em TIMESTAMP NOT NULL DEFAULT NOW(),
    atualizado_em TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS p365_equipes (
    id SERIAL PRIMARY KEY,
    idcampanha INTEGER NOT NULL REFERENCES p365_campanhas(id) ON DELETE CASCADE,
    nome VARCHAR(80) NOT NULL,
    idgestor INTEGER NULL,
    cor VARCHAR(20) NOT NULL DEFAULT '#2F5D50',
    ativo BOOLEAN NOT NULL DEFAULT TRUE,
    criado_em TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS p365_participantes (
    id SERIAL PRIMARY KEY,
    idcampanha INTEGER NOT NULL REFERENCES p365_campanhas(id) ON DELETE CASCADE,
    idequipe INTEGER NULL REFERENCES p365_equipes(id) ON DELETE SET NULL,
    idvendedor INTEGER NOT NULL,
    idusuario INTEGER NULL,
    nome_exibicao VARCHAR(120) NOT NULL,
    apelido_tatame VARCHAR(80) NULL,
    categoria VARCHAR(30) NOT NULL DEFAULT 'REPRESENTANTE',
    filiais VARCHAR(40) NOT NULL DEFAULT '1,6',
    carteira_base INTEGER NOT NULL DEFAULT 60,
    ativo BOOLEAN NOT NULL DEFAULT TRUE,
    criado_em TIMESTAMP NOT NULL DEFAULT NOW(),
    UNIQUE (idcampanha, idvendedor)
);

CREATE TABLE IF NOT EXISTS p365_metas_round (
    id SERIAL PRIMARY KEY,
    idcampanha INTEGER NOT NULL REFERENCES p365_campanhas(id) ON DELETE CASCADE,
    idparticipante INTEGER NOT NULL REFERENCES p365_participantes(id) ON DELETE CASCADE,
    ano INTEGER NOT NULL,
    mes INTEGER NOT NULL CHECK (mes BETWEEN 1 AND 12),
    meta_faturamento NUMERIC(14,2) NOT NULL DEFAULT 0,
    carteira_ativa INTEGER NOT NULL DEFAULT 60,
    observacao VARCHAR(255) NULL,
    atualizado_por VARCHAR(60) NULL,
    atualizado_em TIMESTAMP NOT NULL DEFAULT NOW(),
    UNIQUE (idcampanha, idparticipante, ano, mes)
);

CREATE TABLE IF NOT EXISTS p365_desafios_mes (
    id SERIAL PRIMARY KEY,
    idcampanha INTEGER NOT NULL REFERENCES p365_campanhas(id) ON DELETE CASCADE,
    ano INTEGER NOT NULL,
    mes INTEGER NOT NULL CHECK (mes BETWEEN 1 AND 12),
    numero_desafio INTEGER NOT NULL CHECK (numero_desafio IN (1, 2, 3)),
    codigo_golpe VARCHAR(30) NOT NULL,
    titulo VARCHAR(120) NOT NULL,
    descricao TEXT NULL,
    regra_tipo VARCHAR(40) NOT NULL DEFAULT 'PCT_CARTEIRA',
    meta_percentual NUMERIC(6,2) NOT NULL DEFAULT 15,
    pontos INTEGER NOT NULL DEFAULT 10,
    itens_json JSONB NOT NULL DEFAULT '[]'::jsonb,
    ativo BOOLEAN NOT NULL DEFAULT TRUE,
    atualizado_em TIMESTAMP NOT NULL DEFAULT NOW(),
    UNIQUE (idcampanha, ano, mes, numero_desafio)
);

CREATE TABLE IF NOT EXISTS p365_lancamentos_extras (
    id SERIAL PRIMARY KEY,
    idcampanha INTEGER NOT NULL REFERENCES p365_campanhas(id) ON DELETE CASCADE,
    idparticipante INTEGER NOT NULL REFERENCES p365_participantes(id) ON DELETE CASCADE,
    ano INTEGER NOT NULL,
    mes INTEGER NOT NULL CHECK (mes BETWEEN 1 AND 12),
    tipo VARCHAR(30) NOT NULL DEFAULT 'EXTRA_5',
    pontos INTEGER NOT NULL DEFAULT 0,
    valor_ajuste NUMERIC(14,2) NOT NULL DEFAULT 0,
    motivo VARCHAR(255) NOT NULL,
    criado_por VARCHAR(60) NOT NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS p365_round_fechamento (
    id SERIAL PRIMARY KEY,
    idcampanha INTEGER NOT NULL REFERENCES p365_campanhas(id) ON DELETE CASCADE,
    idparticipante INTEGER NOT NULL REFERENCES p365_participantes(id) ON DELETE CASCADE,
    ano INTEGER NOT NULL,
    mes INTEGER NOT NULL CHECK (mes BETWEEN 1 AND 12),
    fat_dia10 NUMERIC(14,2) NOT NULL DEFAULT 0,
    fat_dia20 NUMERIC(14,2) NOT NULL DEFAULT 0,
    fat_dia30 NUMERIC(14,2) NOT NULL DEFAULT 0,
    devolucoes_mes NUMERIC(14,2) NOT NULL DEFAULT 0,
    fat_liquido NUMERIC(14,2) NOT NULL DEFAULT 0,
    meta_faturamento NUMERIC(14,2) NOT NULL DEFAULT 0,
    pct_dia10 NUMERIC(7,2) NOT NULL DEFAULT 0,
    pct_dia20 NUMERIC(7,2) NOT NULL DEFAULT 0,
    pct_dia30 NUMERIC(7,2) NOT NULL DEFAULT 0,
    pts_dia10 INTEGER NOT NULL DEFAULT 0,
    pts_dia20 INTEGER NOT NULL DEFAULT 0,
    pts_dia30 INTEGER NOT NULL DEFAULT 0,
    carteira_ativa INTEGER NOT NULL DEFAULT 60,
    carteira_efetiva INTEGER NOT NULL DEFAULT 60,
    clientes_positivados INTEGER NOT NULL DEFAULT 0,
    pct_positivacao NUMERIC(7,2) NOT NULL DEFAULT 0,
    pts_positivacao INTEGER NOT NULL DEFAULT 0,
    pct_desafio1 NUMERIC(7,2) NOT NULL DEFAULT 0,
    pts_desafio1 INTEGER NOT NULL DEFAULT 0,
    pct_desafio2 NUMERIC(7,2) NOT NULL DEFAULT 0,
    pts_desafio2 INTEGER NOT NULL DEFAULT 0,
    pct_desafio3 NUMERIC(7,2) NOT NULL DEFAULT 0,
    pts_desafio3 INTEGER NOT NULL DEFAULT 0,
    pts_extras INTEGER NOT NULL DEFAULT 0,
    pts_total_mes INTEGER NOT NULL DEFAULT 0,
    bonus_mes_rs NUMERIC(10,2) NOT NULL DEFAULT 0,
    fechado BOOLEAN NOT NULL DEFAULT FALSE,
    fechado_por VARCHAR(60) NULL,
    fechado_em TIMESTAMP NULL,
    detalhes_json JSONB NOT NULL DEFAULT '{}'::jsonb,
    UNIQUE (idcampanha, idparticipante, ano, mes)
);

CREATE TABLE IF NOT EXISTS p365_pins_desbloqueados (
    id SERIAL PRIMARY KEY,
    idcampanha INTEGER NOT NULL REFERENCES p365_campanhas(id) ON DELETE CASCADE,
    idparticipante INTEGER NOT NULL REFERENCES p365_participantes(id) ON DELETE CASCADE,
    pin_codigo VARCHAR(50) NOT NULL,
    ano INTEGER NOT NULL,
    mes INTEGER NOT NULL,
    desbloqueado_em TIMESTAMP NOT NULL DEFAULT NOW(),
    UNIQUE (idcampanha, idparticipante, pin_codigo, ano, mes)
);

CREATE INDEX IF NOT EXISTS idx_p365_metas_round_lookup ON p365_metas_round (idcampanha, ano, mes);
CREATE INDEX IF NOT EXISTS idx_p365_fechamento_lookup ON p365_round_fechamento (idcampanha, ano, mes);
CREATE INDEX IF NOT EXISTS idx_p365_extras_lookup ON p365_lancamentos_extras (idcampanha, idparticipante, ano, mes);

-- Seed da Campanha T27 (Temporada 2027 - Jiu-Jitsu: O Caminho da Faixa Preta)
INSERT INTO p365_campanhas (codigo, nome, temporada_ano, tema, subtitulo, data_inicio, data_fim, piso_carteira_positivacao, teto_pontos_mes, xp_maximo_ano, ativo)
VALUES (
    'T27',
    'Programa 365 Comercial - Temporada 2027',
    2027,
    'JIU-JITSU',
    'O Caminho da Faixa Preta (1.440 XP)',
    '2027-01-01',
    '2027-12-31',
    60,
    120,
    1440,
    TRUE
)
ON CONFLICT (codigo) DO NOTHING;

-- Registro do modulo no Portal
INSERT INTO sistema_modulos (slug, nome, descricao, icon, cor_bg, cor_text, url, ativo, ordem, setor, cor_from, cor_to)
SELECT
    'programa-365',
    'Programa 365 Comercial',
    'Campanha gamificada T27 (Jiu-Jitsu), ranking, faixas, PINs e bonus comercial',
    'fa-solid fa-medal',
    'bg-emerald-50',
    'text-emerald-700',
    '/portal/modules/programa-365.php',
    TRUE,
    18,
    'Comercial',
    '#2F5D50',
    '#1F3F36'
WHERE NOT EXISTS (SELECT 1 FROM sistema_modulos WHERE slug = 'programa-365');
