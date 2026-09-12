CREATE TABLE IF NOT EXISTS frota_operacao_offline (
    operation_id VARCHAR(160) PRIMARY KEY,
    entrega_id INTEGER NOT NULL,
    acao VARCHAR(30) NOT NULL,
    response_body JSONB NOT NULL,
    status_code SMALLINT NOT NULL DEFAULT 200,
    created_at TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_frota_operacao_offline_entrega
    ON frota_operacao_offline (entrega_id);

CREATE INDEX IF NOT EXISTS idx_frota_operacao_offline_created_at
    ON frota_operacao_offline (created_at);