<?php
// src/Controllers/Frota/MotoristaController.php

namespace Nutricional\Controllers\Frota;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class MotoristaController
{
    private $pdo;
    
    public function __construct()
    {
        $this->pdo = \getPDO();
    }
    
    /**
     * GET /v1/frota/motoristas
     * Listar motoristas com filtros
     */
    /**
     * Garante que o usuário autenticado só acesse dados do próprio motorista,
     * a menos que seja admin ou tenha permissão de gestão de frota.
     */
    private function usuarioPodeAcessarMotorista(Request $request, int $motoristaId): bool
    {
        $user = $request->getAttribute('user') ?? [];
        $permissoes = $user['permissoes'] ?? [];
        $isAdmin = (bool)($user['is_admin'] ?? false) || in_array('admin', $permissoes, true);
        if ($isAdmin || in_array('frota', $permissoes, true) || in_array('gestao-cargas', $permissoes, true)) {
            return true;
        }
        $motoristaAutenticado = (int)($user['motorista_id'] ?? 0);
        return $motoristaAutenticado > 0 && $motoristaAutenticado === $motoristaId;
    }

    private function usuarioTemAcessoGestao(Request $request): bool
    {
        $user = $request->getAttribute('user') ?? [];
        $permissoes = $user['permissoes'] ?? [];

        return (bool)($user['is_admin'] ?? false)
            || in_array('admin', $permissoes, true)
            || in_array('frota', $permissoes, true)
            || in_array('gestao-cargas', $permissoes, true);
    }

    public function painelApp(Request $request, Response $response): Response
    {
        if (!$this->usuarioTemAcessoGestao($request)) {
            return $this->json($response, ['success' => false, 'error' => 'Acesso não autorizado'], 403);
        }

        $stmt = $this->pdo->query("
            SELECT
                m.id,
                m.nome,
                m.status,
                m.telefone,
                m.latitude,
                m.longitude,
                m.ultima_posicao,
                v.placa AS veiculo_placa,
                v.modelo AS veiculo_modelo,
                eb.id AS embarque_id,
                eb.numero_embarque,
                eb.status AS embarque_status,
                eb.data_saida,
                COALESCE(entregas.total, 0) AS total_entregas,
                COALESCE(entregas.concluidas, 0) AS entregas_concluidas,
                COALESCE(entregas.em_entrega, 0) AS entregas_em_andamento,
                COALESCE(entregas.pendentes, 0) AS entregas_pendentes,
                COALESCE(entregas.problemas, 0) AS entregas_problemas
            FROM frota_motorista m
            LEFT JOIN LATERAL (
                SELECT embarque.*
                FROM frota_embarque embarque
                WHERE embarque.motorista_id = m.id
                  AND (
                      embarque.status IN ('planejado', 'em_andamento')
                      OR DATE(embarque.data_saida) = CURRENT_DATE
                  )
                ORDER BY
                    CASE WHEN embarque.status = 'em_andamento' THEN 0
                         WHEN embarque.status = 'planejado' THEN 1
                         ELSE 2 END,
                    embarque.data_saida DESC,
                    embarque.id DESC
                LIMIT 1
            ) eb ON TRUE
            LEFT JOIN frota_veiculo v ON v.id = COALESCE(eb.veiculo_id, m.veiculo_atual_id)
            LEFT JOIN LATERAL (
                SELECT
                    COUNT(*) AS total,
                    COUNT(*) FILTER (WHERE e.status IN ('entregue', 'entregue_com_problema')) AS concluidas,
                    COUNT(*) FILTER (WHERE e.status = 'em_entrega') AS em_entrega,
                    COUNT(*) FILTER (WHERE e.status = 'pendente') AS pendentes,
                    COUNT(*) FILTER (WHERE e.status IN ('falha', 'entregue_com_problema')) AS problemas
                FROM frota_entrega e
                WHERE e.embarque_id = eb.id
            ) entregas ON TRUE
            WHERE m.status = 'ativo'
            ORDER BY
                CASE WHEN eb.status = 'em_andamento' THEN 0
                     WHEN eb.status = 'planejado' THEN 1
                     ELSE 2 END,
                m.nome
        ");

        $motoristas = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $resumo = [
            'motoristas' => count($motoristas),
            'em_rota' => 0,
            'com_rota' => 0,
            'total_entregas' => 0,
            'entregas_concluidas' => 0,
            'entregas_pendentes' => 0,
            'problemas' => 0,
        ];

        foreach ($motoristas as &$motorista) {
            foreach (['total_entregas', 'entregas_concluidas', 'entregas_em_andamento', 'entregas_pendentes', 'entregas_problemas'] as $campo) {
                $motorista[$campo] = (int)$motorista[$campo];
            }
            $motorista['progresso'] = $motorista['total_entregas'] > 0
                ? round(($motorista['entregas_concluidas'] / $motorista['total_entregas']) * 100, 1)
                : 0;

            $resumo['em_rota'] += $motorista['embarque_status'] === 'em_andamento' ? 1 : 0;
            $resumo['com_rota'] += $motorista['embarque_id'] ? 1 : 0;
            $resumo['total_entregas'] += $motorista['total_entregas'];
            $resumo['entregas_concluidas'] += $motorista['entregas_concluidas'];
            $resumo['entregas_pendentes'] += $motorista['entregas_pendentes'] + $motorista['entregas_em_andamento'];
            $resumo['problemas'] += $motorista['entregas_problemas'];
        }
        unset($motorista);

        return $this->json($response, [
            'success' => true,
            'data' => $motoristas,
            'resumo' => $resumo,
        ]);
    }

    public function listar(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $user = $request->getAttribute('user') ?? [];
        $permissoes = $user['permissoes'] ?? [];
        $isAdmin = (bool)($user['is_admin'] ?? false) || in_array('admin', $permissoes, true);
        $temAcessoGestao = $isAdmin || in_array('frota', $permissoes, true) || in_array('gestao-cargas', $permissoes, true);
        $motoristaAutenticado = (int)($user['motorista_id'] ?? 0);

        // Motorista comum: só enxerga o próprio cadastro, nunca a lista completa.
        if (!$temAcessoGestao) {
            if ($motoristaAutenticado <= 0) {
                return $this->json($response, ['success' => false, 'error' => 'Acesso não autorizado'], 403);
            }
            $params['id_unico'] = $motoristaAutenticado;
        }
        
        $filtros = [];
        $bindParams = [];
        
        if (!empty($params['status'])) {
            $filtros[] = "m.status = :status";
            $bindParams['status'] = $params['status'];
        }
        
        if (!empty($params['busca'])) {
            $filtros[] = "(m.nome ILIKE :busca OR m.cpf ILIKE :busca2 OR m.telefone ILIKE :busca3)";
            $bindParams['busca'] = "%{$params['busca']}%";
            $bindParams['busca2'] = "%{$params['busca']}%";
            $bindParams['busca3'] = "%{$params['busca']}%";
        }
        
        if (!empty($params['veiculo_id'])) {
            $filtros[] = "m.veiculo_atual_id = :veiculo_id";
            $bindParams['veiculo_id'] = (int)$params['veiculo_id'];
        }

        if (!empty($params['id_unico'])) {
            $filtros[] = "m.id = :id_unico";
            $bindParams['id_unico'] = (int)$params['id_unico'];
        }
        
        $where = !empty($filtros) ? 'WHERE ' . implode(' AND ', $filtros) : '';
        
        $limite = max(1, min((int)($params['limite'] ?? 20), 100));
        $pagina = max(1, (int)($params['pagina'] ?? 1));
        $offset = ($pagina - 1) * $limite;
        
        $sql = "
            SELECT 
                m.*,
                v.placa as veiculo_placa,
                v.modelo as veiculo_modelo,
                COUNT(DISTINCT e.id) as total_entregas,
                COUNT(DISTINCT CASE WHEN e.status = 'entregue' THEN e.id END) as entregas_concluidas,
                COUNT(DISTINCT CASE WHEN e.status = 'pendente' THEN e.id END) as entregas_pendentes,
                COUNT(DISTINCT CASE WHEN e.status = 'falha' THEN e.id END) as entregas_falha,
                COALESCE(SUM(COALESCE((SELECT SUM(pi.valortotal) FROM pedido_item pi WHERE pi.idpedido IN (SELECT value::integer FROM regexp_split_to_table(COALESCE(e.pedidos_ids, ''), ',') value WHERE value ~ '^[0-9]+$')), e.valor_total, 0)), 0) as valor_total_entregas,
                COALESCE(AVG(CASE WHEN e.status = 'entregue' THEN EXTRACT(EPOCH FROM (e.horario_entrega - e.horario_checkin))/60 END), 0) as tempo_medio_entrega
            FROM frota_motorista m
            LEFT JOIN frota_veiculo v ON v.id = m.veiculo_atual_id
            LEFT JOIN frota_embarque eb ON eb.motorista_id = m.id
            LEFT JOIN frota_entrega e ON e.embarque_id = eb.id
            {$where}
            GROUP BY m.id, v.placa, v.modelo
            ORDER BY m.nome ASC
            LIMIT :limite OFFSET :offset
        ";
        
        $stmt = $this->pdo->prepare($sql);
        foreach ($bindParams as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limite', $limite, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        
        $motoristas = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $sqlCount = "SELECT COUNT(*) FROM frota_motorista m {$where}";
        $stmtCount = $this->pdo->prepare($sqlCount);
        foreach ($bindParams as $key => $val) {
            $stmtCount->bindValue($key, $val);
        }
        $stmtCount->execute();
        $total = (int)$stmtCount->fetchColumn();
        
        return $this->json($response, [
            'success' => true,
            'data' => $motoristas,
            'pagination' => [
                'total' => $total,
                'pagina' => $pagina,
                'limite' => $limite,
                'total_paginas' => ceil($total / $limite)
            ]
        ]);
    }
    
    /**
     * GET /v1/frota/motoristas/{id}
     * Buscar motorista específico com detalhes
     */
    public function buscar(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        if (!$this->usuarioPodeAcessarMotorista($request, $id)) {
            return $this->json($response, ['success' => false, 'error' => 'Acesso não autorizado'], 403);
        }
        
        $sql = "
            SELECT 
                m.*,
                v.placa as veiculo_placa,
                v.modelo as veiculo_modelo,
                v.tipo as veiculo_tipo,
                v.latitude as veiculo_lat,
                v.longitude as veiculo_lng,
                COUNT(DISTINCT eb.id) as total_embarques,
                COUNT(DISTINCT e.id) as total_entregas,
                COUNT(DISTINCT CASE WHEN e.status = 'entregue' THEN e.id END) as entregas_concluidas,
                COUNT(DISTINCT CASE WHEN e.status = 'pendente' THEN e.id END) as entregas_pendentes,
                COUNT(DISTINCT CASE WHEN e.status = 'falha' THEN e.id END) as entregas_falha,
                COALESCE(SUM(COALESCE((SELECT SUM(pi.valortotal) FROM pedido_item pi WHERE pi.idpedido IN (SELECT value::integer FROM regexp_split_to_table(COALESCE(e.pedidos_ids, ''), ',') value WHERE value ~ '^[0-9]+$')), e.valor_total, 0)), 0) as valor_total,
                COALESCE(SUM(CASE WHEN e.status = 'entregue' THEN COALESCE((SELECT SUM(pi.valortotal) FROM pedido_item pi WHERE pi.idpedido IN (SELECT value::integer FROM regexp_split_to_table(COALESCE(e.pedidos_ids, ''), ',') value WHERE value ~ '^[0-9]+$')), e.valor_total, 0) END), 0) as valor_entregue,
                COALESCE(AVG(CASE WHEN e.status = 'entregue' THEN EXTRACT(EPOCH FROM (e.horario_entrega - e.horario_checkin))/60 END), 0) as tempo_medio_entrega,
                (SELECT COUNT(*) FROM frota_notificacao WHERE motorista_id = m.id AND lida = false) as notificacoes_nao_lidas
            FROM frota_motorista m
            LEFT JOIN frota_veiculo v ON v.id = m.veiculo_atual_id
            LEFT JOIN frota_embarque eb ON eb.motorista_id = m.id
            LEFT JOIN frota_entrega e ON e.embarque_id = eb.id
            WHERE m.id = :id
            GROUP BY m.id, v.placa, v.modelo, v.tipo, v.latitude, v.longitude
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $motorista = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$motorista) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Motorista não encontrado'
            ], 404);
        }
        
        // Buscar últimas entregas
        $stmt = $this->pdo->prepare("
            SELECT 
                e.*,
                eb.numero_embarque,
                v.placa
            FROM frota_entrega e
            LEFT JOIN frota_embarque eb ON eb.id = e.embarque_id
            LEFT JOIN frota_veiculo v ON v.id = eb.veiculo_id
            WHERE eb.motorista_id = :motorista_id
            ORDER BY e.created_at DESC
            LIMIT 10
        ");
        $stmt->execute(['motorista_id' => $id]);
        $motorista['ultimas_entregas'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Buscar estatísticas por dia
        $stmt = $this->pdo->prepare("
            SELECT 
                DATE(e.horario_entrega) as data,
                COUNT(*) as total,
                SUM(COALESCE((SELECT SUM(pi.valortotal) FROM pedido_item pi WHERE pi.idpedido IN (SELECT value::integer FROM regexp_split_to_table(COALESCE(e.pedidos_ids, ''), ',') value WHERE value ~ '^[0-9]+$')), e.valor_total, 0)) as valor
            FROM frota_entrega e
            LEFT JOIN frota_embarque eb ON eb.id = e.embarque_id
            WHERE eb.motorista_id = :motorista_id
              AND e.status = 'entregue'
              AND e.horario_entrega >= NOW() - INTERVAL '30 days'
            GROUP BY DATE(e.horario_entrega)
            ORDER BY data DESC
        ");
        $stmt->execute(['motorista_id' => $id]);
        $motorista['entregas_por_dia'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        return $this->json($response, [
            'success' => true,
            'data' => $motorista
        ]);
    }
    
    public function criar(Request $request, Response $response): Response
{
    $data = json_decode($request->getBody()->getContents(), true) ?? [];
    
    // Verificar se já existe pelo erp_id
    if (!empty($data['erp_id'])) {
        $stmt = $this->pdo->prepare("SELECT id FROM frota_motorista WHERE erp_id = :erp_id");
        $stmt->execute(['erp_id' => $data['erp_id']]);
        if ($stmt->fetch()) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Motorista já cadastrado'
            ], 400);
        }
    }
    
    // 🔥 AGORA COM TODAS AS COLUNAS
    $stmt = $this->pdo->prepare("
        INSERT INTO frota_motorista (
            erp_id, nome, cpf, cnh, telefone, email, 
            endereco, bairro, cidade, uf, cep, complemento, numero,
            status, created_at, updated_at
        ) VALUES (
            :erp_id, :nome, :cpf, :cnh, :telefone, :email,
            :endereco, :bairro, :cidade, :uf, :cep, :complemento, :numero,
            :status, NOW(), NOW()
        ) RETURNING id
    ");
    
    $stmt->execute([
        'erp_id' => $data['erp_id'] ?? null,
        'nome' => $data['nome'],
        'cpf' => $data['cpf'] ?? null,
        'cnh' => $data['cnh'] ?? '',
        'telefone' => $data['telefone'] ?? null,
        'email' => $data['email'] ?? null,
        'endereco' => $data['endereco'] ?? null,
        'bairro' => $data['bairro'] ?? null,
        'cidade' => $data['cidade'] ?? null,
        'uf' => $data['uf'] ?? null,
        'cep' => $data['cep'] ?? null,
        'complemento' => $data['complemento'] ?? null,
        'numero' => $data['numero'] ?? null,
        'status' => $data['status'] ?? 'ativo'
    ]);
    
    $id = $stmt->fetchColumn();
    
    return $this->json($response, [
        'success' => true,
        'message' => 'Motorista cadastrado com sucesso',
        'data' => ['id' => $id]
    ]);
}
    
    /**
     * PUT /v1/frota/motoristas/{id}
     * Atualizar motorista
     */
    public function atualizar(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $input = json_decode($request->getBody()->getContents(), true) ?? [];
        
        // Verificar se existe
        $stmt = $this->pdo->prepare("SELECT id FROM frota_motorista WHERE id = :id");
        $stmt->execute(['id' => $id]);
        if (!$stmt->fetch()) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Motorista não encontrado'
            ], 404);
        }
        
        // Construir SET dinâmico
        $camposPermitidos = [
            'nome', 'cpf', 'cnh', 'categoria_cnh', 'data_validade_cnh',
            'telefone', 'telefone_emergencia', 'email', 'data_nascimento',
            'data_admissao', 'endereco', 'status', 'veiculo_atual_id'
        ];
        
        $sets = [];
        $bindParams = ['id' => $id];
        
        foreach ($camposPermitidos as $campo) {
            if (array_key_exists($campo, $input)) {
                $sets[] = "{$campo} = :{$campo}";
                $bindParams[$campo] = $input[$campo];
            }
        }
        
        if (empty($sets)) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Nenhum campo para atualizar'
            ], 400);
        }
        
        $sets[] = "updated_at = NOW()";
        $sql = "UPDATE frota_motorista SET " . implode(', ', $sets) . " WHERE id = :id";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bindParams);
        
        return $this->json($response, [
            'success' => true,
            'message' => 'Motorista atualizado com sucesso'
        ]);
    }
    
    /**
     * DELETE /v1/frota/motoristas/{id}
     * Deletar motorista (soft delete - inativa)
     */
    public function deletar(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        
        // Verificar se existe
        $stmt = $this->pdo->prepare("SELECT id FROM frota_motorista WHERE id = :id");
        $stmt->execute(['id' => $id]);
        if (!$stmt->fetch()) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Motorista não encontrado'
            ], 404);
        }
        
        // Verificar se tem embarques ativos
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM frota_embarque 
            WHERE motorista_id = :id AND status IN ('planejado', 'em_andamento')
        ");
        $stmt->execute(['id' => $id]);
        if ((int)$stmt->fetchColumn() > 0) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Motorista possui embarques ativos. Não é possível excluir.'
            ], 400);
        }
        
        // Soft delete (inativar)
        $stmt = $this->pdo->prepare("
            UPDATE frota_motorista 
            SET status = 'inativo', updated_at = NOW() 
            WHERE id = :id
        ");
        $stmt->execute(['id' => $id]);
        
        return $this->json($response, [
            'success' => true,
            'message' => 'Motorista inativado com sucesso'
        ]);
    }
    
    /**
     * GET /v1/frota/motoristas/{id}/entregas
     * Listar entregas do motorista com filtros
     */
    public function entregas(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        if (!$this->usuarioPodeAcessarMotorista($request, $id)) {
            return $this->json($response, ['success' => false, 'error' => 'Acesso não autorizado'], 403);
        }
        $params = $request->getQueryParams();
        
        $filtros = ["eb.motorista_id = :motorista_id"];
        $bindParams = ['motorista_id' => $id];
        
        if (!empty($params['status'])) {
            $filtros[] = "e.status = :status";
            $bindParams['status'] = $params['status'];
        }
        
        if (!empty($params['data_inicio']) && !empty($params['data_fim'])) {
            $filtros[] = "DATE(e.created_at) BETWEEN :data_inicio AND :data_fim";
            $bindParams['data_inicio'] = $params['data_inicio'];
            $bindParams['data_fim'] = $params['data_fim'];
        }
        
        $where = 'WHERE ' . implode(' AND ', $filtros);
        
        $limite = (int)($params['limite'] ?? 50);
        $pagina = (int)($params['pagina'] ?? 1);
        $offset = ($pagina - 1) * $limite;
        
        $sql = "
            SELECT 
                e.*,
                eb.numero_embarque,
                eb.data_saida,
                v.placa,
                v.modelo
            FROM frota_entrega e
            LEFT JOIN frota_embarque eb ON eb.id = e.embarque_id
            LEFT JOIN frota_veiculo v ON v.id = eb.veiculo_id
            {$where}
            ORDER BY e.created_at DESC
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
        
        $sqlCount = "SELECT COUNT(*) FROM frota_entrega e LEFT JOIN frota_embarque eb ON eb.id = e.embarque_id {$where}";
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
    
// v1/src/Controllers/Frota/MotoristaController.php

    /**
     * GET /v1/frota/motoristas/{id}/entregas/hoje
     * Entregas do motorista para hoje
     *
     * 🔥 CORRIGIDO 2026-09-24:
     *   - O `checklist` de cada entrega agora vem do ERP (pedido_item),
     *     não da tabela frota_checklist_entrega (que só tem dados
     *     DEPOIS do checkout).
     *   - A tabela frota_checklist_entrega é usada apenas para ENRIQUECER
     *     os itens previstos com o que já foi registrado (status,
     *     quantidade_entregue, motivo, foto_url).
     *   - Assim o modal de checkout sempre mostra os itens do pedido.
     *
     * 🔥 ENRIQUECIDO 2026-09-25 (Pacote 1.5):
     *   - Adicionado `embarque_info` no payload raiz.
     *   - Adicionado `fotos[]` e outros metadados em cada entrega.
     *   - Adicionado `resumo_itens` em cada entrega.
     *   - Adicionado `levas[]` no checklist de cada item.
     */
    public function entregasHoje(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        if (!$this->usuarioPodeAcessarMotorista($request, $id)) {
            return $this->json($response, ['success' => false, 'error' => 'Acesso não autorizado'], 403);
        }

        $sql = "
            SELECT 
                e.*,
                eb.numero_embarque,
                eb.data_saida,
                eb.veiculo_id,
                v.placa,
                v.modelo,
                v.latitude as veiculo_lat,
                v.longitude as veiculo_lng,
                eb.id as embarque_id,
                eb.updated_at as embarque_updated_at
            FROM frota_entrega e
            LEFT JOIN frota_embarque eb ON eb.id = e.embarque_id
            LEFT JOIN frota_veiculo v ON v.id = eb.veiculo_id
           WHERE eb.motorista_id = :motorista_id
  AND (
      DATE(e.created_at) = CURRENT_DATE
      OR e.status IN ('pendente', 'em_entrega')
      OR e.status IN ('entregue', 'entregue_com_problema', 'falha', 'cancelada')
  )
ORDER BY e.ordem_entrega ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['motorista_id' => $id]);
        $entregas = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Fallback: se não achou nada hoje, busca do último embarque
        if (empty($entregas)) {
            $stmtLast = $this->pdo->prepare("
                SELECT 
                    e.*,
                    eb.numero_embarque,
                    eb.data_saida,
                    eb.veiculo_id,
                    v.placa,
                    v.modelo,
                    v.latitude as veiculo_lat,
                    v.longitude as veiculo_lng,
                    eb.id as embarque_id,
                    eb.updated_at as embarque_updated_at
                FROM frota_entrega e
                LEFT JOIN frota_embarque eb ON eb.id = e.embarque_id
                LEFT JOIN frota_veiculo v ON v.id = eb.veiculo_id
                WHERE eb.motorista_id = :motorista_id
                  AND eb.id = (
                      SELECT id FROM frota_embarque 
                      WHERE motorista_id = :motorista_id 
                      ORDER BY created_at DESC LIMIT 1
                  )
                ORDER BY e.ordem_entrega ASC
            ");
            $stmtLast->execute(['motorista_id' => $id]);
            $entregas = $stmtLast->fetchAll(\PDO::FETCH_ASSOC);
        }

        if (empty($entregas)) {
            return $this->json($response, [
                'success' => true,
                'data' => [
                    'entregas' => [],
                    'embarque_info' => null,
                    'rota_ativa' => null,
                    'proxima_entrega' => null,
                    'resumo' => [ 'total' => 0, 'pendentes' => 0, 'em_entrega' => 0, 'entregues' => 0, 'falhas' => 0, 'progresso' => 0 ]
                ]
            ]);
        }

        // ================================================================
        // 🔥 CORREÇÃO PRINCIPAL:
        // 1) Buscar os ITENS DO ERP (pedido_item) via pedidos_ids de cada entrega
        // 2) Buscar o checklist JÁ REGISTRADO em frota_checklist_entrega
        // 3) Cruzar os dois: o que veio do ERP + o que já foi registrado
        // 4) Buscar as LEVAS registradas para cada item do checklist
        // ================================================================
        $entregaIds = array_column($entregas, 'id');

        // ── 1. Itens do ERP para todas as entregas
        $pedidosErpIds = [];
        foreach ($entregas as $entrega) {
            $ids = [];
            if (!empty($entrega['pedidos_ids'])) {
                $ids = array_filter(array_map('intval', explode(',', $entrega['pedidos_ids'])));
            }
            if (empty($ids) && !empty($entrega['pedido_id'])) {
                $ids = [(int)$entrega['pedido_id']];
            }
            foreach ($ids as $pid) {
                $pedidosErpIds[$pid] = true;
            }
        }
        $pedidosErpIds = array_keys($pedidosErpIds);

        $itensErpPorPedido = [];
        if (!empty($pedidosErpIds)) {
            $placeholders = implode(',', array_fill(0, count($pedidosErpIds), '?'));

            $stmtItens = $this->pdo->prepare("
                SELECT
                    pi.idpedido,
                    pi.iditem,
                    i.referencia,
                    i.descricao,
                    pi.qt AS quantidade_prevista,
                    COALESCE(i.pesobruto, 0) AS peso_bruto,
                    COALESCE((select descricao from unidade where idunidade = i.idunidadebasica ), 'UN') AS unidade
                FROM pedido_item pi
                INNER JOIN item i ON i.iditem = pi.iditem
                WHERE pi.idpedido IN ({$placeholders})
                  AND pi.ativo = 'S'
                ORDER BY pi.idpedido, i.referencia
            ");
            $stmtItens->execute($pedidosErpIds);

            foreach ($stmtItens->fetchAll(\PDO::FETCH_ASSOC) as $itemErp) {
                $itensErpPorPedido[$itemErp['idpedido']][] = $itemErp;
            }
        }

        // ── 2. Checklist já registrado (só para enriquecer)
        $checklistRegistradoPorEntrega = [];
        $levasPorChecklist = [];
        if ($entregaIds) {
            $placeholders = implode(',', array_fill(0, count($entregaIds), '?'));
            $stmtChecklist = $this->pdo->prepare("
                SELECT id, entrega_id, item_id, referencia, descricao,
                       quantidade_prevista, quantidade_entregue, status, motivo, foto_url
                FROM frota_checklist_entrega
                WHERE entrega_id IN ({$placeholders})
                ORDER BY entrega_id, item_id
            ");
            $stmtChecklist->execute($entregaIds);
            $checklistItems = $stmtChecklist->fetchAll(\PDO::FETCH_ASSOC);
            
            $checklistIds = [];
            foreach ($checklistItems as $item) {
                $checklistRegistradoPorEntrega[$item['entrega_id']][$item['item_id']] = $item;
                $checklistIds[] = $item['id'];
            }

            // ── 2.1. Buscar as LEVAS registradas para cada checklist_id
            if (!empty($checklistIds)) {
                $placeholdersLevas = implode(',', array_fill(0, count($checklistIds), '?'));
                $stmtLevas = $this->pdo->prepare("
                    SELECT checklist_id, id, quantidade, foto_url, observacao, registrado_em, latitude, longitude
                    FROM frota_checklist_entrega_leva
                    WHERE checklist_id IN ({$placeholdersLevas})
                    ORDER BY registrado_em ASC
                ");
                $stmtLevas->execute($checklistIds);
                foreach ($stmtLevas->fetchAll(\PDO::FETCH_ASSOC) as $leva) {
                    $levasPorChecklist[$leva['checklist_id']][] = $leva;
                }
            }
        }

        // ── 3. Cruzar: para cada entrega, montar o checklist completo
        foreach ($entregas as &$entrega) {
            $entregaId = (int)$entrega['id'];

            $idsDaEntrega = [];
               // 🔥 Pacote 3 — M4-fix Camada 2 (2026-09-25):
            //   PostgreSQL NUMERIC volta como STRING no JSON (via PDO).
            //   O frontend usa `typeof x === 'number'` e falha silenciosamente.
            //   Forçamos cast para float/null aqui, uma vez, e o resto do app
            //   (motorista, admin, futuros consumidores) já recebe number.
            foreach (['latitude', 'longitude', 'veiculo_lat', 'veiculo_lng'] as $campoCoordenada) {
                if (array_key_exists($campoCoordenada, $entrega)) {
                    $entrega[$campoCoordenada] = ($entrega[$campoCoordenada] === null || $entrega[$campoCoordenada] === '')
                        ? null
                        : (float)$entrega[$campoCoordenada];
                }
            }

            $idsDaEntrega = [];
            if (!empty($entrega['pedidos_ids'])) {
                $idsDaEntrega = array_filter(array_map('intval', explode(',', $entrega['pedidos_ids'])));
            }
            if (empty($idsDaEntrega) && !empty($entrega['pedido_id'])) {
                $idsDaEntrega = [(int)$entrega['pedido_id']];
            }
            if (!empty($entrega['pedidos_ids'])) {
                $idsDaEntrega = array_filter(array_map('intval', explode(',', $entrega['pedidos_ids'])));
            }
            if (empty($idsDaEntrega) && !empty($entrega['pedido_id'])) {
                $idsDaEntrega = [(int)$entrega['pedido_id']];
            }

            $itensConsolidados = [];
            foreach ($idsDaEntrega as $pid) {
                foreach ($itensErpPorPedido[$pid] ?? [] as $itemErp) {
                    $iditem = (int)$itemErp['iditem'];
                    if (!isset($itensConsolidados[$iditem])) {
                        $itensConsolidados[$iditem] = [
                            'item_id'              => $iditem,
                            'referencia'           => $itemErp['referencia'],
                            'descricao'            => $itemErp['descricao'],
                            'unidade'              => $itemErp['unidade'],
                            'peso_bruto'           => (float)$itemErp['peso_bruto'],
                            'quantidade_prevista'  => 0,
                            'quantidade_entregue'  => null,
                            'status'               => null,
                            'motivo'               => null,
                            'foto_url'             => null,
                            'levas'                => []
                        ];
                    }
                    $itensConsolidados[$iditem]['quantidade_prevista'] += (float)$itemErp['quantidade_prevista'];
                }
            }

            // Enriquecer com o checklist já registrado (se houver)
            foreach ($checklistRegistradoPorEntrega[$entregaId] ?? [] as $itemId => $registrado) {
                $itemId = (int)$itemId;
                if (isset($itensConsolidados[$itemId])) {
                    $itensConsolidados[$itemId]['quantidade_entregue'] = (float)$registrado['quantidade_entregue'];
                    $itensConsolidados[$itemId]['status']              = $registrado['status'];
                    $itensConsolidados[$itemId]['motivo']              = $registrado['motivo'];
                    $itensConsolidados[$itemId]['foto_url']            = $registrado['foto_url'];
                    $itensConsolidados[$itemId]['levas']               = $levasPorChecklist[$registrado['id']] ?? [];
                } else {
                    $itensConsolidados[$itemId] = [
                        'item_id'              => $itemId,
                        'referencia'           => $registrado['referencia'],
                        'descricao'            => $registrado['descricao'],
                        'unidade'              => 'UN',
                        'peso_bruto'           => 0,
                        'quantidade_prevista'  => (float)$registrado['quantidade_prevista'],
                        'quantidade_entregue'  => (float)$registrado['quantidade_entregue'],
                        'status'               => $registrado['status'],
                        'motivo'               => $registrado['motivo'],
                        'foto_url'             => $registrado['foto_url'],
                        'levas'                => $levasPorChecklist[$registrado['id']] ?? [],
                    ];
                }
            }

            $entrega['checklist'] = array_values($itensConsolidados);

            // ── 4. Adicionar fotos[] e outros metadados
            $entrega['fotos'] = [];
            if (!empty($entrega['foto_romaneio_url'])) {
                $entrega['fotos'][] = ['tipo' => 'romaneio', 'url' => $entrega['foto_romaneio_url']];
            }
            if (!empty($entrega['foto_item_url'])) {
                $entrega['fotos'][] = ['tipo' => 'item_geral', 'url' => $entrega['foto_item_url']];
            }
            if (!empty($entrega['foto_checkin_url'])) {
                $entrega['fotos'][] = ['tipo' => 'checkin', 'url' => $entrega['foto_checkin_url']];
            }
            foreach($entrega['checklist'] as $item) {
                if (!empty($item['foto_url'])) {
                    $entrega['fotos'][] = ['tipo' => 'item_checkout', 'url' => $item['foto_url'], 'referencia' => $item['referencia']];
                }
                if (!empty($item['levas'])) {
                    foreach($item['levas'] as $leva) {
                        if (!empty($leva['foto_url'])) {
                            $entrega['fotos'][] = ['tipo' => 'leva', 'url' => $leva['foto_url'], 'referencia' => $item['referencia']];
                        }
                    }
                }
            }

            // ── 5. Adicionar resumo_itens
            $totalItens = count($entrega['checklist']);
            $entregues = count(array_filter($entrega['checklist'], fn($i) => $i['status'] === 'entregue'));
            $faltantes = count(array_filter($entrega['checklist'], fn($i) => $i['status'] === 'faltante'));
            $devolvidos = count(array_filter($entrega['checklist'], fn($i) => $i['status'] === 'devolvido'));
            $entrega['resumo_itens'] = [
                'total' => $totalItens,
                'entregues' => $entregues,
                'faltantes' => $faltantes,
                'devolvidos' => $devolvidos,
            ];
        }
        unset($entrega);

        // ================================================================
        // 4. Resumo + próxima entrega + rota ativa + embarque_info
        // ================================================================
        $total = count($entregas);
        $pendentes  = count(array_filter($entregas, fn($e) => $e['status'] === 'pendente'));
        $emEntrega  = count(array_filter($entregas, fn($e) => $e['status'] === 'em_entrega'));
        $entregues  = count(array_filter($entregas, fn($e) => in_array($e['status'], ['entregue', 'entregue_com_problema'])));
        $falhas     = count(array_filter($entregas, fn($e) => $e['status'] === 'falha'));

        $proxima = null;
        foreach ($entregas as $e) {
            if (in_array($e['status'], ['pendente', 'em_entrega'])) {
                $proxima = $e;
                break;
            }
        }

        // 🔥 NOVO: Montar embarque_info a partir do primeiro item
        $embarque_info = null;
        if (!empty($entregas[0]['embarque_id'])) {
            $primeira_entrega = $entregas[0];
            $embarqueId = $primeira_entrega['embarque_id'];
            
            $stmtEmbarqueInfo = $this->pdo->prepare("
                SELECT 
                    e.id, e.numero_embarque, e.erp_embarque_id, e.nome_embarque as rota, e.status as status_embarque, e.data_saida, e.data_retorno,
                    v.placa as veiculo_placa, v.modelo as veiculo_modelo, m.nome as motorista_nome,
                    (SELECT COUNT(*) FROM frota_entrega WHERE embarque_id = e.id) as total_entregas,
                    (SELECT COUNT(*) FROM frota_entrega WHERE embarque_id = e.id AND status IN ('entregue', 'entregue_com_problema')) as entregas_concluidas,
                    (SELECT COALESCE(SUM(valor_total), 0) FROM frota_entrega WHERE embarque_id = e.id) as valor_total,
                    (SELECT COALESCE(SUM(peso_total), 0) FROM frota_entrega WHERE embarque_id = e.id) as peso_total
                FROM frota_embarque e
                LEFT JOIN frota_veiculo v ON v.id = e.veiculo_id
                LEFT JOIN frota_motorista m ON m.id = e.motorista_id
                WHERE e.id = :embarque_id
            ");
            $stmtEmbarqueInfo->execute(['embarque_id' => $embarqueId]);
            $info = $stmtEmbarqueInfo->fetch(\PDO::FETCH_ASSOC);
            if ($info) {
                $embarque_info = [
                    'id' => (int)$info['id'],
                    'numero_embarque' => $info['numero_embarque'],
                    'erp_id' => $info['erp_embarque_id'],
                    'rota' => $info['rota'],
                    'veiculo_placa' => $info['veiculo_placa'],
                    'veiculo_modelo' => $info['veiculo_modelo'],
                    'motorista_nome' => $info['motorista_nome'],
                    'valor_total' => (float)$info['valor_total'],
                    'peso_total' => (float)$info['peso_total'],
                    'total_entregas' => (int)$info['total_entregas'],
                    'entregas_concluidas' => (int)$info['entregas_concluidas'],
                    'status_embarque' => $info['status_embarque'],
                    'data_saida' => $info['data_saida'],
                    'data_retorno' => $info['data_retorno'],
                ];
            }
        }

        $stmtRota = $this->pdo->prepare("
            SELECT * FROM frota_embarque 
            WHERE motorista_id = :motorista_id 
              AND status = 'em_andamento'
            ORDER BY id DESC LIMIT 1
        ");
        $stmtRota->execute(['motorista_id' => $id]);
       $rotaAtiva = $stmtRota->fetch(\PDO::FETCH_ASSOC) ?: null;

        return $this->json($response, [
            'success' => true,
            'data' => [
                'entregas' => $entregas,
                'embarque_info' => $embarque_info,
                'rota_ativa' => $rotaAtiva,
                'proxima_entrega' => $proxima,
                'resumo' => [
                    'total' => $total,
                    'pendentes' => $pendentes,
                    'em_entrega' => $emEntrega,
                    'entregues' => $entregues,
                    'falhas' => $falhas,
                    'progresso' => $total > 0 ? round(($entregues / $total) * 100, 1) : 0
                ]
            ]
        ]);
    }
    
    /**
     * GET /v1/frota/motoristas/{id}/rota-ativa
     * Buscar rota ativa do motorista
     */
    public function rotaAtiva(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        if (!$this->usuarioPodeAcessarMotorista($request, $id)) {
            return $this->json($response, ['success' => false, 'error' => 'Acesso não autorizado'], 403);
        }
        
        $sql = "
            SELECT 
                eb.*,
                v.placa,
                v.modelo,
                v.latitude as veiculo_lat,
                v.longitude as veiculo_lng,
                COUNT(e.id) as total_entregas,
                COUNT(CASE WHEN e.status = 'entregue' THEN 1 END) as entregues,
                COUNT(CASE WHEN e.status = 'pendente' THEN 1 END) as pendentes,
                COUNT(CASE WHEN e.status = 'em_entrega' THEN 1 END) as em_andamento,
                COUNT(CASE WHEN e.status = 'falha' THEN 1 END) as falhas
            FROM frota_embarque eb
            LEFT JOIN frota_veiculo v ON v.id = eb.veiculo_id
            LEFT JOIN frota_entrega e ON e.embarque_id = eb.id
            WHERE eb.motorista_id = :motorista_id
              AND eb.status IN ('planejado', 'em_andamento')
            GROUP BY eb.id, v.placa, v.modelo, v.latitude, v.longitude
            ORDER BY eb.data_saida DESC
            LIMIT 1
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['motorista_id' => $id]);
        $rota = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$rota) {
            return $this->json($response, [
                'success' => true,
                'data' => null,
                'message' => 'Nenhuma rota ativa encontrada'
            ]);
        }
        
        // Buscar entregas da rota
        $stmt = $this->pdo->prepare("
            SELECT 
                e.*,
                c.nome as cliente_nome_completo,
                c.telefone as cliente_telefone,
                c.latitude as cliente_lat,
                c.longitude as cliente_lng
            FROM frota_entrega e
            LEFT JOIN frota_cliente c ON c.id = e.cliente_id
            WHERE e.embarque_id = :embarque_id
            ORDER BY e.ordem_entrega ASC
        ");
        $stmt->execute(['embarque_id' => $rota['id']]);
        $rota['entregas'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        return $this->json($response, [
            'success' => true,
            'data' => $rota
        ]);
    }
    
    /**
     * POST /v1/frota/motoristas/{id}/rota/iniciar
     * Iniciar rota do motorista
     */
    public function iniciarRota(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        
        // Buscar próximo embarque planejado
        $stmt = $this->pdo->prepare("
            SELECT id, veiculo_id FROM frota_embarque 
            WHERE motorista_id = :motorista_id 
              AND status = 'planejado'
              AND data_saida <= CURRENT_DATE
            ORDER BY data_saida ASC
            LIMIT 1
        ");
        $stmt->execute(['motorista_id' => $id]);
        $embarque = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$embarque) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Nenhum embarque planejado encontrado'
            ], 404);
        }
        
        // Iniciar embarque
        $stmt = $this->pdo->prepare("
            UPDATE frota_embarque 
            SET status = 'em_andamento', 
                horario_saida = NOW(),
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute(['id' => $embarque['id']]);
        
        // Atualizar veículo
        $stmt = $this->pdo->prepare("
            UPDATE frota_veiculo 
            SET status = 'em_rota',
                updated_at = NOW()
            WHERE id = :veiculo_id
        ");
        $stmt->execute(['veiculo_id' => $embarque['veiculo_id']]);
        
        // Atualizar motorista
        $stmt = $this->pdo->prepare("
            UPDATE frota_motorista 
            SET veiculo_atual_id = :veiculo_id,
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            'id' => $id,
            'veiculo_id' => $embarque['veiculo_id']
        ]);
        
        // Registrar log
        $this->registrarLog($embarque['id'], 'iniciar_rota', 'Rota iniciada pelo motorista');
        
        return $this->json($response, [
            'success' => true,
            'message' => 'Rota iniciada com sucesso',
            'data' => [
                'embarque_id' => $embarque['id'],
                'status' => 'em_andamento',
                'horario_saida' => date('Y-m-d H:i:s')
            ]
        ]);
    }
    
    /**
     * POST /v1/frota/motoristas/{id}/rota/finalizar
     * Finalizar rota do motorista
     */
    public function finalizarRota(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        
        // Buscar embarque em andamento
        $stmt = $this->pdo->prepare("
            SELECT id, veiculo_id FROM frota_embarque 
            WHERE motorista_id = :motorista_id 
              AND status = 'em_andamento'
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute(['motorista_id' => $id]);
        $embarque = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$embarque) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Nenhuma rota em andamento encontrada'
            ], 404);
        }
        
        // Verificar se todas as entregas foram concluídas
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as total, 
                   COUNT(CASE WHEN status IN ('entregue', 'falha', 'cancelada') THEN 1 END) as finalizadas
            FROM frota_entrega
            WHERE embarque_id = :embarque_id
        ");
        $stmt->execute(['embarque_id' => $embarque['id']]);
        $stats = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if ($stats['total'] > $stats['finalizadas']) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Existem entregas pendentes. Finalize todas as entregas primeiro.',
                'pendentes' => $stats['total'] - $stats['finalizadas']
            ], 400);
        }
        
        // Finalizar embarque
        $stmt = $this->pdo->prepare("
            UPDATE frota_embarque 
            SET status = 'finalizado', 
                horario_retorno = NOW(),
                data_retorno = CURRENT_DATE,
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute(['id' => $embarque['id']]);
        
        // Atualizar veículo
        $stmt = $this->pdo->prepare("
            UPDATE frota_veiculo 
            SET status = 'disponivel',
                updated_at = NOW()
            WHERE id = :veiculo_id
        ");
        $stmt->execute(['veiculo_id' => $embarque['veiculo_id']]);
        
        // Atualizar motorista
        $stmt = $this->pdo->prepare("
            UPDATE frota_motorista 
            SET veiculo_atual_id = NULL,
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute(['id' => $id]);
        
        // Registrar log
        $this->registrarLog($embarque['id'], 'finalizar_rota', 'Rota finalizada pelo motorista');
        
        return $this->json($response, [
            'success' => true,
            'message' => 'Rota finalizada com sucesso',
            'data' => [
                'embarque_id' => $embarque['id'],
                'status' => 'finalizado',
                'horario_retorno' => date('Y-m-d H:i:s'),
                'total_entregas' => $stats['total'],
                'entregas_concluidas' => $stats['finalizadas']
            ]
        ]);
    }
    
      /**
     * POST /v1/frota/motoristas/{id}/posicao
     * Atualizar posição do motorista (GPS)
     */
    public function atualizarPosicao(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        if ($id <= 0) {
            return $this->json($response, [
                'success' => false,
                'error' => 'ID de motorista inválido'
            ], 400);
        }

        $input = json_decode($request->getBody()->getContents(), true) ?? [];
        $user = $request->getAttribute('user') ?? [];
        $permissoes = $user['permissoes'] ?? [];
        $isAdmin = (bool)($user['is_admin'] ?? false) || in_array('admin', $permissoes, true);
        $motoristaAutenticado = (int)($user['motorista_id'] ?? 0);

        if (!$isAdmin && $motoristaAutenticado !== $id) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Motorista não autorizado'
            ], 403);
        }

        $lat = (float)($input['lat'] ?? 0);
        $lng = (float)($input['lng'] ?? 0);
        $velocidade = (float)($input['velocidade'] ?? 0);
        $precisao = (float)($input['precisao'] ?? 0);

        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180 || ($lat == 0 && $lng == 0)) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Latitude e longitude são obrigatórios'
            ], 400);
        }

        try {
            // Atualizar posição do motorista
            $stmt = $this->pdo->prepare("
                UPDATE frota_motorista 
                SET latitude = :lat,
                    longitude = :lng,
                    ultima_posicao = NOW()
                WHERE id = :id
            ");
            $stmt->execute([
                'id' => $id,
                'lat' => $lat,
                'lng' => $lng
            ]);

            // Atualizar posição do veículo vinculado (se houver)
            $stmt = $this->pdo->prepare("
                UPDATE frota_veiculo 
                SET latitude = :lat,
                    longitude = :lng,
                    velocidade_atual = :velocidade,
                    ultima_posicao = NOW()
                WHERE id = (
                    SELECT veiculo_atual_id FROM frota_motorista WHERE id = :motorista_id
                )
            ");
            $stmt->execute([
                'motorista_id' => $id,
                'lat' => $lat,
                'lng' => $lng,
                'velocidade' => $velocidade
            ]);

            // Registrar histórico de posição
            $stmt = $this->pdo->prepare("
                INSERT INTO frota_historico_posicao 
                (veiculo_id, motorista_id, embarque_id, latitude, longitude, velocidade, precisao, data_hora)
                VALUES (
                    (SELECT veiculo_atual_id FROM frota_motorista WHERE id = :motorista_id),
                    :motorista_id,
                    (SELECT id FROM frota_embarque WHERE motorista_id = :motorista_id AND status = 'em_andamento' ORDER BY id DESC LIMIT 1),
                    :lat,
                    :lng,
                    :velocidade,
                    :precisao,
                    NOW()
                )
            ");
            $stmt->execute([
                'motorista_id' => $id,
                'lat' => $lat,
                'lng' => $lng,
                'velocidade' => $velocidade,
                'precisao' => $precisao
            ]);

            return $this->json($response, [
                'success' => true,
                'message' => 'Posição atualizada com sucesso',
                'data' => [
                    'motorista_id' => $id,
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'velocidade' => $velocidade,
                    'timestamp' => date('Y-m-d H:i:s')
                ]
            ]);
        } catch (\Exception $e) {
            error_log('[Motorista-posicao] Erro: ' . $e->getMessage());
            return $this->json($response, [
                'success' => false,
                'error' => 'Erro ao atualizar posição'
            ], 500);
        }
    }
    /**
     * GET /v1/frota/motoristas/{id}/estatisticas
     * Estatísticas detalhadas do motorista
     */
    public function estatisticas(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $dias = (int)($request->getQueryParams()['dias'] ?? 30);
        
        // Usar função PostgreSQL
        $stmt = $this->pdo->prepare("SELECT * FROM calcular_estatisticas_motorista(:motorista_id, :dias)");
        $stmt->execute([
            'motorista_id' => $id,
            'dias' => $dias
        ]);
        $estatisticas = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$estatisticas) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Motorista não encontrado ou sem dados'
            ], 404);
        }
        
        // Buscar entregas por dia (últimos 30 dias)
        $stmt = $this->pdo->prepare("
            SELECT 
                DATE(e.horario_entrega) as data,
                COUNT(*) as total,
                SUM(COALESCE((SELECT SUM(pi.valortotal) FROM pedido_item pi WHERE pi.idpedido IN (SELECT value::integer FROM regexp_split_to_table(COALESCE(e.pedidos_ids, ''), ',') value WHERE value ~ '^[0-9]+$')), e.valor_total, 0)) as valor,
                AVG(EXTRACT(EPOCH FROM (e.horario_entrega - e.horario_checkin))/60) as tempo_medio
            FROM frota_entrega e
            LEFT JOIN frota_embarque eb ON eb.id = e.embarque_id
            WHERE eb.motorista_id = :motorista_id
              AND e.status = 'entregue'
              AND e.horario_entrega >= NOW() - INTERVAL '30 days'
            GROUP BY DATE(e.horario_entrega)
            ORDER BY data DESC
        ");
        $stmt->execute(['motorista_id' => $id]);
        $entregasPorDia = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        return $this->json($response, [
            'success' => true,
            'data' => [
                'resumo' => $estatisticas,
                'entregas_por_dia' => $entregasPorDia,
                'periodo' => [
                    'dias' => $dias,
                    'data_inicio' => date('Y-m-d', strtotime("-{$dias} days")),
                    'data_fim' => date('Y-m-d')
                ]
            ]
        ]);
    }
    
    /**
     * POST /v1/frota/motoristas/{id}/ocorrencia
     * Registrar ocorrência do motorista
     */
    public function registrarOcorrencia(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $input = json_decode($request->getBody()->getContents(), true) ?? [];
        
        $tipo = $input['tipo'] ?? '';
        $descricao = $input['descricao'] ?? '';
        $lat = (float)($input['lat'] ?? 0);
        $lng = (float)($input['lng'] ?? 0);
        
        if (empty($tipo) || empty($descricao)) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Tipo e descrição são obrigatórios'
            ], 400);
        }
        
        $tiposValidos = ['desvio_rota', 'endereco_incorreto', 'cliente_ausente', 'transito', 'veiculo_problema', 'acidente', 'outro'];
        if (!in_array($tipo, $tiposValidos)) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Tipo inválido. Opções: ' . implode(', ', $tiposValidos)
            ], 400);
        }
        
        // Buscar embarque ativo
        $stmt = $this->pdo->prepare("
            SELECT id FROM frota_embarque 
            WHERE motorista_id = :motorista_id 
              AND status = 'em_andamento'
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute(['motorista_id' => $id]);
        $embarque = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        $sql = "
            INSERT INTO frota_ocorrencia 
            (embarque_id, motorista_id, tipo, descricao, latitude, longitude, status, created_at)
            VALUES (:embarque_id, :motorista_id, :tipo, :descricao, :lat, :lng, 'aberta', NOW())
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'embarque_id' => $embarque['id'] ?? null,
            'motorista_id' => $id,
            'tipo' => $tipo,
            'descricao' => $descricao,
            'lat' => $lat,
            'lng' => $lng
        ]);
        
        $ocorrenciaId = $this->pdo->lastInsertId();
        
        // Notificar gestor via WebSocket
        $this->enviarNotificacaoWS([
            'tipo' => 'ocorrencia',
            'ocorrencia_id' => $ocorrenciaId,
            'motorista_id' => $id,
            'tipo_ocorrencia' => $tipo,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
        return $this->json($response, [
            'success' => true,
            'message' => 'Ocorrência registrada com sucesso',
            'id' => $ocorrenciaId
        ]);
    }
    
    /**
     * POST /v1/frota/motoristas/{id}/notificacao
     * Enviar notificação para o motorista
     */
    public function enviarNotificacao(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $input = json_decode($request->getBody()->getContents(), true) ?? [];
        
        $titulo = $input['titulo'] ?? '';
        $mensagem = $input['mensagem'] ?? '';
        $tipo = $input['tipo'] ?? 'sistema';
        
        if (empty($titulo) || empty($mensagem)) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Título e mensagem são obrigatórios'
            ], 400);
        }
        
        $stmt = $this->pdo->prepare("
            INSERT INTO frota_notificacao 
            (motorista_id, tipo, titulo, mensagem, created_at)
            VALUES (:motorista_id, :tipo, :titulo, :mensagem, NOW())
        ");
        $stmt->execute([
            'motorista_id' => $id,
            'tipo' => $tipo,
            'titulo' => $titulo,
            'mensagem' => $mensagem
        ]);
        
        $notificacaoId = $this->pdo->lastInsertId();
        
        // Enviar push notification se tiver device_token
        $this->enviarPushNotification($id, $titulo, $mensagem);
        
        return $this->json($response, [
            'success' => true,
            'message' => 'Notificação enviada com sucesso',
            'id' => $notificacaoId
        ]);
    }
    
    /**
     * GET /v1/frota/motoristas/{id}/notificacoes
     * Listar notificações do motorista
     */
    public function getNotificacoes(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        if (!$this->usuarioPodeAcessarMotorista($request, $id)) {
            return $this->json($response, ['success' => false, 'error' => 'Acesso não autorizado'], 403);
        }
        $limite = (int)($request->getQueryParams()['limite'] ?? 20);
        
        $stmt = $this->pdo->prepare("
            SELECT * FROM frota_notificacao 
            WHERE motorista_id = :motorista_id
            ORDER BY created_at DESC
            LIMIT :limite
        ");
        $stmt->bindValue(':motorista_id', $id, \PDO::PARAM_INT);
        $stmt->bindValue(':limite', $limite, \PDO::PARAM_INT);
        $stmt->execute();
        
        $notificacoes = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $stmtCount = $this->pdo->prepare("
            SELECT COUNT(*) FROM frota_notificacao 
            WHERE motorista_id = :motorista_id AND lida = false
        ");
        $stmtCount->execute(['motorista_id' => $id]);
        $naoLidas = (int)$stmtCount->fetchColumn();
        
        return $this->json($response, [
            'success' => true,
            'data' => $notificacoes,
            'nao_lidas' => $naoLidas
        ]);
    }
    
    /**
     * PUT /v1/frota/motoristas/{id}/notificacoes/{notif_id}/ler
     * Marcar notificação como lida
     */
    public function marcarNotificacaoLida(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $notifId = (int)$args['notif_id'];
        
        $stmt = $this->pdo->prepare("
            UPDATE frota_notificacao 
            SET lida = true, lida_em = NOW()
            WHERE id = :id AND motorista_id = :motorista_id
        ");
        $stmt->execute(['id' => $notifId, 'motorista_id' => $id]);
        
        return $this->json($response, [
            'success' => true,
            'message' => 'Notificação marcada como lida'
        ]);
    }
    
    /**
     * POST /v1/frota/motoristas/{id}/jornada/iniciar
     * Iniciar jornada de trabalho
     */
    public function iniciarJornada(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $input = json_decode($request->getBody()->getContents(), true) ?? [];
        
        $lat = (float)($input['lat'] ?? 0);
        $lng = (float)($input['lng'] ?? 0);
        
        // Verificar se já tem jornada aberta
        $stmt = $this->pdo->prepare("
            SELECT id FROM frota_jornada 
            WHERE motorista_id = :motorista_id AND data_fim IS NULL
        ");
        $stmt->execute(['motorista_id' => $id]);
        if ($stmt->fetch()) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Já existe uma jornada em aberto'
            ], 400);
        }
        
        $stmt = $this->pdo->prepare("
            INSERT INTO frota_jornada 
            (motorista_id, data_inicio, horario_inicio, latitude_inicio, longitude_inicio, created_at)
            VALUES (:motorista_id, CURRENT_DATE, NOW(), :lat, :lng, NOW())
        ");
        $stmt->execute([
            'motorista_id' => $id,
            'lat' => $lat,
            'lng' => $lng
        ]);
        
        $jornadaId = $this->pdo->lastInsertId();
        
        return $this->json($response, [
            'success' => true,
            'message' => 'Jornada iniciada com sucesso',
            'id' => $jornadaId,
            'data_inicio' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * POST /v1/frota/motoristas/{id}/jornada/finalizar
     * Finalizar jornada de trabalho
     */
    public function finalizarJornada(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $input = json_decode($request->getBody()->getContents(), true) ?? [];
        
        $lat = (float)($input['lat'] ?? 0);
        $lng = (float)($input['lng'] ?? 0);
        $kmRodados = (float)($input['km_rodados'] ?? 0);
        $observacoes = $input['observacoes'] ?? '';
        
        // Buscar jornada aberta
        $stmt = $this->pdo->prepare("
            SELECT id FROM frota_jornada 
            WHERE motorista_id = :motorista_id AND data_fim IS NULL
        ");
        $stmt->execute(['motorista_id' => $id]);
        $jornada = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$jornada) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Nenhuma jornada em aberto encontrada'
            ], 404);
        }
        
        $stmt = $this->pdo->prepare("
            UPDATE frota_jornada 
            SET data_fim = CURRENT_DATE,
                horario_fim = NOW(),
                latitude_fim = :lat,
                longitude_fim = :lng,
                km_rodados = :km,
                observacoes = :obs,
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            'id' => $jornada['id'],
            'lat' => $lat,
            'lng' => $lng,
            'km' => $kmRodados,
            'obs' => $observacoes
        ]);
        
        return $this->json($response, [
            'success' => true,
            'message' => 'Jornada finalizada com sucesso',
            'data' => [
                'id' => $jornada['id'],
                'data_fim' => date('Y-m-d H:i:s'),
                'km_rodados' => $kmRodados
            ]
        ]);
    }
    
    /**
     * GET /v1/frota/motoristas/{id}/jornada/historico
     * Histórico de jornadas do motorista
     */
    public function historicoJornada(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $limite = (int)($request->getQueryParams()['limite'] ?? 30);
        
        $stmt = $this->pdo->prepare("
            SELECT * FROM frota_jornada 
            WHERE motorista_id = :motorista_id
            ORDER BY data_inicio DESC
            LIMIT :limite
        ");
        $stmt->bindValue(':motorista_id', $id, \PDO::PARAM_INT);
        $stmt->bindValue(':limite', $limite, \PDO::PARAM_INT);
        $stmt->execute();
        
        $jornadas = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Calcular total de horas e km
        $totalHoras = 0;
        $totalKm = 0;
        foreach ($jornadas as $j) {
            if ($j['horario_inicio'] && $j['horario_fim']) {
                $inicio = new \DateTime($j['horario_inicio']);
                $fim = new \DateTime($j['horario_fim']);
                $totalHoras += ($fim->getTimestamp() - $inicio->getTimestamp()) / 3600;
            }
            $totalKm += (float)$j['km_rodados'];
        }
        
        return $this->json($response, [
            'success' => true,
            'data' => $jornadas,
            'resumo' => [
                'total_jornadas' => count($jornadas),
                'total_horas' => round($totalHoras, 1),
                'total_km' => round($totalKm, 1),
                'media_horas_dia' => count($jornadas) > 0 ? round($totalHoras / count($jornadas), 1) : 0
            ]
        ]);
    }
    
    /**
     * GET /v1/frota/motoristas/{id}/painel
     *
     * Dashboard pessoal do motorista na aba "Meu Painel".
     *
     * Retorna:
     * - Score desempenho interno (do cache Cobli)
     * - Score Cobli (frota_cobli_score_cache)
     * - Ranking (posição do motorista, sem nomes)
     * - Resumo do mês (entregas, valor, km, tempo)
     * - Caminhão hoje (posição + odômetro + velocidade + tempo parado)
     * - Últimos 5 embarques
     *
     * 🔥 NOVO 2026-09-25 (Pacote 2)
     */
    public function painelPessoal(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];

        if (!$this->usuarioPodeAcessarMotorista($request, $id)) {
            return $this->json($response, ['success' => false, 'error' => 'Acesso não autorizado'], 403);
        }

        try {
            // 1. Resumo do Mês
            $resumo = $this->getResumoMes($id);

            // 2. Score Cobli + Ranking
            $cobli = $this->getCobliScoreMotorista($id);
            $ranking = $this->getRankingMotorista($id);

            // 3. Caminhão Hoje
            $caminhao = $this->getCaminhaoHoje($id);

            // 4. Últimos 5 Embarques
            $embarques = $this->getUltimosEmbarques($id, 5);

            return $this->json($response, [
                'success' => true,
                'data' => [
                    'resumo_mes' => $resumo,
                    'cobli' => $cobli,
                    'ranking' => $ranking,
                    'caminhao_hoje' => $caminhao,
                    'ultimos_embarques' => $embarques,
                ]
            ]);

        } catch (\Exception $e) {
            error_log('[PainelPessoal] Erro: ' . $e->getMessage());
            return $this->json($response, [
                'success' => false,
                'error' => 'Erro ao carregar o painel: ' . $e->getMessage()
            ], 500);
        }
    }

     /**
     * Resumo de entregas do mês atual.
     *
     * 🔥 CORRIGIDO 2026-09-25 (Pacote 2, patch 2):
     *   - `km_rodados` antes fazia `SUM(km_rodados)` sobre todos os
     *     snapshots do mês, inflando o valor (4× no caso do Daniel).
     *   - Agora pega APENAS a linha mais recente do cache
     *     (ORDER BY atualizado_em DESC LIMIT 1), que é o que o Cobli
     *     mostra no painel dele.
     */
    private function getResumoMes(int $motoristaId): array
    {
        $sql = "
            SELECT
                COUNT(e.id) AS total_entregas,
                COUNT(e.id) FILTER (WHERE e.status IN ('entregue', 'entregue_com_problema')) AS entregas_concluidas,
                COUNT(e.id) FILTER (WHERE e.status = 'falha') AS falhas,
                COALESCE(SUM(e.valor_total) FILTER (WHERE e.status IN ('entregue', 'entregue_com_problema')), 0) AS valor_total,
                COALESCE(AVG(EXTRACT(EPOCH FROM (e.horario_entrega - e.horario_checkin))/60) FILTER (WHERE e.status = 'entregue' AND e.horario_checkin IS NOT NULL), 0) AS tempo_medio_min
            FROM frota_entrega e
            INNER JOIN frota_embarque eb ON eb.id = e.embarque_id
            WHERE eb.motorista_id = :motorista_id
              AND e.status IN ('entregue', 'entregue_com_problema', 'falha')
              AND DATE(e.horario_entrega) >= DATE_TRUNC('month', CURRENT_DATE)
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['motorista_id' => $motoristaId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];

        // 🔥 CORRIGIDO: pega APENAS a linha mais recente do cache,
        // não soma todos os snapshots.
        $stmtKm = $this->pdo->prepare("
            SELECT s.km_rodados
            FROM frota_cobli_score_cache s
            INNER JOIN frota_cobli_motorista cm ON cm.cobli_driver_id = s.entity_id
            WHERE s.aggregation_type = 'DRIVER'
              AND cm.motorista_id = :motorista_id
              AND s.periodo_fim >= DATE_TRUNC('month', CURRENT_DATE)
            ORDER BY s.atualizado_em DESC, s.id DESC
            LIMIT 1
        ");
        $stmtKm->execute(['motorista_id' => $motoristaId]);
        $km = (float)($stmtKm->fetchColumn() ?: 0);

        return [
            'total_entregas'      => (int)($row['total_entregas'] ?? 0),
            'entregas_concluidas' => (int)($row['entregas_concluidas'] ?? 0),
            'falhas'              => (int)($row['falhas'] ?? 0),
            'valor_total'         => (float)($row['valor_total'] ?? 0),
            'tempo_medio_min'     => round((float)($row['tempo_medio_min'] ?? 0), 1),
            'km_rodados'          => round($km, 1),
        ];
    }

    /**
     * Score do Cobli + velocidade média calculada.
     * Cache em memória por 5 min.
     *
     * 🔥 CORRIGIDO 2026-09-25 (Pacote 2):
     *   - Antes `velocidade_media` vinha direto do cache e ficava 0/nulo
     *     porque a Cobli não expõe esse campo no payload do ranking.
     *   - Agora: tenta cache, senão calcula `km_rodados / (tempo_minutos / 60)`.
     */
    private function getCobliScoreMotorista(int $motoristaId): array
    {
        $cacheKey = "cobli_score_motorista_{$motoristaId}";
        $cacheFile = sys_get_temp_dir() . '/' . $cacheKey . '.json';
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 300) {
            $cached = json_decode(file_get_contents($cacheFile), true);
            if ($cached) return $cached;
        }

        $resultado = [
            'score'            => null,
            'variacao'         => null,
            'km_rodados'       => 0,
            'eventos'          => 0,
            'velocidade_media' => 0,
            'atualizado_em'    => null,
        ];

        try {
            // 🔥 Buscar o vínculo na tabela correta
            $stmt = $this->pdo->prepare("
                SELECT cobli_driver_id
                FROM frota_cobli_motorista
                WHERE motorista_id = :motorista_id
            ");
            $stmt->execute(['motorista_id' => $motoristaId]);
            $cobliDriverId = $stmt->fetchColumn();

            if ($cobliDriverId) {
                $stmt = $this->pdo->prepare("
                    SELECT score, variacao, km_rodados, tempo_minutos,
                           total_eventos, velocidade_media, atualizado_em
                    FROM frota_cobli_score_cache
                    WHERE aggregation_type = 'DRIVER'
                      AND entity_id = :entity_id
                    ORDER BY periodo_fim DESC
                    LIMIT 1
                ");
                $stmt->execute(['entity_id' => $cobliDriverId]);
                $score = $stmt->fetch(\PDO::FETCH_ASSOC);

                if ($score) {
                    $km    = (float)$score['km_rodados'];
                    $tempo = (int)$score['tempo_minutos'];
                    $vel   = $score['velocidade_media'];

                    // 🔥 Fallback: se o cache não tem velocidade, calcula
                    if ($vel === null || (float)$vel <= 0) {
                        $vel = ($km > 0 && $tempo > 0)
                            ? round($km / ($tempo / 60), 1)
                            : 0;
                    } else {
                        $vel = (float)$vel;
                    }

                    $resultado = [
                        'score'            => (float)$score['score'],
                        'variacao'         => (float)$score['variacao'],
                        'km_rodados'       => $km,
                        'eventos'          => (int)$score['total_eventos'],
                        'velocidade_media' => $vel,
                        'atualizado_em'    => $score['atualizado_em'],
                    ];
                }
            }
        } catch (\Exception $e) {
            error_log('[PainelPessoal] Erro ao buscar score Cobli: ' . $e->getMessage());
        }

        file_put_contents($cacheFile, json_encode($resultado));

        return $resultado;
    }

       /**
     * Posição do motorista no ranking Cobli (Opção B: sempre do cache).
     * Retorna apenas: posição, total e percentil.
     *
     * 🔥 CORRIGIDO 2026-09-25 (Pacote 2):
     *   - Antes a `posicao` vinha de uma query RANK() sobre o cache mas
     *     o `total` vinha de uma subquery separada. Isso gerava
     *     inconsistências (ex: "23º de 16").
     *   - Agora ambos vêm da MESMA query. Se o cache tem 16, mostra
     *     "5º de 16". Se tiver 64, mostra "23º de 64".
     *   - Anônimo: nunca retorna nome de outros motoristas.
     */
    private function getRankingMotorista(int $motoristaId): array
    {
        $resultado = [
            'posicao'          => null,
            'total_motoristas' => 0,
            'percentil'        => null,
        ];

        try {
            // Buscar o vínculo
            $stmt = $this->pdo->prepare("
                SELECT cobli_driver_id
                FROM frota_cobli_motorista
                WHERE motorista_id = :motorista_id
            ");
            $stmt->execute(['motorista_id' => $motoristaId]);
            $cobliDriverId = $stmt->fetchColumn();

            if (!$cobliDriverId) return $resultado;

            // 🔥 Posição e total vêm da MESMA query — sempre consistentes
            $stmt = $this->pdo->prepare("
                WITH ranking_atual AS (
                    SELECT
                        entity_id,
                        RANK() OVER (ORDER BY score DESC) AS posicao,
                        COUNT(*) OVER ()                   AS total
                    FROM frota_cobli_score_cache
                    WHERE aggregation_type = 'DRIVER'
                      AND periodo_fim >= DATE_TRUNC('month', CURRENT_DATE)
                )
                SELECT posicao, total
                FROM ranking_atual
                WHERE entity_id = :entity_id
                LIMIT 1
            ");
            $stmt->execute(['entity_id' => $cobliDriverId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($row && $row['posicao'] !== null) {
                $posicao = (int)$row['posicao'];
                $total   = (int)$row['total'];
                $resultado = [
                    'posicao'          => $posicao,
                    'total_motoristas' => $total,
                    'percentil'        => $total > 0
                        ? (int)round((1 - ($posicao - 1) / $total) * 100)
                        : 0,
                ];
            }
        } catch (\Exception $e) {
            error_log('[PainelPessoal] Erro ao buscar ranking: ' . $e->getMessage());
        }

        return $resultado;
    }

    /**
     * Dados do caminhão hoje (posição + odômetro + velocidade + tempo parado).
     */
    private function getCaminhaoHoje(int $motoristaId): ?array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    v.id, v.placa, v.modelo, v.marca,
                    v.odometro_atual, v.velocidade_atual,
                    v.latitude, v.longitude, v.ultima_posicao,
                    v.status
                FROM frota_motorista m
                INNER JOIN frota_veiculo v ON v.id = m.veiculo_atual_id
                WHERE m.id = :motorista_id
            ");
            $stmt->execute(['motorista_id' => $motoristaId]);
            $veiculo = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$veiculo) return null;

            // Tempo parado: minutos desde a última posição com ignição ligada
            $tempoParado = null;
            if (!empty($veiculo['ultima_posicao'])) {
                $stmtParado = $this->pdo->prepare("
                    SELECT EXTRACT(EPOCH FROM (NOW() - capturado_em)) / 60 AS minutos
                    FROM frota_cobli_posicao
                    WHERE veiculo_id = :veiculo_id
                      AND ignicao_ligada = false
                    ORDER BY capturado_em DESC
                    LIMIT 1
                ");
                $stmtParado->execute(['veiculo_id' => $veiculo['id']]);
                $tempoParado = $stmtParado->fetchColumn();
                if ($tempoParado !== false) {
                    $tempoParado = round((float)$tempoParado);
                } else {
                    $tempoParado = null;
                }
            }

            return [
                'id'              => (int)$veiculo['id'],
                'placa'           => $veiculo['placa'],
                'modelo'          => $veiculo['modelo'],
                'marca'           => $veiculo['marca'],
                'odometro_atual'  => (float)$veiculo['odometro_atual'],
                'velocidade_atual' => (float)$veiculo['velocidade_atual'],
                'latitude'        => $veiculo['latitude'] !== null ? (float)$veiculo['latitude'] : null,
                'longitude'       => $veiculo['longitude'] !== null ? (float)$veiculo['longitude'] : null,
                'ultima_posicao'  => $veiculo['ultima_posicao'],
                'tempo_parado_min' => $tempoParado,
                'status'          => $veiculo['status'],
            ];
        } catch (\Exception $e) {
            error_log('[PainelPessoal] Erro ao buscar caminhão: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Últimos N embarques do motorista.
     */
    private function getUltimosEmbarques(int $motoristaId, int $limite = 5): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                eb.id, eb.numero_embarque, eb.status, eb.data_saida, eb.data_retorno,
                eb.distancia_total_km,
                v.placa AS veiculo_placa,
                (SELECT COUNT(*) FROM frota_entrega WHERE embarque_id = eb.id) AS total_entregas,
                (SELECT COUNT(*) FROM frota_entrega WHERE embarque_id = eb.id AND status IN ('entregue', 'entregue_com_problema')) AS entregas_concluidas
            FROM frota_embarque eb
            LEFT JOIN frota_veiculo v ON v.id = eb.veiculo_id
            WHERE eb.motorista_id = :motorista_id
              AND eb.status IN ('finalizado', 'cancelado', 'problema')
            ORDER BY eb.data_saida DESC
            LIMIT :limite
        ");
        $stmt->bindValue(':motorista_id', $motoristaId, \PDO::PARAM_INT);
        $stmt->bindValue(':limite', $limite, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ========================================================================
    // MÉTODOS AUXILIARES
    // ========================================================================
    
    private function validarCPF($cpf): bool
    {
        $cpf = preg_replace('/[^0-9]/', '', $cpf);
        if (strlen($cpf) != 11) return false;
        if (preg_match('/^(\d)\1{10}$/', $cpf)) return false;
        
        for ($t = 9; $t < 11; $t++) {
            $d = 0;
            for ($c = 0; $c < $t; $c++) {
                $d += $cpf[$c] * (($t + 1) - $c);
            }
            $d = ((10 * $d) % 11) % 10;
            if ($cpf[$c] != $d) return false;
        }
        return true;
    }
    
    private function limparNumeros($valor): string
    {
        return preg_replace('/[^0-9]/', '', $valor);
    }
    
    private function registrarLog($embarqueId, $acao, $descricao)
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
                'usuario_id' => $_SESSION['user_id'] ?? 0
            ]);
        } catch (\Exception $e) {
            error_log('Erro ao registrar log: ' . $e->getMessage());
        }
    }
    
    private function enviarNotificacaoWS($dados)
    {
        // TODO: Implementar WebSocket
        error_log('WebSocket: ' . json_encode($dados));
    }
    
    private function enviarPushNotification($motoristaId, $titulo, $mensagem)
    {
        // Buscar device_token do motorista
        $stmt = $this->pdo->prepare("SELECT device_token FROM frota_motorista WHERE id = :id");
        $stmt->execute(['id' => $motoristaId]);
        $motorista = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if ($motorista && !empty($motorista['device_token'])) {
            // TODO: Implementar FCM/APNS
            error_log("Push Notification para {$motoristaId}: {$titulo} - {$mensagem}");
        }
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