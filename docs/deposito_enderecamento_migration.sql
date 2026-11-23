-- Chamado #70: Tabela de endereçamento fixo (Giro / Pulmão) por item
CREATE TABLE IF NOT EXISTS item_endereco (
    id SERIAL PRIMARY KEY,
    iditem INTEGER NOT NULL REFERENCES item(iditem) ON DELETE CASCADE,
    idsecao INTEGER NOT NULL,
    idendereco INTEGER NOT NULL,
    tipo VARCHAR(20) NOT NULL DEFAULT 'GIRO',
    prioridade INTEGER NOT NULL DEFAULT 1,
    observacao VARCHAR(255) NULL,
    atualizado_por VARCHAR(60) NULL,
    atualizado_em TIMESTAMP NOT NULL DEFAULT NOW(),
    UNIQUE (iditem, idsecao, idendereco)
);

CREATE INDEX IF NOT EXISTS idx_item_endereco_item ON item_endereco (iditem, prioridade);
CREATE INDEX IF NOT EXISTS idx_item_endereco_end ON item_endereco (idsecao, idendereco);
