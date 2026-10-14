-- Automacao "Flex minimo do gestor" (substitui enviaflexminimo / enviaflexminimo2 do ERP).
-- Os registros nascem INATIVOS: ative somente depois de desligar a rotina equivalente no ERP,
-- senao os dois podem emprestar ao mesmo tempo.

CREATE TABLE IF NOT EXISTS flex_minimo_config (
    id SERIAL PRIMARY KEY,
    gestor_id INT NOT NULL,
    nome VARCHAR(120) NOT NULL,
    filial INT NOT NULL,
    valor_minimo NUMERIC(14,2) NOT NULL DEFAULT 1000,
    valor_emprestimo NUMERIC(14,2) NOT NULL DEFAULT 1000,
    max_multiplos INT NOT NULL DEFAULT 5,
    motivo TEXT NOT NULL,
    emails TEXT,
    falhas_para_alertar INT NOT NULL DEFAULT 5,
    ativo BOOLEAN NOT NULL DEFAULT FALSE,
    ultima_verificacao TIMESTAMPTZ,
    ultimo_saldo NUMERIC(14,2),
    ultimo_emprestimo_em TIMESTAMPTZ,
    falhas_seguidas INT NOT NULL DEFAULT 0,
    ultimo_erro TEXT,
    ultimo_erro_em TIMESTAMPTZ,
    alerta_falha_enviado BOOLEAN NOT NULL DEFAULT FALSE,
    UNIQUE (gestor_id, filial)
);

INSERT INTO flex_minimo_config (gestor_id, nome, filial, valor_minimo, valor_emprestimo, motivo)
VALUES
 (12335, 'TIAGO (MOV) - Gestor Matriz', 1, 3000, 3000, 'Empréstimo para o TIAGO (MOV) para Suprir desfalque de Verba Flex'),
 (15816, 'TALES - Gestor Filial', 6, 1000, 1000, 'Empréstimo para o Tales (Gestor Filial) para Suprir desfalque de Verba Flex')
ON CONFLICT (gestor_id, filial) DO NOTHING;
