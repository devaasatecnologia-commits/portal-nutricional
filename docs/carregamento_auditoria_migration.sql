-- Execute once, after backup and homologation review, on the PostgreSQL schema
-- used by the API. Historical subida-to-order allocations are intentionally
-- not guessed; exact allocation begins with confirmations after deployment.
BEGIN;

CREATE TABLE IF NOT EXISTS carregamento_subida_alocacoes (
    id BIGSERIAL PRIMARY KEY,
    id_subida INTEGER NOT NULL
        REFERENCES carregamento_subidas(id) ON DELETE RESTRICT,
    id_carregamento INTEGER NOT NULL,
    idembarque INTEGER NOT NULL,
    iditem INTEGER NOT NULL,
    idpedido INTEGER NOT NULL,
    iditempedido INTEGER NOT NULL,
    quantidade NUMERIC(14,4) NOT NULL CHECK (quantidade > 0),
    criado_em TIMESTAMPTZ NOT NULL DEFAULT clock_timestamp(),
    CONSTRAINT uq_carregamento_subida_alocacao_pedido_item
        UNIQUE (id_subida, idpedido, iditempedido)
);

ALTER TABLE carregamento_subida_alocacoes
    DROP CONSTRAINT IF EXISTS carregamento_subida_alocacoes_id_subida_iditempedido_key;

DO $migration$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'uq_carregamento_subida_alocacao_pedido_item'
          AND conrelid = 'carregamento_subida_alocacoes'::regclass
    ) THEN
        ALTER TABLE carregamento_subida_alocacoes
            ADD CONSTRAINT uq_carregamento_subida_alocacao_pedido_item
            UNIQUE (id_subida, idpedido, iditempedido);
    END IF;
END;
$migration$;

CREATE INDEX IF NOT EXISTS idx_carregamento_subida_alocacao_embarque_item
    ON carregamento_subida_alocacoes (idembarque, iditem, id_subida);

CREATE TABLE IF NOT EXISTS carregamento_estornos (
    id BIGSERIAL PRIMARY KEY,
    idembarque INTEGER NOT NULL,
    iditem INTEGER NOT NULL,
    id_subida INTEGER NULL
        REFERENCES carregamento_subidas(id) ON DELETE RESTRICT,
    tipo VARCHAR(24) NOT NULL
        CHECK (tipo IN ('SUBIDA', 'ITEM', 'TODAS', 'DESFAZER')),
    idusuario INTEGER NOT NULL,
    numero_subida_original INTEGER NULL,
    quantidade_estornada NUMERIC(14,4) NOT NULL CHECK (quantidade_estornada > 0),
    caminho_foto_snapshot TEXT NULL,
    motivo TEXT NOT NULL CHECK (length(trim(motivo)) BETWEEN 3 AND 500),
    alocacoes_snapshot JSONB NOT NULL DEFAULT '[]'::jsonb
        CHECK (jsonb_typeof(alocacoes_snapshot) = 'array'),
    criado_em TIMESTAMPTZ NOT NULL DEFAULT clock_timestamp()
);

CREATE INDEX IF NOT EXISTS idx_carregamento_estornos_subida
    ON carregamento_estornos (id_subida, criado_em);

CREATE INDEX IF NOT EXISTS idx_carregamento_estornos_embarque_item
    ON carregamento_estornos (idembarque, iditem, criado_em);

ALTER TABLE carregamento_fotos
    ADD COLUMN IF NOT EXISTS id_subida INTEGER NULL;

DO $migration$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'fk_carregamento_fotos_subida_audit'
          AND conrelid = 'carregamento_fotos'::regclass
    ) THEN
        ALTER TABLE carregamento_fotos
            ADD CONSTRAINT fk_carregamento_fotos_subida_audit
            FOREIGN KEY (id_subida)
            REFERENCES carregamento_subidas(id)
            ON DELETE RESTRICT;
    END IF;
END;
$migration$;

CREATE INDEX IF NOT EXISTS idx_carregamento_fotos_subida
    ON carregamento_fotos (id_subida, data_hora DESC);

DROP TRIGGER IF EXISTS trg_carregamento_fotos_immutavel ON carregamento_fotos;

UPDATE carregamento_fotos cf
SET id_subida = (
    SELECT cs.id
    FROM carregamento_subidas cs
    WHERE cs.idembarque = cf.idembarque
      AND cs.iditem = cf.iditem
      AND cs.path_foto = cf.caminho_foto
    ORDER BY cs.id DESC
    LIMIT 1
)
WHERE cf.id_subida IS NULL
  AND EXISTS (
      SELECT 1
      FROM carregamento_subidas cs
      WHERE cs.idembarque = cf.idembarque
        AND cs.iditem = cf.iditem
        AND cs.path_foto = cf.caminho_foto
  );

CREATE OR REPLACE FUNCTION impedir_alteracao_trilha_carregamento()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $function$
BEGIN
    RAISE EXCEPTION 'A trilha de auditoria do carregamento aceita apenas novos registros';
    RETURN OLD;
END;
$function$;

CREATE OR REPLACE FUNCTION impedir_exclusao_subida_carregamento()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $function$
BEGIN
    RAISE EXCEPTION 'Subidas de carregamento nao podem ser apagadas; registre um estorno';
    RETURN OLD;
END;
$function$;

CREATE OR REPLACE FUNCTION impedir_alteracao_dados_subida_carregamento()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $function$
BEGIN
    IF ROW(
        OLD.id_carregamento, OLD.idembarque, OLD.iditem, OLD.idusuario,
        OLD.doca, OLD.qt_subida, OLD.qt_acumulada, OLD.numero_subida, OLD.data_hora
    ) IS DISTINCT FROM ROW(
        NEW.id_carregamento, NEW.idembarque, NEW.iditem, NEW.idusuario,
        NEW.doca, NEW.qt_subida, NEW.qt_acumulada, NEW.numero_subida, NEW.data_hora
    ) THEN
        RAISE EXCEPTION 'Dados originais da subida sao imutaveis; registre um estorno';
    END IF;
    RETURN NEW;
END;
$function$;

DROP TRIGGER IF EXISTS trg_carregamento_subida_preservar_historico ON carregamento_subidas;
CREATE TRIGGER trg_carregamento_subida_preservar_historico
    BEFORE DELETE ON carregamento_subidas
    FOR EACH ROW EXECUTE FUNCTION impedir_exclusao_subida_carregamento();

DROP TRIGGER IF EXISTS trg_carregamento_subida_dados_immutaveis ON carregamento_subidas;
CREATE TRIGGER trg_carregamento_subida_dados_immutaveis
    BEFORE UPDATE ON carregamento_subidas
    FOR EACH ROW EXECUTE FUNCTION impedir_alteracao_dados_subida_carregamento();

DROP TRIGGER IF EXISTS trg_carregamento_estornos_immutavel ON carregamento_estornos;
CREATE TRIGGER trg_carregamento_estornos_immutavel
    BEFORE UPDATE OR DELETE ON carregamento_estornos
    FOR EACH ROW EXECUTE FUNCTION impedir_alteracao_trilha_carregamento();

DROP TRIGGER IF EXISTS trg_carregamento_subida_alocacoes_immutavel ON carregamento_subida_alocacoes;
CREATE TRIGGER trg_carregamento_subida_alocacoes_immutavel
    BEFORE UPDATE OR DELETE ON carregamento_subida_alocacoes
    FOR EACH ROW EXECUTE FUNCTION impedir_alteracao_trilha_carregamento();

DROP TRIGGER IF EXISTS trg_carregamento_fotos_immutavel ON carregamento_fotos;
CREATE TRIGGER trg_carregamento_fotos_immutavel
    BEFORE UPDATE OR DELETE ON carregamento_fotos
    FOR EACH ROW EXECUTE FUNCTION impedir_alteracao_trilha_carregamento();

COMMIT;
