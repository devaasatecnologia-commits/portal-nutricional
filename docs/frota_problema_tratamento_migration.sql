-- ============================================================================
-- MIGRATION: Estrutura de Tratamento de Problemas de Frota
-- ============================================================================
-- Objetivo:
--   Separar em 3 camadas o ciclo de vida de um problema de entrega:
--     1. FATO       → frota_entrega_problema (o que o motorista reportou)
--     2. TRATAMENTO → frota_problema_tratamento (o que o gestor decidiu)
--     3. DOCUMENTO  → frota_acerto_pedido / frota_acerto_item (o que foi ao ERP)
--
-- Contexto de negócio:
--   - Faltante passa a ter 2 variações: com estoque (transação 19) e
--     sem estoque (transação 20).
--   - Devolução deixa de gerar pedido ERP e passa a gerar comprovante
--     para faturamento (numeração DEV-AAAA-NNNNNN).
--
-- Segurança:
--   - Idempotente: pode rodar várias vezes sem erro.
--   - Não altera nem apaga dados existentes.
--   - Migração retroativa preserva histórico dos pedidos antigos.
--
-- Autor: Migração arquitetural — sessão 2026-09-17
-- ============================================================================

BEGIN;

-- ============================================================================
-- 1. TABELA: frota_problema_tratamento
-- ============================================================================
-- Registra cada decisão tomada pelo gestor sobre um problema reportado.
-- Append-only: para alterar uma decisão, insere-se uma nova linha com
-- status 'cancelado' na anterior — preservando trilha de auditoria.
-- ============================================================================

CREATE TABLE IF NOT EXISTS frota_problema_tratamento (
    id SERIAL PRIMARY KEY,

    -- Vínculo com o FATO (problema reportado pelo motorista)
    problema_id INTEGER NOT NULL
        REFERENCES frota_entrega_problema(id) ON DELETE CASCADE,

    -- Tipo de tratamento decidido pelo gestor
    -- Valores esperados:
    --   'faltante_com_estoque'    → transação ERP 19
    --   'faltante_sem_estoque'    → transação ERP 20
    --   'devolucao_comprovante'   → gera comprovante p/ faturamento
    tipo_tratamento VARCHAR(40) NOT NULL,

    -- Snapshot da transação ERP usada (para auditoria fiscal)
    id_transacao_erp INTEGER,
    id_filial_erp INTEGER,
    transacao_descricao_snapshot VARCHAR(200),

    -- Vínculo com o DOCUMENTO (pedido de acerto criado)
    acerto_pedido_id INTEGER
        REFERENCES frota_acerto_pedido(id) ON DELETE SET NULL,

    -- Número sequencial do comprovante (só para devolução)
    numero_comprovante VARCHAR(20),

    -- Valor financeiro afetado neste tratamento
    valor_afetado NUMERIC(12,2),

    -- Ciclo de vida do tratamento
    -- Valores esperados:
    --   'pendente'              → decisão tomada, aguardando ação
    --   'processando'           → enviando ao ERP
    --   'criado_erp'            → pedido criado com sucesso
    --   'aguardando_fat'        → devolução aguardando faturamento
    --   'comprovante_emitido'   → comprovante gerado
    --   'cancelado'             → tratamento desfeito
    status VARCHAR(30) NOT NULL DEFAULT 'pendente',

    -- Auditoria: quem decidiu e quando
    decidido_por INTEGER,
    decidido_em TIMESTAMP,

    -- Auditoria: quem emitiu comprovante e quando (só devolução)
    comprovante_emitido_em TIMESTAMP,
    comprovante_emitido_por INTEGER,

    -- Observações livres
    observacoes TEXT,

    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP NOT NULL DEFAULT NOW()
);

-- Índices para relatórios e consultas frequentes
CREATE INDEX IF NOT EXISTS idx_problema_trat_problema
    ON frota_problema_tratamento(problema_id);

CREATE INDEX IF NOT EXISTS idx_problema_trat_status
    ON frota_problema_tratamento(status);

CREATE INDEX IF NOT EXISTS idx_problema_trat_tipo
    ON frota_problema_tratamento(tipo_tratamento);

CREATE INDEX IF NOT EXISTS idx_problema_trat_transacao
    ON frota_problema_tratamento(id_transacao_erp, id_filial_erp);

CREATE INDEX IF NOT EXISTS idx_problema_trat_comprovante
    ON frota_problema_tratamento(numero_comprovante)
    WHERE numero_comprovante IS NOT NULL;

CREATE INDEX IF NOT EXISTS idx_problema_trat_acerto
    ON frota_problema_tratamento(acerto_pedido_id)
    WHERE acerto_pedido_id IS NOT NULL;

-- Comentários na tabela (documentação viva)
COMMENT ON TABLE frota_problema_tratamento IS
    'Camada 2 (TRATAMENTO): decisões tomadas pelo gestor sobre problemas reportados. Append-only para auditoria.';

COMMENT ON COLUMN frota_problema_tratamento.tipo_tratamento IS
    'Valores: faltante_com_estoque | faltante_sem_estoque | devolucao_comprovante';

COMMENT ON COLUMN frota_problema_tratamento.status IS
    'Ciclo de vida: pendente | processando | criado_erp | aguardando_fat | comprovante_emitido | cancelado';

COMMENT ON COLUMN frota_problema_tratamento.transacao_descricao_snapshot IS
    'Snapshot da descrição da transação ERP no momento da decisão (protege contra mudanças futuras no ERP).';


-- ============================================================================
-- 2. TABELA: frota_comprovante_sequencia
-- ============================================================================
-- Controla a numeração sequencial de comprovantes de devolução (DEV-AAAA-NNNNNN).
-- Uma linha por filial + ano. Sem risco de race condition (usa UPDATE atômico).
-- ============================================================================

CREATE TABLE IF NOT EXISTS frota_comprovante_sequencia (
    id SERIAL PRIMARY KEY,
    id_filial INTEGER NOT NULL,
    ano INTEGER NOT NULL,
    ultimo_numero INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP NOT NULL DEFAULT NOW(),
    UNIQUE (id_filial, ano)
);

CREATE INDEX IF NOT EXISTS idx_comprovante_seq_filial_ano
    ON frota_comprovante_sequencia(id_filial, ano);

COMMENT ON TABLE frota_comprovante_sequencia IS
    'Controla numeração sequencial de comprovantes de devolução por filial/ano (DEV-AAAA-NNNNNN).';


-- ============================================================================
-- 3. ALTERAÇÕES EM TABELAS EXISTENTES (Camada 3 — DOCUMENTO)
-- ============================================================================

-- 3.1 frota_acerto_pedido: campos espelho para consulta rápida no acerto
ALTER TABLE frota_acerto_pedido
    ADD COLUMN IF NOT EXISTS tipo_tratamento VARCHAR(40);

ALTER TABLE frota_acerto_pedido
    ADD COLUMN IF NOT EXISTS id_transacao_erp INTEGER;

ALTER TABLE frota_acerto_pedido
    ADD COLUMN IF NOT EXISTS id_filial_erp INTEGER;

ALTER TABLE frota_acerto_pedido
    ADD COLUMN IF NOT EXISTS transacao_descricao_snapshot VARCHAR(200);

ALTER TABLE frota_acerto_pedido
    ADD COLUMN IF NOT EXISTS numero_comprovante VARCHAR(20);

-- Índice para relatórios na Camada 3
CREATE INDEX IF NOT EXISTS idx_acerto_pedido_tipo_trat
    ON frota_acerto_pedido(tipo_tratamento)
    WHERE tipo_tratamento IS NOT NULL;

CREATE INDEX IF NOT EXISTS idx_acerto_pedido_comprovante
    ON frota_acerto_pedido(numero_comprovante)
    WHERE numero_comprovante IS NOT NULL;

-- 3.2 frota_acerto_item: coluna tipo_faltante (projeção para relatórios)
ALTER TABLE frota_acerto_item
    ADD COLUMN IF NOT EXISTS tipo_faltante VARCHAR(30);

COMMENT ON COLUMN frota_acerto_item.tipo_faltante IS
    'Projeção do tipo_faltante: com_estoque | sem_estoque | NULL. Fonte da verdade está em frota_problema_tratamento.';


-- ============================================================================
-- 4. MIGRAÇÃO RETROATIVA
-- ============================================================================
-- Preserva histórico dos pedidos criados ANTES desta migração.
-- Como o comportamento antigo era:
--   faltante  → sempre movimenta estoque (transação 19)
--   devolucao → gerava pedido ERP (transação 20)
-- Vamos classificar os registros antigos assim:
--   pedidos com tipo_problema='faltante' → tipo_tratamento='faltante_com_estoque'
--   pedidos com tipo_problema='devolucao' → tipo_tratamento='devolucao_comprovante'
--     (mesmo que tenham gerado pedido ERP no passado, agora são tratados
--      como comprovante — decisão de negócio da migração)
-- ============================================================================

-- 4.1 Classificar pedidos de acerto antigos que ainda não têm tipo_tratamento
UPDATE frota_acerto_pedido
SET tipo_tratamento = CASE
    WHEN tipo_problema = 'faltante'  THEN 'faltante_com_estoque'
    WHEN tipo_problema = 'devolucao' THEN 'devolucao_comprovante'
    ELSE NULL
END
WHERE tipo_tratamento IS NULL
  AND tipo_problema IS NOT NULL;

-- 4.2 Propagar id_transacao_erp com base no tipo_problema antigo
UPDATE frota_acerto_pedido
SET id_transacao_erp = CASE
    WHEN tipo_problema = 'faltante'  THEN 19
    WHEN tipo_problema = 'devolucao' THEN 20
    ELSE NULL
END
WHERE id_transacao_erp IS NULL
  AND tipo_problema IS NOT NULL;

-- 4.3 Propagar tipo_faltante em frota_acerto_item (retroativo)
UPDATE frota_acerto_item ai
SET tipo_faltante = 'com_estoque'
FROM frota_acerto_pedido ap
WHERE ai.acerto_pedido_id = ap.id
  AND ap.tipo_problema = 'faltante'
  AND ai.tipo_faltante IS NULL;

-- Nota: NÃO criamos linhas em frota_problema_tratamento para pedidos antigos
-- porque não temos a granularidade do vínculo com frota_entrega_problema_id
-- naquele momento. Os espelhos em frota_acerto_pedido já cobrem o histórico.


-- ============================================================================
-- 5. VERIFICAÇÕES PÓS-MIGRAÇÃO
-- ============================================================================
-- Execute estes SELECTs manualmente para validar a migração.

-- 5.1 Deve retornar 3 tabelas/índices criados
-- SELECT tablename FROM pg_tables
-- WHERE tablename IN ('frota_problema_tratamento','frota_comprovante_sequencia');

-- 5.2 Deve retornar 5 colunas novas em frota_acerto_pedido
-- SELECT column_name FROM information_schema.columns
-- WHERE table_name = 'frota_acerto_pedido'
--   AND column_name IN (
--     'tipo_tratamento','id_transacao_erp','id_filial_erp',
--     'transacao_descricao_snapshot','numero_comprovante');

-- 5.3 Deve retornar 1 coluna nova em frota_acerto_item
-- SELECT column_name FROM information_schema.columns
-- WHERE table_name = 'frota_acerto_item'
--   AND column_name = 'tipo_faltante';

-- 5.4 Deve retornar 0 linhas (nenhum pedido antigo ficou sem classificação)
-- SELECT COUNT(*) FROM frota_acerto_pedido
-- WHERE tipo_problema IN ('faltante','devolucao')
--   AND tipo_tratamento IS NULL;

-- 5.5 Contagem retroativa (para conferência)
-- SELECT tipo_problema, tipo_tratamento, COUNT(*)
-- FROM frota_acerto_pedido
-- GROUP BY tipo_problema, tipo_tratamento
-- ORDER BY tipo_problema, tipo_tratamento;


COMMIT;

-- ============================================================================
-- FIM DA MIGRATION
-- ============================================================================
-- Próximos passos (outros passos deste bloco):
--   - Bloco 1, Passo 1.2: Documentar transações 19/20 no token de continuidade
--   - Bloco 2: Adaptar AcertoEmbarqueController.php para usar a nova estrutura
-- ============================================================================