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

-- Politica de seguranca por usuario (2FA no login, inatividade, duracao do token)
CREATE TABLE IF NOT EXISTS usuario_seguranca (
    idusuario INT PRIMARY KEY,
    exige_2fa_login BOOLEAN NOT NULL DEFAULT false,
    inatividade_min INT NOT NULL DEFAULT 10,
    token_min INT NOT NULL DEFAULT 240,
    atualizado_por VARCHAR(100),
    atualizado_em TIMESTAMP DEFAULT NOW()
);
ALTER TABLE usuario_seguranca ADD COLUMN IF NOT EXISTS whatsapp VARCHAR(20);

-- Codigo de verificacao por WhatsApp (5 min, uso unico, so o hash e guardado)
CREATE TABLE IF NOT EXISTS admin_whatsapp_codigo (
    idusuario INT PRIMARY KEY,
    hash VARCHAR(64) NOT NULL,
    expira TIMESTAMP NOT NULL,
    tentativas INT NOT NULL DEFAULT 0,
    enviado_em TIMESTAMP NOT NULL DEFAULT NOW()
);

-- Cofre pessoal de senhas (cifrado com chave derivada da senha mestra; nada em claro)
CREATE TABLE IF NOT EXISTS cofre_mestre (
    idusuario INT PRIMARY KEY,
    salt TEXT NOT NULL,
    verificador TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS cofre_itens (
    id BIGSERIAL PRIMARY KEY,
    idusuario INT NOT NULL,
    titulo VARCHAR(120) NOT NULL,
    dados_cifrados TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_cofre_itens_usuario ON cofre_itens (idusuario);
