-- Migration: Criação da tabela de Metas Financeiras Personalizadas (fin_metas)
-- Data: 09/11/2026

CREATE TABLE IF NOT EXISTS fin_metas (
    id SERIAL PRIMARY KEY,
    tipo_indicador VARCHAR(50) NOT NULL,
    titulo VARCHAR(150) NOT NULL,
    descricao TEXT,
    idfilial INT DEFAULT 0,
    valor_meta NUMERIC(14,2) NOT NULL,
    direcao VARCHAR(20) DEFAULT 'menor_melhor', -- 'menor_melhor' ou 'maior_melhor'
    tolerancia_alerta NUMERIC(14,2) DEFAULT 0,
    data_inicio DATE NOT NULL,
    data_fim DATE NOT NULL,
    status VARCHAR(20) DEFAULT 'ativa', -- 'ativa', 'pausada', 'concluida', 'cancelada'
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_fin_metas_status ON fin_metas(status);
CREATE INDEX IF NOT EXISTS idx_fin_metas_periodo ON fin_metas(data_inicio, data_fim);
CREATE INDEX IF NOT EXISTS idx_fin_metas_filial ON fin_metas(idfilial);
