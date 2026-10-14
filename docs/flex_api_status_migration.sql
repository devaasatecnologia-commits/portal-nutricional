-- Historico de disponibilidade da API Mercos (/saldo_flex). Uma linha por teste.
-- O cron grava uma linha por filial por minuto; o botao "Testar agora" grava com origem 'manual'.
-- Retencao: o cron apaga linhas com mais de 30 dias.

CREATE TABLE IF NOT EXISTS flex_api_status_log (
    id BIGSERIAL PRIMARY KEY,
    filial INT NOT NULL,
    em TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    ok BOOLEAN NOT NULL,
    http INT,
    ms INT,
    erro TEXT,
    origem VARCHAR(10) NOT NULL DEFAULT 'cron'
);
CREATE INDEX IF NOT EXISTS idx_flex_api_status_filial_em ON flex_api_status_log (filial, em DESC);
