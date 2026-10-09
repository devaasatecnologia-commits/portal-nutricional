-- Item a mais na subida (ex.: unidade rasgada carregada como reserva).
-- Não altera quantidades de carregamento; é registro auditável vinculado à subida.
CREATE TABLE IF NOT EXISTS carregamento_subida_extras (
    id              BIGSERIAL PRIMARY KEY,
    id_subida       INTEGER NOT NULL REFERENCES carregamento_subidas(id) ON DELETE CASCADE,
    id_carregamento INTEGER,
    idembarque      INTEGER NOT NULL,
    iditem          INTEGER NOT NULL,
    quantidade      NUMERIC(14,3) NOT NULL CHECK (quantidade > 0),
    motivo          TEXT NOT NULL,
    idusuario       INTEGER,
    criado_em       TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_carr_extras_subida ON carregamento_subida_extras (id_subida);
CREATE INDEX IF NOT EXISTS idx_carr_extras_embarque ON carregamento_subida_extras (idembarque, iditem);