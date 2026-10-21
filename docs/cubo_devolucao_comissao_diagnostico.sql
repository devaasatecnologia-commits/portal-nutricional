-- ============================================================================ 
-- DIAGNÓSTICO SOMENTE-LEITURA — Comissão x Devoluções (Cubo de Vendas)
-- ============================================================================
-- Objetivo: confirmar no banco AUTORIZADO o schema necessário para abater
-- comissão de devolução de venda no Cubo de Vendas (VendasCuboController).
--
-- Contexto (fatos do código, 2026-10-19):
--   - Comissão hoje = SUM(nfs_item.valor_comissao) das NFs de SAÍDA
--     (nfs.status = 2 AND nfs.idtransacao = 1). Ver VendasCuboController.
--   - Devolução de venda = NF de ENTRADA (nfe) com transações
--     8,9,1000,1004,1005,1007,1015,1020,1024,1025,1031,1032,1033
--     (constante TRANS_DEVOLUCAO em VendasDashboardController; bonificações
--     21,1010,1011,1016,1028 ficam de fora).
--   - NÃO há no repositório nenhum uso de nfe_item nem de vínculo
--     devolução → nota de venda. Este script confirma essas duas coisas.
--
-- NÃO ALTERA NADA. Execute no ambiente autorizado (dev/homologação), cole a
-- saída e devolva para implementação do abatimento.
-- ============================================================================

-- ----------------------------------------------------------------------------
-- Q1) Colunas de NFS_ITEM e NFE_ITEM (existe nfe_item? tem valor_comissao?
--     tem coluna de vínculo com a venda, ex.: idnfs / idnfs_item / idpedido?)
-- ----------------------------------------------------------------------------
SELECT c.table_name,
       c.ordinal_position,
       c.column_name,
       c.data_type
FROM information_schema.columns c
WHERE c.table_name IN ('nfs_item', 'nfe_item')
ORDER BY c.table_name, c.ordinal_position;

-- ----------------------------------------------------------------------------
-- Q2) Colunas de vínculo/interesse nas cabeçalhos NFS e NFE
--     (procura idnfs, idpedido, origem, devol, comiss, data)
-- ----------------------------------------------------------------------------
SELECT table_name,
       column_name,
       data_type
FROM information_schema.columns
WHERE table_name IN ('nfs', 'nfe')
  AND (column_name ILIKE '%idnfs%'
    OR column_name ILIKE '%idpedido%'
    OR column_name ILIKE '%devol%'
    OR column_name ILIKE '%comiss%'
    OR column_name ILIKE '%origem%'
    OR column_name ILIKE '%data%')
ORDER BY table_name, column_name;

-- ----------------------------------------------------------------------------
-- Q3) Chaves estrangeiras: quem liga nfe/nfe_item a nfs/pedido?
--     (autoritativo: mostra a coluna de vínculo real, se existir FK)
-- ----------------------------------------------------------------------------
SELECT tc.table_name,
       kcu.column_name,
       ccu.table_name  AS tabela_referenciada,
       ccu.column_name AS coluna_referenciada
FROM information_schema.table_constraints tc
JOIN information_schema.key_column_usage kcu
       ON kcu.constraint_name = tc.constraint_name
JOIN information_schema.constraint_column_usage ccu
       ON ccu.constraint_name = tc.constraint_name
WHERE tc.constraint_type = 'FOREIGN KEY'
  AND (tc.table_name   IN ('nfs', 'nfs_item', 'nfe', 'nfe_item')
    OR ccu.table_name  IN ('nfs', 'nfs_item', 'nfe', 'nfe_item'))
ORDER BY tc.table_name, kcu.column_name;

-- ----------------------------------------------------------------------------
-- Q4) Catálogos de transação (identificar devolução/bonificação por descrição)
-- ----------------------------------------------------------------------------
SELECT idtransacao, descricao
FROM nfs_transacao
ORDER BY idtransacao;

SELECT idtransacao, descricao
FROM nfe_transacao
ORDER BY idtransacao;

-- ----------------------------------------------------------------------------
-- Q5) Amostra de devoluções recentes (somente colunas já comprovadas)
--     Observar: as transações de exemplo do ERP usam DATAENTRADA no filtro,
--     enquanto o Dashboard usa DATAEMISSAO — confirmar qual rege o abatimento.
-- ----------------------------------------------------------------------------
SELECT n.idnfe,
       n.dataemissao,
       n.dataentrada,
       n.idtransacao,
       t.descricao     AS transacao,
       n.status,
       n.idfilial,
       n.idcliforemp,
       c.fantasia      AS cliente,
       n.idvendrepre,
       v.fantasia      AS representante,
       n.valortotalnota
FROM nfe n
JOIN nfe_transacao t ON t.idtransacao = n.idtransacao
JOIN cliforemp   c  ON c.idcliforemp  = n.idcliforemp
LEFT JOIN cliforemp v ON v.idcliforemp = n.idvendrepre
WHERE n.status = 2
  AND n.idtransacao IN (8,9,1000,1004,1005,1007,1015,1020,1024,1025,1031,1032,1033)
  AND n.dataemissao BETWEEN '2026-09-01' AND '2026-10-31'
ORDER BY n.dataemissao DESC
LIMIT 50;

-- ----------------------------------------------------------------------------
-- Q6) Conferência de comissão na saída (base atual do Cubo) — mesmo período,
--     para comparar com o abatimento depois da implementação.
-- ----------------------------------------------------------------------------
SELECT n.idnfs,
       n.dataemissao,
       n.numeronf,
       n.idtransacao,
       n.idcliforemp,
       COUNT(*)                         AS itens,
       SUM(ni.qt)                       AS quantidade,
       SUM(ni.valor_comissao)           AS comissao
FROM nfs n
JOIN nfs_item ni ON ni.idnfs = n.idnfs
WHERE n.status = 2
  AND n.idtransacao = 1
  AND n.dataemissao BETWEEN '2026-09-01' AND '2026-10-31'
GROUP BY n.idnfs, n.dataemissao, n.numeronf, n.idtransacao, n.idcliforemp
ORDER BY n.dataemissao DESC
LIMIT 50;
