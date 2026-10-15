-- Cofre de segredos, 2FA do Admin e auditoria. Rodar com o dono do banco
-- caso o usuario da aplicacao nao tenha permissao de CREATE TABLE
-- (as classes tentam criar sozinhas e seguem funcionando sem isso se falhar).
CREATE TABLE IF NOT EXISTS sistema_segredos (
    chave VARCHAR(80) PRIMARY KEY,
    valor_cifrado TEXT NOT NULL,
    atualizado_por VARCHAR(100),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS admin_2fa (
    idusuario INT PRIMARY KEY,
    segredo_cifrado TEXT NOT NULL,
    ativo BOOLEAN NOT NULL DEFAULT false,
    ultimo_contador BIGINT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS admin_segredos_log (
    id BIGSERIAL PRIMARY KEY,
    chave VARCHAR(80) NOT NULL,
    acao VARCHAR(20) NOT NULL,
    idusuario INT,
    username VARCHAR(100),
    ip VARCHAR(45),
    sucesso BOOLEAN NOT NULL DEFAULT true,
    created_at TIMESTAMP DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_admin_segredos_log_falhas ON admin_segredos_log (idusuario, created_at) WHERE sucesso = false;
