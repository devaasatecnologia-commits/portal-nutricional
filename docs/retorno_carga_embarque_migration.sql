-- Vínculo explícito do retorno com o embarque de origem
ALTER TABLE retorno_carga ADD COLUMN IF NOT EXISTS idembarque integer;
CREATE INDEX IF NOT EXISTS idx_retorno_carga_idembarque ON retorno_carga (idembarque) WHERE idembarque IS NOT NULL;
