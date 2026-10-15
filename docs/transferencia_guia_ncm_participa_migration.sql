-- A guia passou a considerar apenas os NCMs cadastrados como participantes.
-- Remove as regras "sem ST PR" importadas da planilha: NCM sem regra e ignorado.
DELETE FROM guia_transferencia_ncm_regra
WHERE st_pr = FALSE AND atualizado_por = 'planilha';
