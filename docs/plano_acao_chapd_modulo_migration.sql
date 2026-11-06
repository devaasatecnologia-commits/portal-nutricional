-- =============================================================================
-- MIGRAÇÃO: Plano de Ação 5W2H & PDI Especialista (70-20-10) integrado ao C.H.A.P.D
-- Data: 2026-11-05
-- =============================================================================

-- 1. Permitir múltiplas avaliações mensais por colaborador (a última do mês é a principal vigente)
ALTER TABLE avaliacao_chapd DROP CONSTRAINT IF EXISTS uk_chapd_colab_periodo;
DROP INDEX IF EXISTS uk_chapd_colab_periodo;
CREATE INDEX IF NOT EXISTS idx_chapd_colab_periodo_id ON avaliacao_chapd (idcliforemp, ano, mes, id DESC);

-- 2. Adicionar flag requer_pdi e campos de ciência/feedback do colaborador na avaliação
ALTER TABLE avaliacao_chapd ADD COLUMN IF NOT EXISTS requer_pdi BOOLEAN DEFAULT FALSE;
ALTER TABLE avaliacao_chapd ADD COLUMN IF NOT EXISTS feedback_colaborador TEXT;
ALTER TABLE avaliacao_chapd ADD COLUMN IF NOT EXISTS data_ciencia_colaborador TIMESTAMP;

-- 3. Criar tabela de cabeçalho do Plano de Ação 5W2H & PDI Especialista
CREATE TABLE IF NOT EXISTS avaliacao_chapd_plano_acao (
    id SERIAL PRIMARY KEY,
    idavaliacao INTEGER NULL REFERENCES avaliacao_chapd(id) ON DELETE SET NULL,
    idcliforemp INTEGER NULL,
    nome_colaborador VARCHAR(200),
    cargo VARCHAR(150),
    setor VARCHAR(120),
    idfilial INTEGER DEFAULT 1,
    ano INTEGER,
    mes INTEGER,
    pontuacao_origem NUMERIC(6,2),
    conceito_origem VARCHAR(10),
    meta_pontuacao NUMERIC(6,2) DEFAULT 90.00,
    prazo_dias_reavaliacao INTEGER DEFAULT 30,
    pilares_criticos VARCHAR(50),
    origem_tipo VARCHAR(40) DEFAULT 'PDI_ABAIXO_85',
    problema TEXT NOT NULL,
    objetivo TEXT NOT NULL,
    responsavel VARCHAR(150) NOT NULL DEFAULT 'Sandro Rafo',
    aprovador VARCHAR(150) DEFAULT 'Robson Becker',
    revisao VARCHAR(100),
    status_geral VARCHAR(40) DEFAULT 'DENTRO_DO_PRAZO',
    observacoes_gerais TEXT,
    feedback_colaborador TEXT,
    data_ciencia_colaborador TIMESTAMP,
    criado_por VARCHAR(100),
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE avaliacao_chapd_plano_acao ADD COLUMN IF NOT EXISTS meta_pontuacao NUMERIC(6,2) DEFAULT 90.00;
ALTER TABLE avaliacao_chapd_plano_acao ADD COLUMN IF NOT EXISTS prazo_dias_reavaliacao INTEGER DEFAULT 30;
ALTER TABLE avaliacao_chapd_plano_acao ADD COLUMN IF NOT EXISTS pilares_criticos VARCHAR(50);
ALTER TABLE avaliacao_chapd_plano_acao ADD COLUMN IF NOT EXISTS feedback_colaborador TEXT;
ALTER TABLE avaliacao_chapd_plano_acao ADD COLUMN IF NOT EXISTS data_ciencia_colaborador TIMESTAMP;

CREATE INDEX IF NOT EXISTS idx_pa_chapd_idavaliacao ON avaliacao_chapd_plano_acao(idavaliacao);
CREATE INDEX IF NOT EXISTS idx_pa_chapd_idcliforemp ON avaliacao_chapd_plano_acao(idcliforemp);
CREATE INDEX IF NOT EXISTS idx_pa_chapd_status ON avaliacao_chapd_plano_acao(status_geral);

-- 4. Criar tabela de etapas 5W2H + Metodologia PDI 70-20-10 + Vínculo Pilar C.H.A.P.D
CREATE TABLE IF NOT EXISTS avaliacao_chapd_plano_acao_item (
    id SERIAL PRIMARY KEY,
    idplano INTEGER NOT NULL REFERENCES avaliacao_chapd_plano_acao(id) ON DELETE CASCADE,
    ordem INTEGER DEFAULT 1,
    pilar_chapd VARCHAR(10) DEFAULT 'GERAL',
    tipo_702010 VARCHAR(30) DEFAULT '70_PRATICA',
    o_que TEXT NOT NULL,
    como TEXT,
    onde VARCHAR(150) DEFAULT 'NTD',
    quem VARCHAR(150),
    data_inicio DATE,
    data_termino DATE,
    status VARCHAR(50) DEFAULT 'Dentro do Prazo',
    observacao TEXT,
    evidencia_status VARCHAR(10) DEFAULT 'P',
    evidencia_auditoria TEXT,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE avaliacao_chapd_plano_acao_item ADD COLUMN IF NOT EXISTS pilar_chapd VARCHAR(10) DEFAULT 'GERAL';
ALTER TABLE avaliacao_chapd_plano_acao_item ADD COLUMN IF NOT EXISTS tipo_702010 VARCHAR(30) DEFAULT '70_PRATICA';

CREATE INDEX IF NOT EXISTS idx_pa_item_idplano ON avaliacao_chapd_plano_acao_item(idplano);

-- 5. Registrar o segundo módulo no portal (sistema_modulos)
INSERT INTO sistema_modulos (slug, nome, descricao, icon, cor_bg, cor_text, url, ordem, ativo, setor)
SELECT
    'plano-acao-chapd',
    'Plano de Ação & PDI',
    'Gestão de Planos de Ação 5W2H e PDI de Colaboradores C.H.A.P.D',
    'fa-list-check',
    'bg-rose-50',
    'text-rose-700',
    '/portal/modules/plano-acao-chapd.php',
    86,
    TRUE,
    'Recursos Humanos'
WHERE NOT EXISTS (
    SELECT 1 FROM sistema_modulos WHERE slug = 'plano-acao-chapd'
);

-- 6. Conceder permissão automaticamente aos usuários que já possuem acesso a avaliacao-chapd
INSERT INTO usuario_permissoes (idusuario, idmodulo)
SELECT DISTINCT up.idusuario, m_novo.id
FROM usuario_permissoes up
INNER JOIN sistema_modulos m_orig ON m_orig.id = up.idmodulo AND m_orig.slug = 'avaliacao-chapd'
CROSS JOIN sistema_modulos m_novo
WHERE m_novo.slug = 'plano-acao-chapd'
  AND NOT EXISTS (
      SELECT 1 FROM usuario_permissoes ex
      WHERE ex.idusuario = up.idusuario AND ex.idmodulo = m_novo.id
  );
