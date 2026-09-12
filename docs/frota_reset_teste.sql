BEGIN;

-- Reset operacional para testes. Preserva motoristas, veiculos e clientes mestres.
DELETE FROM frota_operacao_offline;
DELETE FROM frota_entrega_timeline;
DELETE FROM frota_acerto_item
 WHERE acerto_pedido_id IN (
     SELECT id FROM frota_acerto_pedido
     WHERE acerto_id IN (SELECT id FROM frota_acerto_embarque)
 );
DELETE FROM frota_acerto_pedido
 WHERE acerto_id IN (SELECT id FROM frota_acerto_embarque);
DELETE FROM frota_acerto_embarque;
DELETE FROM frota_checklist_entrega;
DELETE FROM frota_entrega_foto;
DELETE FROM frota_entrega_problema;
DELETE FROM frota_ocorrencia;
DELETE FROM frota_checkin;
DELETE FROM frota_historico_posicao;
DELETE FROM frota_log_entrega;
DELETE FROM frota_log_embarque;
DELETE FROM frota_notificacao;
DELETE FROM frota_entrega;
DELETE FROM frota_embarque;

-- Revise os totais acima antes de confirmar no banco de testes.
-- COMMIT;
ROLLBACK;