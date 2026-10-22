-- Migração para regras de estoque do Pedido de Amostra / Material de Apoio
-- Permite configurar percentual padrão liberado para a Filial 6 (PR) e regras específicas por item.

CREATE TABLE IF NOT EXISTS amostra_estoque_config (
    id SERIAL PRIMARY KEY,
    filial_origem INTEGER NOT NULL DEFAULT 1,
    filial_destino INTEGER NOT NULL DEFAULT 6,
    percentual_padrao NUMERIC(5,2) NOT NULL DEFAULT 30.00,
    atualizado_por VARCHAR(100),
    atualizado_em TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

INSERT INTO amostra_estoque_config (filial_origem, filial_destino, percentual_padrao, atualizado_por)
SELECT 1, 6, 30.00, 'SISTEMA'
WHERE NOT EXISTS (SELECT 1 FROM amostra_estoque_config WHERE filial_origem = 1 AND filial_destino = 6);

CREATE TABLE IF NOT EXISTS amostra_estoque_regra_item (
    id SERIAL PRIMARY KEY,
    iditem INTEGER NOT NULL,
    filial_destino INTEGER NOT NULL DEFAULT 6,
    percentual_liberado NUMERIC(5,2) NOT NULL,
    ativo BOOLEAN NOT NULL DEFAULT TRUE,
    motivo VARCHAR(255),
    atualizado_por VARCHAR(100),
    atualizado_em TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    CONSTRAINT unq_amostra_regra_item UNIQUE (iditem, filial_destino)
);
