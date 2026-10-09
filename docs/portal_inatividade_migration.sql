-- Registro dos motivos informados apos inatividade na sessao do portal.
-- Aplicar uma vez; a API registra em error_log enquanto a tabela nao existir.
CREATE TABLE IF NOT EXISTS portal_inatividade (
    id BIGSERIAL PRIMARY KEY,
    idusuario INTEGER NOT NULL,
    username VARCHAR(100) NULL,
    motivo VARCHAR(300) NOT NULL,
    minutos_inativo INTEGER NOT NULL DEFAULT 0,
    modulo VARCHAR(40) NULL,
    pagina VARCHAR(200) NULL,
    ip_origem VARCHAR(45) NULL,
    user_agent TEXT NULL,
    criado_em TIMESTAMPTZ NOT NULL DEFAULT clock_timestamp()
);

CREATE INDEX IF NOT EXISTS idx_portal_inatividade_usuario
    ON portal_inatividade (idusuario, criado_em DESC);

CREATE INDEX IF NOT EXISTS idx_portal_inatividade_periodo
    ON portal_inatividade (criado_em DESC);

-- Codigo da tabela de paradas logisticas (1.1 a 6.10)
ALTER TABLE portal_inatividade ADD COLUMN IF NOT EXISTS codigo_parada VARCHAR(5) NULL;
CREATE INDEX IF NOT EXISTS idx_portal_inatividade_codigo ON portal_inatividade (codigo_parada, criado_em DESC);
