<?php
// src/Controllers/Frota/EntregaController.php

namespace Nutricional\Controllers\Frota;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Nutricional\Services\Frota\GeocodingService;

class EntregaController
{
    private $pdo;
    private $geocodingService;
    
    public function __construct()
    {
        $this->pdo = \getPDO();
        $this->geocodingService = new GeocodingService();
    }
    
    /**
     * GET /v1/frota/entregas
     * Listar entregas com filtros avançados
     */
    public function listar(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        
        $filtros = [];
        $bindParams = [];
        
        if (!empty($params['status'])) {
            $filtros[] = "e.status = :status";
            $bindParams['status'] = $params['status'];
        }
        if (!empty($params['embarque_id'])) {
            $filtros[] = "e.embarque_id = :embarque_id";
            $bindParams['embarque_id'] = (int)$params['embarque_id'];
        }
        if (!empty($params['motorista_id'])) {
            $filtros[] = "eb.motorista_id = :motorista_id";
            $bindParams['motorista_id'] = (int)$params['motorista_id'];
        }
        if (!empty($params['cliente_id'])) {
            $filtros[] = "e.cliente_id = :cliente_id";
            $bindParams['cliente_id'] = (int)$params['cliente_id'];
        }
        if (!empty($params['data_inicio']) && !empty($params['data_fim'])) {
            $filtros[] = "DATE(e.created_at) BETWEEN :data_inicio AND :data_fim";
            $bindParams['data_inicio'] = $params['data_inicio'];
            $bindParams['data_fim'] = $params['data_fim'];
        }
        if (!empty($params['busca'])) {
            $filtros[] = "(e.cliente_nome ILIKE :busca OR e.codigo_rastreamento ILIKE :busca2)";
            $bindParams['busca'] = "%{$params['busca']}%";
            $bindParams['busca2'] = "%{$params['busca']}%";
        }
        
        $where = !empty($filtros) ? 'WHERE ' . implode(' AND ', $filtros) : '';
        
        $limite = (int)($params['limite'] ?? 20);
        $pagina = (int)($params['pagina'] ?? 1);
        $offset = ($pagina - 1) * $limite;
        
        $sql = "
        SELECT 
        e.*,
        eb.numero_embarque,
        eb.data_saida,
        m.nome as motorista_nome,
        m.telefone as motorista_telefone,
        v.placa,
        v.modelo,
        p.numero_pedido,
        p.valor_total as pedido_valor,
        status_geolocalizacao,
        origem_geolocalizacao,
        mensagem_geolocalizacao,
        data_geolocalizacao,
        CASE 
        WHEN status_geolocalizacao = 'pendente_geolocalizacao' THEN 'Aguardando geolocalização'
        WHEN status_geolocalizacao = 'pendente_confirmacao' THEN 'Confirmar no local'
        WHEN status_geolocalizacao = 'valido' THEN 'Coordenadas válidas'
        WHEN status_geolocalizacao = 'confirmado' THEN 'Confirmado no checkout'
        ELSE 'Desconhecido'
    END as status_geo_descricao
    FROM frota_entrega e
    LEFT JOIN frota_embarque eb ON eb.id = e.embarque_id
    LEFT JOIN frota_motorista m ON m.id = eb.motorista_id
    LEFT JOIN frota_veiculo v ON v.id = eb.veiculo_id
    LEFT JOIN frota_pedido p ON p.id = e.pedido_id
    {$where}
    ORDER BY e.ordem_entrega ASC, e.created_at DESC
    LIMIT :limite OFFSET :offset
    ";

    $stmt = $this->pdo->prepare($sql);
    foreach ($bindParams as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue(':limite', $limite, \PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
    $stmt->execute();
    $entregas = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    $sqlCount = "SELECT COUNT(*) FROM frota_entrega e {$where}";
    $stmtCount = $this->pdo->prepare($sqlCount);
    foreach ($bindParams as $key => $val) {
        $stmtCount->bindValue($key, $val);
    }
    $stmtCount->execute();
    $total = (int)$stmtCount->fetchColumn();

    return $this->json($response, [
        'success' => true,
        'data' => $entregas,
        'pagination' => [
            'total' => $total,
            'pagina' => $pagina,
            'limite' => $limite,
            'total_paginas' => ceil($total / $limite)
        ]
    ]);
}

    /**
 * GET /v1/frota/entregas/{id}
 * Buscar entrega específica com todos os detalhes
 */
    public function buscar(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];

    // ================================================================
    // 1. BUSCAR DADOS PRINCIPAIS DA ENTREGA
    // ================================================================
        $sql = "
        SELECT 
        e.*,
        eb.numero_embarque,
        eb.data_saida,
        eb.data_prevista_entrega,
        m.id as motorista_id,
        m.nome as motorista_nome,
        m.telefone as motorista_telefone,
        v.placa,
        v.modelo,
        v.tipo as veiculo_tipo,
        p.numero_pedido,
        p.valor_total as pedido_valor,
        p.peso_total as pedido_peso,
        p.volume_total as pedido_volume,
        c.nome as cliente_nome_completo,
        c.telefone as cliente_telefone,
        c.email as cliente_email,
        c.cnpj_cpf
        FROM frota_entrega e
        LEFT JOIN frota_embarque eb ON eb.id = e.embarque_id
        LEFT JOIN frota_motorista m ON m.id = eb.motorista_id
        LEFT JOIN frota_veiculo v ON v.id = eb.veiculo_id
        LEFT JOIN frota_pedido p ON p.id = e.pedido_id
        LEFT JOIN frota_cliente c ON c.id = e.cliente_id
        WHERE e.id = :id
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $entrega = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$entrega) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Entrega não encontrada'
            ], 404);
        }

    // ================================================================
    // 2. 🔥 BUSCAR CHECKLIST DA ENTREGA (ADICIONADO)
    // ================================================================
        $stmtChecklist = $this->pdo->prepare("
            SELECT 
            entrega_id,
            item_id,
            referencia,
            descricao,
            foto_url,
            quantidade_prevista,
            quantidade_entregue,
            status,
            motivo
            FROM frota_checklist_entrega
            WHERE entrega_id = :entrega_id
            ORDER BY item_id ASC
            ");
        $stmtChecklist->execute(['entrega_id' => $id]);
        $checklist = $stmtChecklist->fetchAll(\PDO::FETCH_ASSOC);

    // 🔥 ADICIONAR O CHECKLIST AO OBJETO DA ENTREGA
        $entrega['checklist'] = $checklist;

    // 🔥 LOG PARA VERIFICAR (veja no log do PHP)
        error_log('[Entrega-buscar] Checklist para entrega #' . $id . ': ' . count($checklist) . ' itens');
        if (count($checklist) > 0) {
            error_log('[Entrega-buscar] Primeiro item: ' . json_encode($checklist[0]));
        }

    // ================================================================
    // 3. BUSCAR CHECK-INS
    // ================================================================
        $stmt = $this->pdo->prepare("
            SELECT 
            tipo,
            latitude,
            longitude,
            foto_url,
            assinatura_url,
            observacoes,
            data_hora
            FROM frota_checkin
            WHERE entrega_id = :entrega_id
            ORDER BY data_hora ASC
            ");
        $stmt->execute(['entrega_id' => $id]);
        $entrega['checkins'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    // ================================================================
    // 4. BUSCAR OCORRÊNCIAS
    // ================================================================
        $stmt = $this->pdo->prepare("
            SELECT 
            tipo,
            descricao,
            status,
            created_at
            FROM frota_ocorrencia
            WHERE entrega_id = :entrega_id
            ORDER BY created_at DESC
            ");
        $stmt->execute(['entrega_id' => $id]);
        $entrega['ocorrencias'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    // ================================================================
    // 5. BUSCAR HISTÓRICO DE POSIÇÕES
    // ================================================================
        $stmt = $this->pdo->prepare("
            SELECT 
            latitude,
            longitude,
            velocidade,
            data_hora
            FROM frota_historico_posicao
            WHERE embarque_id = (SELECT embarque_id FROM frota_entrega WHERE id = :entrega_id)
            AND data_hora >= (SELECT created_at FROM frota_entrega WHERE id = :entrega_id)
            ORDER BY data_hora ASC
            ");
        $stmt->execute(['entrega_id' => $id]);
        $entrega['historico_posicoes'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    // ================================================================
    // 6. RESPOSTA
    // ================================================================
        return $this->json($response, [
            'success' => true,
            'data' => $entrega
        ]);
    }
    
    /**
     * GET /v1/frota/entregas/rastreamento/{codigo}
     * Buscar entrega por código de rastreamento (público para cliente)
     */
    public function buscarPorRastreamento(Request $request, Response $response, array $args): Response
    {
        $codigo = trim($args['codigo'] ?? '');
        if (empty($codigo)) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Código de rastreamento é obrigatório'
            ], 400);
        }
        
        $sql = "
        SELECT 
        e.id,
        e.codigo_rastreamento,
        e.cliente_nome,
        e.endereco,
        e.numero,
        e.bairro,
        e.cidade,
        e.uf,
        e.status,
        e.horario_checkin,
        e.horario_entrega,
        e.nome_recebedor,
        e.observacoes,
        e.foto_entrega_url,
        eb.numero_embarque,
        m.nome as motorista_nome,
        m.telefone as motorista_telefone,
        v.placa,
        v.modelo,
        v.latitude as veiculo_lat,
        v.longitude as veiculo_lng,
        v.ultima_posicao,
        (SELECT COUNT(*) FROM frota_entrega WHERE cliente_nome = e.cliente_nome AND status = 'entregue') as total_entregas_cliente
        FROM frota_entrega e
        LEFT JOIN frota_embarque eb ON eb.id = e.embarque_id
        LEFT JOIN frota_motorista m ON m.id = eb.motorista_id
        LEFT JOIN frota_veiculo v ON v.id = eb.veiculo_id
        WHERE e.codigo_rastreamento = :codigo
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['codigo' => $codigo]);
        $entrega = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$entrega) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Código de rastreamento não encontrado'
            ], 404);
        }
        
        $entrega['timeline'] = $this->gerarTimeline($entrega['id']);
        $entrega['previsao'] = $this->calcularPrevisao($entrega);
        
        return $this->json($response, [
            'success' => true,
            'data' => $entrega
        ]);
    }
    
    // ================================================================
    // CHECK-IN
    // ================================================================
    public function checkin(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $input = json_decode($request->getBody()->getContents(), true) ?? [];
        $user = $request->getAttribute('user');
        $usuarioId = $user['idusuario'] ?? 0;
        
        $entrega = $this->getEntrega($id);
        if (!$entrega) {
            return $this->json($response, ['success' => false, 'error' => 'Entrega não encontrada'], 404);
        }
        if (!$this->motoristaPodeOperarEntrega($request, $entrega, $desktop)) {
            return $this->json($response, ['success' => false, 'error' => 'Motorista não autorizado para esta entrega'], 403);
        }
        
        if ($entrega['status'] === 'entregue') {
            return $this->json($response, ['success' => false, 'error' => 'Esta entrega já foi concluída'], 400);
        }
        
        $desktop = (bool)($input['desktop'] ?? false);
        $lat = (float)($input['latitude'] ?? 0);
        $lng = (float)($input['longitude'] ?? 0);
        
        if ($lat == 0 || $lng == 0) {
            if (!empty($entrega['latitude']) && !empty($entrega['longitude'])) {
                $lat = (float)$entrega['latitude'];
                $lng = (float)$entrega['longitude'];
            } else {
                define('DISTRIBUIDORA_LAT', -28.979438954992666);
                define('DISTRIBUIDORA_LNG', -49.53561648427039);
                $lat = DISTRIBUIDORA_LAT;
                $lng = DISTRIBUIDORA_LNG;
            }
        }
        
        // Validação de distância apenas se não for desktop
        if (!$desktop && !empty($entrega['latitude']) && !empty($entrega['longitude'])) {
            $distancia = $this->calcularDistancia(
                $lat, $lng,
                (float)$entrega['latitude'],
                (float)$entrega['longitude']
            );
            $distanciaMinima = $this->getConfig('distancia_minima_checkin_metros', 100);
            
            if ($distancia > $distanciaMinima) {
                return $this->json($response, [
                    'success' => false,
                    'error' => "Você está a {$distancia}m do local de entrega. Distância máxima permitida: {$distanciaMinima}m.",
                    'distancia' => round($distancia, 0)
                ], 400);
            }
        }
        
        // Foto (upload)
        $fotoUrl = null;
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $fotoUrl = $this->uploadFoto($_FILES['foto'], 'checkin_' . $id);
        }
        
        // Registrar check-in
        $stmt = $this->pdo->prepare("
            INSERT INTO frota_checkin 
            (entrega_id, motorista_id, tipo, latitude, longitude, foto_url, data_hora)
            VALUES (
                :entrega_id,
                (SELECT motorista_id FROM frota_embarque WHERE id = (SELECT embarque_id FROM frota_entrega WHERE id = :entrega_id2)),
                'checkin',
                :lat,
                :lng,
                :foto,
                NOW()
                )
            ");
        $stmt->execute([
            'entrega_id' => $id,
            'entrega_id2' => $id,
            'lat' => $lat,
            'lng' => $lng,
            'foto' => $fotoUrl
        ]);
        
        // Atualizar entrega
        $stmt = $this->pdo->prepare("
            UPDATE frota_entrega 
                SET status = 'em_entrega',
                horario_checkin = NOW(),
                lat_checkin = :lat,
                lng_checkin = :lng,
                foto_checkin_url = :foto,
                updated_at = NOW()
                WHERE id = :id
                ");
        $stmt->execute([
            'id' => $id,
            'lat' => $lat,
            'lng' => $lng,
            'foto' => $fotoUrl
        ]);
        
        // 🔥 LOG: usando registrarLogEntrega com usuarioId
        $this->registrarLogEntrega($id, 'checkin', "Check-in registrado para entrega #{$id}" . ($desktop ? ' (desktop)' : ''), $usuarioId);
        
        return $this->json($response, [
            'success' => true,
            'message' => 'Check-in registrado com sucesso!',
            'data' => [
                'entrega_id' => $id,
                'status' => 'em_entrega',
                'horario_checkin' => date('Y-m-d H:i:s')
            ]
        ]);
    }
    
  /**
 * POST /v1/frota/entregas/{id}/checkout
 * Finalizar uma entrega com checkout
 */
public function checkout(Request $request, Response $response, array $args): Response
{
    $id = (int)$args['id'];
    $input = json_decode($request->getBody()->getContents(), true) ?? [];
    $user = $request->getAttribute('user');
    $usuarioId = $user['idusuario'] ?? 0;

    $desktop = (bool)($input['desktop'] ?? false);
    $lat = (float)($input['latitude'] ?? 0);
    $lng = (float)($input['longitude'] ?? 0);
    $nomeRecebedor = trim($input['nome_recebedor'] ?? '');
    $fotoRomaneioBase64 = $input['foto_romaneio'] ?? null;
    $checklist = $input['checklist'] ?? [];
    $temFaltante = (bool)($input['tem_faltante'] ?? false);
    $temDevolucao = (bool)($input['tem_devolucao'] ?? false);

    $entrega = $this->getEntrega($id);
    if (!$entrega) {
        return $this->json($response, ['success' => false, 'error' => 'Entrega não encontrada'], 404);
    }
    if (!$this->motoristaPodeOperarEntrega($request, $entrega, $desktop)) {
        return $this->json($response, ['success' => false, 'error' => 'Motorista não autorizado para esta entrega'], 403);
    }

    if ($entrega['status'] === 'entregue') {
        return $this->json($response, ['success' => false, 'error' => 'Entrega já foi concluída'], 400);
    }

    // Validações prévias
    if (empty($fotoRomaneioBase64)) {
        return $this->json($response, ['success' => false, 'error' => 'Foto do romaneio assinado é obrigatória'], 400);
    }
    if (empty($nomeRecebedor)) {
        return $this->json($response, ['success' => false, 'error' => 'Nome do recebedor é obrigatório'], 400);
    }
    if (!$desktop && !empty($checklist)) {
        foreach ($checklist as $item) {
            if (empty($item['foto_item'])) {
                return $this->json($response, [
                    'success' => false,
                    'error' => 'Foto do item "' . ($item['referencia'] ?? $item['item_id']) . '" é obrigatória.'
                ], 400);
            }
        }
    }

    try {
        $this->pdo->beginTransaction();

        // Coordenadas
        if (!$desktop) {
            if ($lat == 0 || $lng == 0) {
                if (!empty($entrega['latitude']) && !empty($entrega['longitude'])) {
                    $lat = (float)$entrega['latitude'];
                    $lng = (float)$entrega['longitude'];
                } else {
                    define('DISTRIBUIDORA_LAT', -28.979438954992666);
                    define('DISTRIBUIDORA_LNG', -49.53561648427039);
                    $lat = DISTRIBUIDORA_LAT;
                    $lng = DISTRIBUIDORA_LNG;
                }
            }
            if (!empty($entrega['cliente_id'])) {
                $stmt = $this->pdo->prepare("
                    UPDATE frota_cliente 
                    SET latitude = :lat, 
                        longitude = :lng,
                        coordenada_confiavel = true,
                        data_atualizacao_coordenada = NOW(),
                        origem_coordenada = 'checkout',
                        updated_at = NOW()
                    WHERE id = :id
                ");
                $stmt->execute([
                    'id' => $entrega['cliente_id'], 
                    'lat' => $lat, 
                    'lng' => $lng
                ]);
            }
        } else {
            if (!empty($entrega['latitude']) && !empty($entrega['longitude'])) {
                $lat = (float)$entrega['latitude'];
                $lng = (float)$entrega['longitude'];
            } else {
                define('DISTRIBUIDORA_LAT', -28.979438954992666);
                define('DISTRIBUIDORA_LNG', -49.53561648427039);
                $lat = DISTRIBUIDORA_LAT;
                $lng = DISTRIBUIDORA_LNG;
            }
        }

        // Salvar foto do romaneio
        $fotoRomaneioUrl = $this->salvarFotoBase64($fotoRomaneioBase64, 'romaneio_' . $id);

        // ATUALIZAR ENTREGA
        $statusEntrega = ($temFaltante || $temDevolucao) ? 'entregue_com_problema' : 'entregue';
        $stmt = $this->pdo->prepare("
            UPDATE frota_entrega 
            SET latitude = :lat, 
                longitude = :lng,
                status_geolocalizacao = 'confirmado',
                data_geolocalizacao = NOW(),
                status = :status,
                horario_entrega = NOW(),
                nome_recebedor = :nome_recebedor,
                foto_romaneio_url = :foto_romaneio,
                data_checkout = NOW(),
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            'id' => $id,
            'lat' => $lat,
            'lng' => $lng,
            'status' => $statusEntrega,
            'nome_recebedor' => $nomeRecebedor,
            'foto_romaneio' => $fotoRomaneioUrl
        ]);

        // ================================================================
        // SALVAR CHECKLIST - CORRIGIDO (coluna: foto_url)
        // ================================================================
        if (!empty($checklist)) {
            $stmt = $this->pdo->prepare("
                INSERT INTO frota_checklist_entrega (
                    entrega_id, 
                    item_id, 
                    referencia, 
                    descricao, 
                    quantidade_prevista, 
                    quantidade_entregue, 
                    status, 
                    motivo, 
                    foto_url
                ) VALUES (
                    :entrega_id, 
                    :item_id, 
                    :referencia, 
                    :descricao,
                    :quantidade_prevista, 
                    :quantidade_entregue,
                    :status, 
                    :motivo, 
                    :foto_url
                )
            ");

            foreach ($checklist as $item) {
                $fotoItemUrl = null;
                if (!empty($item['foto_item'])) {
                    $fotoItemUrl = $this->salvarFotoBase64($item['foto_item'], 'item_' . $id . '_' . $item['item_id']);
                }

                $stmt->execute([
                    'entrega_id' => $id,
                    'item_id' => $item['item_id'],
                    'referencia' => $item['referencia'] ?? null,
                    'descricao' => $item['descricao'] ?? null,
                    'quantidade_prevista' => $item['quantidade_prevista'] ?? 0,
                    'quantidade_entregue' => $item['quantidade_entregue'] ?? 0,
                    'status' => $item['status'],
                    'motivo' => $item['motivo'] ?? null,
                    'foto_url' => $fotoItemUrl
                ]);
            }
        }

        // Atualizar status do embarque se houver problema
        if ($temFaltante || $temDevolucao) {
            $stmt = $this->pdo->prepare("
                UPDATE frota_embarque 
                SET status = 'problema',
                    updated_at = NOW()
                WHERE id = (SELECT embarque_id FROM frota_entrega WHERE id = :id)
            ");
            $stmt->execute(['id' => $id]);
        }

        // Registrar check-out no histórico
        $stmt = $this->pdo->prepare("
            INSERT INTO frota_checkin 
            (entrega_id, motorista_id, tipo, latitude, longitude, assinatura_url, data_hora)
            VALUES (
                :entrega_id,
                (SELECT motorista_id FROM frota_embarque WHERE id = (SELECT embarque_id FROM frota_entrega WHERE id = :entrega_id2)),
                'checkout',
                :lat,
                :lng,
                :foto_romaneio,
                NOW()
            )
        ");
        $stmt->execute([
            'entrega_id' => $id,
            'entrega_id2' => $id,
            'lat' => $lat,
            'lng' => $lng,
            'foto_romaneio' => $fotoRomaneioUrl
        ]);

        // LOG
        $this->registrarLogEntrega($id, 'checkout', 
            "Entrega concluída. Desktop: " . ($desktop ? 'Sim' : 'Não') . 
            ($temFaltante ? ' - Itens faltantes' : '') .
            ($temDevolucao ? ' - Devoluções' : ''),
            $usuarioId
        );

        $this->pdo->commit();

        return $this->json($response, [
            'success' => true,
            'message' => ($temFaltante || $temDevolucao) 
                ? 'Entrega concluída com pendências (faltantes/devoluções). Embarque marcado como problema.' 
                : 'Entrega concluída com sucesso!',
            'data' => [
                'entrega_id' => $id,
                'status' => $statusEntrega,
                'embarque_status' => ($temFaltante || $temDevolucao) ? 'problema' : null
            ]
        ]);

    } catch (\Exception $e) {
        $this->pdo->rollBack();
        error_log('[Checkout] Erro: ' . $e->getMessage());
        error_log('[Checkout] Stack trace: ' . $e->getTraceAsString());
        return $this->json($response, [
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
}
    // ================================================================
    // FALHA
    // ================================================================
    public function falha(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $input = json_decode($request->getBody()->getContents(), true) ?? [];
        $user = $request->getAttribute('user');
        $usuarioId = $user['idusuario'] ?? 0;
        
        $motivo = trim($input['motivo'] ?? '');
        if (empty($motivo)) {
            return $this->json($response, ['success' => false, 'error' => 'Motivo da falha é obrigatório'], 400);
        }
        
        $motivosValidos = ['cliente_ausente', 'endereco_incorreto', 'recusado', 'nao_localizado', 'outro'];
        if (!in_array($motivo, $motivosValidos)) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Motivo inválido. Opções: ' . implode(', ', $motivosValidos)
            ], 400);
        }
        
        $entrega = $this->getEntrega($id);
        if (!$entrega) {
            return $this->json($response, ['success' => false, 'error' => 'Entrega não encontrada'], 404);
        }
        if (!$this->motoristaPodeOperarEntrega($request, $entrega, (bool)($input['desktop'] ?? false))) {
            return $this->json($response, ['success' => false, 'error' => 'Motorista não autorizado para esta entrega'], 403);
        }
        
        $lat = (float)($input['latitude'] ?? 0);
        $lng = (float)($input['longitude'] ?? 0);
        if ($lat == 0 || $lng == 0) {
            if (!empty($entrega['latitude']) && !empty($entrega['longitude'])) {
                $lat = (float)$entrega['latitude'];
                $lng = (float)$entrega['longitude'];
            }
        }
        
        // Registrar ocorrência
        $stmt = $this->pdo->prepare("
            INSERT INTO frota_ocorrencia 
            (entrega_id, motorista_id, tipo, descricao, latitude, longitude, status, created_at)
            VALUES (
                :entrega_id,
                (SELECT motorista_id FROM frota_embarque WHERE id = (SELECT embarque_id FROM frota_entrega WHERE id = :entrega_id2)),
                'endereco_incorreto',
                :descricao,
                :lat,
                :lng,
                'aberta',
                NOW()
                )
            ");
        $stmt->execute([
            'entrega_id' => $id,
            'entrega_id2' => $id,
            'descricao' => $input['observacao'] ?? $motivo,
            'lat' => $lat,
            'lng' => $lng
        ]);
        
        $tentativas = (int)$entrega['tentativas'] + 1;
        $maxTentativas = (int)$this->getConfig('limite_tentativas_entrega', 3);
        $novoStatus = $tentativas >= $maxTentativas ? 'cancelada' : 'pendente';
        $proximaTentativa = $tentativas < $maxTentativas ? date('Y-m-d', strtotime('+1 day')) : null;
        
        $stmt = $this->pdo->prepare("
            UPDATE frota_entrega 
                SET status = :status,
                motivo_falha = :motivo,
                observacoes_falha = :obs,
                tentativas = :tentativas,
                data_proxima_tentativa = :proxima,
                updated_at = NOW()
                WHERE id = :id
                ");
        $stmt->execute([
            'id' => $id,
            'status' => $novoStatus,
            'motivo' => $motivo,
            'obs' => $input['observacao'] ?? '',
            'tentativas' => $tentativas,
            'proxima' => $proximaTentativa
        ]);
        
        // 🔥 LOG: usando registrarLog (para embarque) com usuarioId
        $this->registrarLog($entrega['embarque_id'] ?? 0, 'falha', "Falha na entrega #{$id}: {$motivo}", $usuarioId);
        
        return $this->json($response, [
            'success' => true,
            'message' => 'Falha registrada com sucesso',
            'data' => [
                'entrega_id' => $id,
                'status' => $novoStatus,
                'tentativas' => $tentativas,
                'proxima_tentativa' => $proximaTentativa
            ]
        ]);
    }
    
    /**
     * PUT /v1/frota/entregas/{id}/corrigir-endereco
     * Corrigir endereço da entrega
     */
    public function corrigirEndereco(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $input = json_decode($request->getBody()->getContents(), true) ?? [];
        $user = $request->getAttribute('user');
        $usuarioId = $user['idusuario'] ?? 0;
        
        $entrega = $this->getEntrega($id);
        if (!$entrega) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Entrega não encontrada'
            ], 404);
        }
        
        $enderecoCompleto = trim($input['endereco'] ?? '');
        if (empty($enderecoCompleto)) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Endereço é obrigatório'
            ], 400);
        }
        
        $geocode = $this->geocodingService->geocodificar($enderecoCompleto);
        if (!$geocode) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Não foi possível geocodificar o endereço informado'
            ], 400);
        }
        
        $stmt = $this->pdo->prepare("
            UPDATE frota_entrega 
                SET endereco = :endereco,
                numero = :numero,
                complemento = :complemento,
                bairro = :bairro,
                cidade = :cidade,
                uf = :uf,
                cep = :cep,
                latitude = :lat,
                longitude = :lng,
                updated_at = NOW()
                WHERE id = :id
                ");
        $stmt->execute([
            'id' => $id,
            'endereco' => $geocode['logradouro'] ?? $input['endereco'],
            'numero' => $input['numero'] ?? '',
            'complemento' => $input['complemento'] ?? '',
            'bairro' => $geocode['bairro'] ?? $input['bairro'] ?? '',
            'cidade' => $geocode['cidade'] ?? $input['cidade'] ?? '',
            'uf' => $geocode['uf'] ?? $input['uf'] ?? '',
            'cep' => $geocode['cep'] ?? $input['cep'] ?? '',
            'lat' => $geocode['lat'],
            'lng' => $geocode['lng']
        ]);
        
        if ($entrega['cliente_id']) {
            $stmt = $this->pdo->prepare("
                UPDATE frota_cliente 
                    SET endereco = :endereco,
                    numero = :numero,
                    complemento = :complemento,
                    bairro = :bairro,
                    cidade = :cidade,
                    uf = :uf,
                    cep = :cep,
                    latitude = :lat,
                    longitude = :lng,
                    updated_at = NOW()
                    WHERE id = :id
                    ");
            $stmt->execute([
                'id' => $entrega['cliente_id'],
                'endereco' => $geocode['logradouro'] ?? $input['endereco'],
                'numero' => $input['numero'] ?? '',
                'complemento' => $input['complemento'] ?? '',
                'bairro' => $geocode['bairro'] ?? $input['bairro'] ?? '',
                'cidade' => $geocode['cidade'] ?? $input['cidade'] ?? '',
                'uf' => $geocode['uf'] ?? $input['uf'] ?? '',
                'cep' => $geocode['cep'] ?? $input['cep'] ?? '',
                'lat' => $geocode['lat'],
                'lng' => $geocode['lng']
            ]);
        }
        
        $stmt = $this->pdo->prepare("
            INSERT INTO frota_ocorrencia 
            (entrega_id, tipo, descricao, status, created_at)
            VALUES (:entrega_id, 'endereco_incorreto', :descricao, 'resolvida', NOW())
            ");
        $stmt->execute([
            'entrega_id' => $id,
            'descricao' => "Endereço corrigido de '{$entrega['endereco']}' para '{$enderecoCompleto}'"
        ]);
        
        $this->registrarLog($id, 'corrigir_endereco', "Endereço corrigido para: {$enderecoCompleto}", $usuarioId);
        
        return $this->json($response, [
            'success' => true,
            'message' => 'Endereço corrigido com sucesso',
            'data' => [
                'entrega_id' => $id,
                'endereco' => $enderecoCompleto,
                'latitude' => $geocode['lat'],
                'longitude' => $geocode['lng']
            ]
        ]);
    }
    
    /**
     * POST /v1/frota/entregas/{id}/reagendar
     * Reagendar entrega
     */
    public function reagendar(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $input = json_decode($request->getBody()->getContents(), true) ?? [];
        $user = $request->getAttribute('user');
        $usuarioId = $user['idusuario'] ?? 0;
        
        $novaData = trim($input['data'] ?? '');
        if (empty($novaData)) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Nova data é obrigatória'
            ], 400);
        }
        
        $entrega = $this->getEntrega($id);
        if (!$entrega) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Entrega não encontrada'
            ], 404);
        }
        
        $stmt = $this->pdo->prepare("
            UPDATE frota_entrega 
                SET data_proxima_tentativa = :data,
                status = 'pendente',
                tentativas = 0,
                updated_at = NOW()
                WHERE id = :id
                ");
        $stmt->execute([
            'id' => $id,
            'data' => $novaData
        ]);
        
        $this->registrarLog($id, 'reagendar', "Entrega reagendada para: {$novaData}", $usuarioId);
        
        return $this->json($response, [
            'success' => true,
            'message' => 'Entrega reagendada com sucesso',
            'data' => [
                'entrega_id' => $id,
                'nova_data' => $novaData,
                'status' => 'pendente'
            ]
        ]);
    }
    
    // ================================================================
    // MÉTODOS AUXILIARES
    // ================================================================
    
    private function getEntrega($id)
    {
        $stmt = $this->pdo->prepare("
            SELECT 
            e.*,
            eb.motorista_id,
            eb.veiculo_id,
            eb.numero_embarque,
            eb.id as embarque_id
            FROM frota_entrega e
            LEFT JOIN frota_embarque eb ON eb.id = e.embarque_id
            WHERE e.id = :id
            ");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    private function motoristaPodeOperarEntrega(Request $request, array $entrega, bool $desktop): bool
    {
        if ($desktop) return true;
        $user = $request->getAttribute('user') ?? [];
        if (in_array('admin', $user['permissoes'] ?? [], true)) return true;
        $motoristaId = (int)($user['motorista_id'] ?? 0);
        return $motoristaId > 0 && $motoristaId === (int)($entrega['motorista_id'] ?? 0);
    }
    
    private function getConfig($chave, $padrao = null)
    {
        $stmt = $this->pdo->prepare("SELECT valor FROM frota_configuracao WHERE chave = :chave");
        $stmt->execute(['chave' => $chave]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ? $result['valor'] : $padrao;
    }
    
    private function calcularDistancia($lat1, $lng1, $lat2, $lng2): float
    {
        if (!$lat1 || !$lng1 || !$lat2 || !$lng2) return 0;
        $R = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat/2) * sin($dLat/2) +
        cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
        sin($dLng/2) * sin($dLng/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        return $R * $c;
    }

    
    private function gerarTimeline($entregaId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 'checkin' as evento, data_hora, 'Check-in realizado' as descricao, foto_url
            FROM frota_checkin
            WHERE entrega_id = :entrega_id AND tipo = 'checkin'
            UNION ALL
            SELECT 'checkout' as evento, data_hora, 'Entrega concluída' as descricao, foto_url
            FROM frota_checkin
            WHERE entrega_id = :entrega_id AND tipo = 'checkout'
            ORDER BY data_hora ASC
            ");
        $stmt->execute(['entrega_id' => $entregaId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    private function calcularPrevisao($entrega): ?array
    {
        if ($entrega['status'] === 'entregue') {
            return ['status' => 'entregue', 'data' => $entrega['horario_entrega']];
        }
        if ($entrega['veiculo_lat'] && $entrega['veiculo_lng']) {
            $distancia = $this->calcularDistancia(
                (float)$entrega['veiculo_lat'],
                (float)$entrega['veiculo_lng'],
                (float)$entrega['latitude'],
                (float)$entrega['longitude']
            );
            $tempoMin = round(($distancia / 1000) / 40 * 60);
            $previsao = date('Y-m-d H:i:s', strtotime("+{$tempoMin} minutes"));
            return [
                'status' => 'estimado',
                'distancia_km' => round($distancia / 1000, 1),
                'tempo_min' => $tempoMin,
                'previsao' => $previsao
            ];
        }
        return ['status' => 'indisponivel'];
    }
    
    private function atualizarPedidoERP($entregaId, $status)
    {
        error_log("Atualizando pedido ERP: Entrega {$entregaId} -> Status {$status}");
    }
    
    private function notificarCliente($entrega)
    {
        error_log("Notificando cliente: {$entrega['cliente_nome']} - Entrega {$entrega['id']}");
    }
    
    /**
     * LOG para frota_log_embarque (ações que afetam o embarque)
     */
    private function registrarLog($embarqueId, $acao, $descricao, $usuarioId = 0)
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO frota_log_embarque (embarque_id, acao, descricao, usuario_id, data_hora)
                VALUES (:embarque_id, :acao, :descricao, :usuario_id, NOW())
                ");
            $stmt->execute([
                'embarque_id' => $embarqueId,
                'acao' => $acao,
                'descricao' => $descricao,
                'usuario_id' => $usuarioId
            ]);
        } catch (\Exception $e) {
            error_log('Erro ao registrar log (embarque): ' . $e->getMessage());
        }
    }
    
    /**
     * LOG para frota_log_entrega (ações específicas da entrega)
     */
    private function registrarLogEntrega($entregaId, $acao, $descricao, $usuarioId = 0)
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO frota_log_entrega (entrega_id, acao, descricao, usuario_id, data_hora)
                VALUES (:entrega_id, :acao, :descricao, :usuario_id, NOW())
                ");
            $stmt->execute([
                'entrega_id' => $entregaId,
                'acao' => $acao,
                'descricao' => $descricao,
                'usuario_id' => $usuarioId
            ]);
        } catch (\Exception $e) {
            error_log('Erro ao registrar log (entrega): ' . $e->getMessage());
        }
    }


    private function uploadFoto($file, $prefix): ?string
    {
        $extensao = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $nome = $prefix . '_' . date('Ymd_His') . '.' . $extensao;

        $basePath = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
        $caminho = $basePath . '/portal/uploads/frota/entregas/';
        if (!is_dir($caminho)) mkdir($caminho, 0755, true);

        $destino = $caminho . $nome;
        if (move_uploaded_file($file['tmp_name'], $destino)) {
            return '/portal/uploads/frota/entregas/' . $nome;
        }
        return null;
    }
    
    private function salvarAssinatura($base64, $entregaId): ?string
    {
        $dados = explode(',', $base64);
        $imagem = base64_decode($dados[1] ?? '');
        if (!$imagem) return null;

        $nome = 'assinatura_' . $entregaId . '_' . date('Ymd_His') . '.png';

        $basePath = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
        $caminho = $basePath . '/portal/uploads/frota/assinaturas/';
        if (!is_dir($caminho)) mkdir($caminho, 0755, true);

        $destino = $caminho . $nome;
        if (file_put_contents($destino, $imagem)) {
            return '/portal/uploads/frota/assinaturas/' . $nome;
        }
        return null;
    }
    
    private function salvarFotoBase64($base64, $prefix): ?string
    {
        $dados = explode(',', $base64);
        if (count($dados) < 2) return null;
        $imagem = base64_decode($dados[1]);
        if (!$imagem) return null;

        $extensao = 'png';
        $nome = $prefix . '_' . date('Ymd_His') . '.' . $extensao;

    // Usando DOCUMENT_ROOT para caminho absoluto
        $basePath = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
        $caminho = $basePath . '/portal/uploads/frota/entregas/';

        if (!is_dir($caminho)) mkdir($caminho, 0755, true);

        $destino = $caminho . $nome;
        if (file_put_contents($destino, $imagem)) {
            return '/portal/uploads/frota/entregas/' . $nome;
        }
        return null;
    }
    
    private function enviarNotificacaoWS($dados)
    {
        error_log('WebSocket: ' . json_encode($dados));
    }
    
    private function json($response, $data, $status = 200): Response
    {
        $payload = json_encode($data, JSON_UNESCAPED_UNICODE);
        $response->getBody()->write($payload);
        return $response->withStatus($status)
        ->withHeader('Content-Type', 'application/json; charset=utf-8')
        ->withHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
    }
}