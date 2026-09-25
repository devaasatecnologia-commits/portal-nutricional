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
     * Detecta se a requisição está em modo treinamento.
     *
     * Ativado via header `X-Training-Mode: 1` (enviado pelo app do motorista
     * quando a URL tem `?treino=1`).
     *
     * Em modo treinamento:
     *   - checkin/checkout NÃO são bloqueados por distância
     *   - lat/lng NÃO são gravados quando o motorista está fora do raio
     */
    private function isTrainingMode(Request $request): bool
    {
        return $request->getHeaderLine('X-Training-Mode') === '1';
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
            $filtros[] = "e.created_at >= :data_inicio AND e.created_at < (:data_fim::date + INTERVAL '1 day')";
            $bindParams['data_inicio'] = $params['data_inicio'] . ' 00:00:00';
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

    $sqlCount = "
        SELECT COUNT(*)
        FROM frota_entrega e
        LEFT JOIN frota_embarque eb ON eb.id = e.embarque_id
        {$where}
    ";
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
    //
    // 🔥 MUDANÇA 2026-09-24 (MODO TREINAMENTO):
    //   - Em modo treino, NÃO bloqueia por distância
    //   - Não grava lat/lng quando fora do raio
    // ================================================================
    public function checkin(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $input = json_decode($request->getBody()->getContents(), true) ?? [];
        $operationId = $this->getOfflineOperationId($request, $input);
        $previousOperation = $this->getOfflineOperation($operationId);
        if ($previousOperation) {
            return $this->json($response, $previousOperation['response'], $previousOperation['status_code']);
        }
        $user = $request->getAttribute('user');
        $usuarioId = $user['idusuario'] ?? 0;
        $desktop = (bool)($input['desktop'] ?? false);
        $isTraining = $this->isTrainingMode($request);

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

        // ============================================================
        // VALIDAÇÃO DE DISTÂNCIA
        // ------------------------------------------------------------
        // Produção:    bloqueia com 400 se > distanciaMaxima
        // Treinamento: NÃO bloqueia e marca $gravarCoordenadas = false
        // ============================================================
        $gravarCoordenadas = true;

        if (!$desktop && !empty($entrega['latitude']) && !empty($entrega['longitude'])) {
            $distancia = $this->calcularDistancia(
                $lat, $lng,
                (float)$entrega['latitude'],
                (float)$entrega['longitude']
            );
            $distanciaMaxima = max(1000.0, (float)$this->getConfig('distancia_minima_checkin_metros', 1000));

            if ($distancia > $distanciaMaxima) {

                if ($isTraining) {
                    // 🎓 MODO TREINO: permite, mas NÃO grava lat/lng
                    $gravarCoordenadas = false;
                    error_log('[Checkin-TREINO] Fora do raio: ' . round($distancia) . 'm (entrega #' . $id . ')');
                } else {
                    // 🚫 PRODUÇÃO: bloqueia
                    $distanciaFormatada = $distancia >= 1000
                        ? number_format($distancia / 1000, 2, ',', '.') . ' km'
                        : number_format($distancia, 0, ',', '.') . ' m';
                    $limiteFormatado = $distanciaMaxima >= 1000
                        ? number_format($distanciaMaxima / 1000, 2, ',', '.') . ' km'
                        : number_format($distanciaMaxima, 0, ',', '.') . ' m';
                    return $this->json($response, [
                        'success' => false,
                        'error' => "Você está a {$distanciaFormatada} do local de entrega. Distância máxima permitida: {$limiteFormatado}.",
                        'distancia' => round($distancia, 0)
                    ], 400);
                }
            }
        }

        // Foto (upload)
        $fotoUrl = null;
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $fotoUrl = $this->uploadFoto($_FILES['foto'], 'checkin_' . $id);
        }

        // Coordenadas efetivas para gravar
        $latGravar = $gravarCoordenadas ? $lat : null;
        $lngGravar = $gravarCoordenadas ? $lng : null;

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
            'lat' => $latGravar,
            'lng' => $lngGravar,
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
            'lat' => $latGravar,
            'lng' => $lngGravar,
            'foto' => $fotoUrl
        ]);

        // LOG
        $logDescricao = "Check-in registrado para entrega #{$id}"
            . ($desktop ? ' (desktop)' : '')
            . ($isTraining ? ' [MODO TREINAMENTO]' : '')
            . (!$gravarCoordenadas ? ' [sem GPS - fora do raio]' : '');
        $this->registrarLogEntrega($id, 'checkin', $logDescricao, $usuarioId);

        $payload = [
            'success' => true,
            'message' => 'Check-in registrado com sucesso!',
            'data' => [
                'entrega_id' => $id,
                'status' => 'em_entrega',
                'horario_checkin' => date('Y-m-d H:i:s'),
                'gps_registrado' => $gravarCoordenadas,
                'modo_treinamento' => $isTraining
            ]
        ];
        $this->saveOfflineOperation($operationId, $id, 'checkin', $payload, 200);
        return $this->json($response, $payload);
    }
       /**
     * POST /v1/frota/entregas/{id}/checkout
     * Finaliza entrega com foto, assinatura e checklist.
     *
     * 🔥 MUDANÇA 2026-09-25 (ENTREGAS PARCIAIS / LEVAS):
     *   - Aceita `checklist[].levas[]` no payload. Cada leva é uma "descida"
     *     com quantidade + foto + timestamp.
     *   - Se o item veio com `status === 'aberto'` (motorista não conseguiu
     *     fechar no dia), a entrega continua aberta — NÃO grava checklist.
     *   - Grava cada leva em `frota_checklist_entrega_leva` vinculada ao
     *     checklist_id recém-criado.
     *   - Retorna `total_levas` no payload para debug.
     */
    public function checkout(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $input = json_decode($request->getBody()->getContents(), true) ?? [];
        $operationId = $this->getOfflineOperationId($request, $input);
        $previousOperation = $this->getOfflineOperation($operationId);
        if ($previousOperation) {
            return $this->json($response, $previousOperation['response'], $previousOperation['status_code']);
        }
        $user = $request->getAttribute('user');
        $usuarioId = $user['idusuario'] ?? 0;
        $isTraining = $this->isTrainingMode($request);

        $desktop = (bool)($input['desktop'] ?? false);
        $lat = (float)($input['latitude'] ?? 0);
        $lng = (float)($input['longitude'] ?? 0);
        $nomeRecebedor = trim($input['nome_recebedor'] ?? '');
        $fotoRomaneioBase64 = $input['foto_romaneio'] ?? null;
        $checklist = $input['checklist'] ?? [];
                // 🔥 NOVO 2026-09-25 (ENTREGAS PARCIAIS): observação automática
        //   Usado quando o rascunho virou faltante automático (checkout não
        //   finalizado em outro dia). Vai para o log e para a coluna
        //   `observacoes` da entrega, permitindo auditar depois.
        $observacaoAutomatica = trim((string)($input['observacao_automatica'] ?? ''));

        // Derivar temFaltante / temDevolucao / temAberto do checklist (fonte da verdade)
        $temFaltante = false;
        $temDevolucao = false;
        $temAberto    = false;
        $totalLevas   = 0;
        foreach ($checklist as $itemCheck) {
            $statusItem = $itemCheck['status'] ?? '';
            if ($statusItem === 'faltante')  $temFaltante = true;
            if ($statusItem === 'devolvido') $temDevolucao = true;
            if ($statusItem === 'aberto')    $temAberto    = true;
            if (!empty($itemCheck['levas']) && is_array($itemCheck['levas'])) {
                $totalLevas += count($itemCheck['levas']);
            }
        }

        $temFaltanteFront  = (bool)($input['tem_faltante'] ?? false);
        $temDevolucaoFront = (bool)($input['tem_devolucao'] ?? false);
        $temAbertoFront    = (bool)($input['tem_aberto'] ?? false);

        if ($temFaltanteFront !== $temFaltante
            || $temDevolucaoFront !== $temDevolucao
            || $temAbertoFront !== $temAberto) {
            error_log('[Checkout] Divergência front/backend: '
                . 'front faltante=' . var_export($temFaltanteFront, true) . ', backend faltante=' . var_export($temFaltante, true)
                . ' | front devolucao=' . var_export($temDevolucaoFront, true) . ', backend devolucao=' . var_export($temDevolucao, true)
                . ' | front aberto=' . var_export($temAbertoFront, true) . ', backend aberto=' . var_export($temAberto, true)
                . ' | entrega_id=' . $id);
        }

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
        if (!$desktop && ($lat == 0 || $lng == 0)) {
            return $this->json($response, ['success' => false, 'error' => 'GPS do dispositivo é obrigatório para finalizar a entrega'], 400);
        }

        // ============================================================
        // VALIDAÇÃO DE DISTÂNCIA (SOFT-FAIL EM MODO TREINAMENTO)
        // ============================================================
        $gravarCoordenadasCheckout = true;

        if (!$desktop && !empty($entrega['latitude']) && !empty($entrega['longitude'])) {
            $distanciaCheckout = $this->calcularDistancia($lat, $lng, (float)$entrega['latitude'], (float)$entrega['longitude']);
            $distanciaMaximaCheckout = max(1000.0, (float)$this->getConfig('distancia_maxima_checkout_metros', 1000));

            if ($distanciaCheckout > $distanciaMaximaCheckout) {
                if ($isTraining) {
                    $gravarCoordenadasCheckout = false;
                    error_log('[Checkout-TREINO] Fora do raio: ' . round($distanciaCheckout) . 'm (entrega #' . $id . ')');
                } else {
                    $distanciaFormatada = $distanciaCheckout >= 1000
                        ? number_format($distanciaCheckout / 1000, 2, ',', '.') . ' km'
                        : number_format($distanciaCheckout, 0, ',', '.') . ' m';
                    return $this->json($response, ['success' => false, 'error' => "Você está a {$distanciaFormatada} do cliente. Aproxime-se para finalizar a entrega."], 400);
                }
            }
        }

        // Foto obrigatória por item (a menos que desktop)
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

        // ============================================================
        // 🔥 NOVO (ENTREGAS PARCIAIS): validação das levas
        //   - Cada leva precisa ter quantidade > 0 e foto
        //   - A soma das levas do item deve bater com quantidade_entregue
        // ============================================================
        foreach ($checklist as $idx => $itemCheck) {
            $levas = $itemCheck['levas'] ?? [];
            if (empty($levas) || !is_array($levas)) {
                continue; // sem levas = entrega de uma vez só (fluxo antigo)
            }

            $somaLevas = 0;
            foreach ($levas as $levaIdx => $leva) {
                $qtdLeva = (float)($leva['quantidade'] ?? 0);
                if ($qtdLeva <= 0) {
                    return $this->json($response, [
                        'success' => false,
                        'error' => "Leva #{$levaIdx} do item '{$itemCheck['referencia']}' tem quantidade inválida."
                    ], 400);
                }
                if (empty($leva['foto_item'])) {
                    return $this->json($response, [
                        'success' => false,
                        'error' => "Foto obrigatória na leva #{$levaIdx} do item '{$itemCheck['referencia']}'."
                    ], 400);
                }
                $somaLevas += $qtdLeva;
            }

            $qtdEntregueItem = (float)($itemCheck['quantidade_entregue'] ?? 0);
            if (abs($somaLevas - $qtdEntregueItem) > 0.01) {
                return $this->json($response, [
                    'success' => false,
                    'error' => "Item '{$itemCheck['referencia']}': soma das levas ({$somaLevas}) não bate com quantidade entregue ({$qtdEntregueItem})."
                ], 400);
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
                if ($gravarCoordenadasCheckout && !empty($entrega['cliente_id'])) {
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

            $latGravar = $gravarCoordenadasCheckout ? $lat : null;
            $lngGravar = $gravarCoordenadasCheckout ? $lng : null;

            // Status da entrega
            $statusEntrega = ($temFaltante || $temDevolucao || $temAberto)
                ? 'entregue_com_problema'
                : 'entregue';

                 // 🔥 NOVO: se veio observacao_automatica, prefixa no campo
            // `observacoes` da entrega (sem perder o que já existe).
            $observacoesFinais = null;
            if ($observacaoAutomatica !== '') {
                $stmtObs = $this->pdo->prepare("
                    SELECT observacoes FROM frota_entrega WHERE id = :id
                ");
                $stmtObs->execute(['id' => $id]);
                $obsAtual = (string)($stmtObs->fetchColumn() ?: '');
                $separador = $obsAtual !== '' ? "\n---\n" : '';
                $observacoesFinais = $separador
                    . '[' . date('d/m/Y H:i') . '] '
                    . $observacaoAutomatica;
                // Se já havia algo, mantém o existente + adiciona o novo
                if ($obsAtual !== '') {
                    $observacoesFinais = $obsAtual . $separador . '[' . date('d/m/Y H:i') . '] ' . $observacaoAutomatica;
                }
            }

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
                    observacoes = CASE
                        WHEN :tem_obs = 1 THEN :obs
                        ELSE observacoes
                    END,
                    updated_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([
                'id' => $id,
                'lat' => $latGravar,
                'lng' => $lngGravar,
                'status' => $statusEntrega,
                'nome_recebedor' => $nomeRecebedor,
                'foto_romaneio' => $fotoRomaneioUrl,
                'tem_obs' => $observacoesFinais !== null ? 1 : 0,
                'obs' => $observacoesFinais
            ]);
  

            // ============================================================
            // SALVAR CHECKLIST + LEVAS
            // ============================================================
            $totalLevasInseridas = 0;

            if (!empty($checklist)) {
                $stmtChecklist = $this->pdo->prepare("
                    INSERT INTO frota_checklist_entrega (
                        entrega_id, item_id, referencia, descricao,
                        quantidade_prevista, quantidade_entregue, status, motivo, foto_url
                    ) VALUES (
                        :entrega_id, :item_id, :referencia, :descricao,
                        :quantidade_prevista, :quantidade_entregue, :status, :motivo, :foto_url
                    ) RETURNING id
                ");

                $stmtLeva = $this->pdo->prepare("
                    INSERT INTO frota_checklist_entrega_leva (
                        checklist_id, entrega_id, item_id, referencia,
                        quantidade, foto_url, latitude, longitude,
                        registrado_em, registrado_por, observacao, sincronizado
                    ) VALUES (
                        :checklist_id, :entrega_id, :item_id, :referencia,
                        :quantidade, :foto_url, :latitude, :longitude,
                        :registrado_em, :registrado_por, :observacao, TRUE
                    )
                ");

                foreach ($checklist as $item) {
                    // Foto principal do item (a última ou a primeira leva)
                    $fotoItemUrl = null;
                    if (!empty($item['foto_item'])) {
                        $fotoItemUrl = $this->salvarFotoBase64($item['foto_item'], 'item_' . $id . '_' . $item['item_id']);
                    }

                    $stmtChecklist->execute([
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

                    $checklistId = (int)$stmtChecklist->fetchColumn();

                    // 🔥 Gravar cada leva vinculada ao checklist
                    $levas = $item['levas'] ?? [];
                    if (!empty($levas) && is_array($levas)) {
                        foreach ($levas as $leva) {
                            $fotoLevaUrl = null;
                            if (!empty($leva['foto_item'])) {
                                $fotoLevaUrl = $this->salvarFotoBase64(
                                    $leva['foto_item'],
                                    'leva_' . $id . '_' . $item['item_id'] . '_' . time() . '_' . mt_rand(100, 999)
                                );
                            }

                            if (!$fotoLevaUrl) {
                                // Se a foto falhou ao salvar, aborta a transação
                                throw new \Exception(
                                    "Falha ao salvar foto da leva do item {$item['referencia']}"
                                );
                            }

                            $stmtLeva->execute([
                                'checklist_id' => $checklistId,
                                'entrega_id' => $id,
                                'item_id' => $item['item_id'],
                                'referencia' => $item['referencia'] ?? null,
                                'quantidade' => $leva['quantidade'],
                                'foto_url' => $fotoLevaUrl,
                                'latitude' => $leva['latitude'] ?? $latGravar,
                                'longitude' => $leva['longitude'] ?? $lngGravar,
                                'registrado_em' => $leva['registrado_em'] ?? date('Y-m-d H:i:s'),
                                'registrado_por' => $usuarioId,
                                'observacao' => $leva['observacao'] ?? null
                            ]);

                            $totalLevasInseridas++;
                        }
                    }
                }
            }

            // Atualizar status do embarque
            if ($temFaltante || $temDevolucao || $temAberto) {
                $stmt = $this->pdo->prepare("
                    UPDATE frota_embarque
                       SET status = 'problema',
                           updated_at = NOW()
                     WHERE id = (SELECT embarque_id FROM frota_entrega WHERE id = :id)
                ");
                $stmt->execute(['id' => $id]);
            }

            $stmt = $this->pdo->prepare("SELECT embarque_id FROM frota_entrega WHERE id = :id");
            $stmt->execute(['id' => $id]);
            $embarqueIdDaEntrega = (int)$stmt->fetchColumn();

            if ($embarqueIdDaEntrega > 0) {
                $stmt = $this->pdo->prepare("
                    SELECT COUNT(*)
                      FROM frota_entrega
                     WHERE embarque_id = :embarque_id
                       AND status NOT IN ('entregue', 'entregue_com_problema', 'falha', 'cancelada')
                ");
                $stmt->execute(['embarque_id' => $embarqueIdDaEntrega]);
                $entregasAbertas = (int)$stmt->fetchColumn();

                if ($entregasAbertas === 0) {
                    $stmt = $this->pdo->prepare("
                        UPDATE frota_embarque
                           SET status = 'finalizado',
                               data_retorno = CURRENT_DATE,
                               horario_retorno = NOW(),
                               updated_at = NOW()
                         WHERE id = :embarque_id
                           AND status IN ('planejado', 'em_andamento', 'problema')
                    ");
                    $stmt->execute(['embarque_id' => $embarqueIdDaEntrega]);

                    if ($stmt->rowCount() > 0) {
                        error_log('[Checkout] Embarque #' . $embarqueIdDaEntrega . ' auto-finalizado.');
                    }
                }
            }

            // Registrar problema em frota_entrega_problema
            if ($temFaltante || $temDevolucao || $temAberto) {
                if ($temFaltante) {
                    $tipoProblema = 'faltante';
                    $descricaoProblema = 'Itens faltantes registrados no checkout';
                } elseif ($temDevolucao) {
                    $tipoProblema = 'devolucao';
                    $descricaoProblema = 'Itens devolvidos registrados no checkout';
                } else {
                    $tipoProblema = 'aberto';
                    $descricaoProblema = 'Itens em aberto aguardando conferência';
                }

                $stmtProblema = $this->pdo->prepare("
                    INSERT INTO frota_entrega_problema (
                        entrega_id, embarque_id, pedido_id, cliente_id, tipo_problema,
                        descricao_problema, quantidade_afetada, valor_afetado,
                        status_problema, prioridade, created_at, updated_at
                    )
                    SELECT
                        e.id, e.embarque_id, e.pedido_id, e.cliente_id, :tipo,
                        :descricao, :quantidade, :valor, 'pendente', 'alta', NOW(), NOW()
                    FROM frota_entrega e
                    WHERE e.id = :entrega_id
                      AND NOT EXISTS (
                          SELECT 1 FROM frota_entrega_problema ep
                          WHERE ep.entrega_id = e.id AND ep.tipo_problema = :tipo_existente
                            AND ep.status_problema IN ('pendente', 'em_analise')
                      )
                ");
                $stmtProblema->execute([
                    'tipo' => $tipoProblema,
                    'tipo_existente' => $tipoProblema,
                    'descricao' => $descricaoProblema,
                    'quantidade' => array_sum(array_map(
                        static fn($item) => (float)($item['quantidade_prevista'] ?? 0) - (float)($item['quantidade_entregue'] ?? 0),
                        $checklist
                    )),
                    'valor' => (float)($entrega['valor_total'] ?? $entrega['valor'] ?? 0),
                    'entrega_id' => $id
                ]);
            }

            // Histórico de check-out
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
                'lat' => $latGravar,
                'lng' => $lngGravar,
                'foto_romaneio' => $fotoRomaneioUrl
            ]);

            // Log
                 $logDescricao = "Entrega concluída. Desktop: " . ($desktop ? 'Sim' : 'Não')
                . ($temFaltante  ? ' - Itens faltantes' : '')
                . ($temDevolucao ? ' - Devoluções'      : '')
                . ($temAberto    ? ' - Itens em aberto' : '')
                . ($totalLevasInseridas > 0 ? " - {$totalLevasInseridas} leva(s) registrada(s)" : '')
                . ($observacaoAutomatica !== '' ? ' [AUTO-FALTANTE: ' . $observacaoAutomatica . ']' : '')
                . ($isTraining ? ' [MODO TREINAMENTO]' : '')
                . (!$gravarCoordenadasCheckout ? ' [sem GPS - fora do raio]' : '');
            $this->registrarLogEntrega($id, 'checkout', $logDescricao, $usuarioId);

            $this->pdo->commit();

            $stmtStatusFinal = $this->pdo->prepare("SELECT status FROM frota_embarque WHERE id = :id");
            $stmtStatusFinal->execute(['id' => $embarqueIdDaEntrega]);
            $embarqueStatusFinal = $stmtStatusFinal->fetchColumn() ?: null;

            $payload = [
                'success' => true,
                'message' => ($temFaltante || $temDevolucao || $temAberto)
                    ? 'Entrega concluída com pendências (faltantes/devoluções/itens em aberto). Embarque marcado como problema.'
                    : 'Entrega concluída com sucesso!',
                'data' => [
                    'entrega_id' => $id,
                    'status' => $statusEntrega,
                    'embarque_id' => $embarqueIdDaEntrega,
                    'embarque_status' => $embarqueStatusFinal,
                    'embarque_auto_finalizado' => ($embarqueStatusFinal === 'finalizado'),
                    'embarque_status_anterior' => ($temFaltante || $temDevolucao || $temAberto) ? 'problema' : 'em_andamento',
                    'gps_registrado' => $gravarCoordenadasCheckout,
                    'modo_treinamento' => $isTraining,
                    'tem_faltante'  => $temFaltante,
                    'tem_devolucao' => $temDevolucao,
                    'tem_aberto'    => $temAberto,
                    'total_levas'   => $totalLevasInseridas
                ]
            ];
            $this->saveOfflineOperation($operationId, $id, 'checkout', $payload, 200);
            return $this->json($response, $payload);

        } catch (\Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('[Checkout] Erro: ' . $e->getMessage());
            error_log('[Checkout] Stack trace: ' . $e->getTraceAsString());
            return $this->json($response, [
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
        /**
     * GET /v1/frota/entregas/{id}/levas
     * Retorna todas as levas registradas para uma entrega.
     *
     * 🔥 NOVO 2026-09-25 (ENTREGAS PARCIAIS):
     *   Usado pelo acerto de embarque para mostrar o histórico de descidas
     *   parciais de cada item.
     */
    public function listarLevas(Request $request, Response $response, array $args): Response
    {
        $entregaId = (int)$args['id'];

        if ($entregaId <= 0) {
            return $this->json($response, [
                'success' => false,
                'error' => 'ID da entrega inválido'
            ], 400);
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    l.id,
                    l.checklist_id,
                    l.item_id,
                    l.referencia,
                    l.quantidade,
                    l.foto_url,
                    l.observacao,
                    l.registrado_em,
                    l.registrado_por,
                    u.username AS registrado_por_nome
                FROM frota_checklist_entrega_leva l
                LEFT JOIN usuario u ON u.idusuario = l.registrado_por
                WHERE l.entrega_id = :entrega_id
                ORDER BY l.item_id ASC, l.registrado_em ASC
            ");
            $stmt->execute(['entrega_id' => $entregaId]);
            $levas = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Agrupar por item
            $porItem = [];
            foreach ($levas as $leva) {
                $itemId = (int)$leva['item_id'];
                if (!isset($porItem[$itemId])) {
                    $porItem[$itemId] = [
                        'item_id' => $itemId,
                        'referencia' => $leva['referencia'],
                        'total_levas' => 0,
                        'total_quantidade' => 0,
                        'levas' => []
                    ];
                }
                $porItem[$itemId]['levas'][] = [
                    'id' => (int)$leva['id'],
                    'quantidade' => (float)$leva['quantidade'],
                    'foto_url' => $leva['foto_url'],
                    'observacao' => $leva['observacao'],
                    'registrado_em' => $leva['registrado_em'],
                    'registrado_por_nome' => $leva['registrado_por_nome']
                ];
                $porItem[$itemId]['total_levas']++;
                $porItem[$itemId]['total_quantidade'] += (float)$leva['quantidade'];
            }

            return $this->json($response, [
                'success' => true,
                'data' => [
                    'entrega_id' => $entregaId,
                    'total_levas' => count($levas),
                    'itens' => array_values($porItem)
                ]
            ]);

        } catch (\Exception $e) {
            error_log('[Levas] Erro ao listar: ' . $e->getMessage());
            return $this->json($response, [
                'success' => false,
                'error' => 'Erro ao carregar levas: ' . $e->getMessage()
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
        $operationId = $this->getOfflineOperationId($request, $input);
        $previousOperation = $this->getOfflineOperation($operationId);
        if ($previousOperation) {
            return $this->json($response, $previousOperation['response'], $previousOperation['status_code']);
        }
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
        
        $payload = [
            'success' => true,
            'message' => 'Falha registrada com sucesso',
            'data' => [
                'entrega_id' => $id,
                'status' => $novoStatus,
                'tentativas' => $tentativas,
                'proxima_tentativa' => $proximaTentativa
            ]
        ];
        $this->saveOfflineOperation($operationId, $id, 'falha', $payload, 200);
        return $this->json($response, $payload);
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
        $user = $request->getAttribute('user') ?? [];
        $permissoes = $user['permissoes'] ?? [];
        $isAdmin = (bool)($user['is_admin'] ?? false) || in_array('admin', $permissoes, true);
        $temAcessoGestao = $isAdmin
            || in_array('frota', $permissoes, true)
            || in_array('gestao-cargas', $permissoes, true);

        if ($desktop) return $temAcessoGestao;
        if ($isAdmin) return true;

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

    private function getOfflineOperationId(Request $request, array $input): ?string
    {
        $operationId = trim($input['operation_id'] ?? $request->getHeaderLine('X-Offline-Operation-Id'));
        return $operationId !== '' ? substr($operationId, 0, 160) : null;
    }

    private function getOfflineOperation(?string $operationId): ?array
    {
        if (!$operationId) return null;

        try {
            if (!$this->offlineOperationTableExists()) return null;
            $stmt = $this->pdo->prepare("
                SELECT response_body, status_code
                FROM frota_operacao_offline
                WHERE operation_id = :operation_id
            ");
            $stmt->execute(['operation_id' => $operationId]);
            $operation = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$operation) return null;

            return [
                'response' => json_decode($operation['response_body'], true) ?? [],
                'status_code' => (int)$operation['status_code']
            ];
        } catch (\Throwable $exception) {
            error_log('Idempotência offline indisponível: ' . $exception->getMessage());
            return null;
        }
    }

    private function saveOfflineOperation(?string $operationId, int $entregaId, string $acao, array $payload, int $statusCode): void
    {
        if (!$operationId) return;

        try {
            if (!$this->offlineOperationTableExists()) return;
            $stmt = $this->pdo->prepare("
                INSERT INTO frota_operacao_offline
                    (operation_id, entrega_id, acao, response_body, status_code, created_at)
                VALUES
                    (:operation_id, :entrega_id, :acao, :response_body, :status_code, NOW())
                ON CONFLICT (operation_id) DO NOTHING
            ");
            $stmt->execute([
                'operation_id' => $operationId,
                'entrega_id' => $entregaId,
                'acao' => $acao,
                'response_body' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'status_code' => $statusCode
            ]);
        } catch (\Throwable $exception) {
            error_log('Não foi possível persistir operação offline: ' . $exception->getMessage());
        }
    }

    private function offlineOperationTableExists(): bool
    {
        static $available;
        if ($available !== null) return $available;

        try {
            $available = (bool)$this->pdo->query("SELECT to_regclass('public.frota_operacao_offline')")->fetchColumn();
        } catch (\Throwable $exception) {
            $available = false;
        }

        return $available;
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