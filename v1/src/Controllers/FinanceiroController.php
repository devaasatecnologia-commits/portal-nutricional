<?php
namespace Nutricional\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class FinanceiroController
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = \getPDO();
    }
/**
 * POST /v1/financeiro/dashboard
 */
public function getDashboard(Request $request, Response $response): Response
{
    $input = json_decode($request->getBody()->getContents(), true) ?? [];
    $diasRecup = intval($input['dias_recup'] ?? 120);
    $corteAtraso = ($diasRecup > 0 && $diasRecup <= 7) ? $diasRecup : 8;
    $uid = intval($input['idusuario'] ?? 0);
    $nivel = $input['nivel'] ?? 'filial';
    $fid = intval($input['filtro_id'] ?? 0);

    try {
        // Obtém permissões do usuário
        $stUser = $this->pdo->prepare("SELECT username, dash_filiais, dash_gestores FROM usuario WHERE idcliforemp = :uid");
        $stUser->execute(['uid' => $uid]);
        $userRow = $stUser->fetch();

        $nomeLogado = $userRow['username'] ?? 'USUÁRIO';
        $dashFiliais = !empty($userRow['dash_filiais']) ? explode(',', $userRow['dash_filiais']) : [];
        $dashGestores = !empty($userRow['dash_gestores']) ? explode(',', $userRow['dash_gestores']) : [];

        // Lista de filiais permitidas
        if (!empty($dashFiliais)) {
            $placeholders = implode(',', array_fill(0, count($dashFiliais), '?'));
            $sqlF = "SELECT DISTINCT idfilial, nome FROM filial WHERE inativo = 'N' AND idfilial IN ($placeholders) ORDER BY idfilial";
            $stF = $this->pdo->prepare($sqlF);
            $stF->execute($dashFiliais);
        } else {
            $sqlF = "SELECT DISTINCT f.idfilial, f.nome FROM filial f 
                     JOIN vw_analise_receber_geral_cliente v ON v.idfilial = f.idfilial 
                     WHERE v.idvendrepre = :uid ORDER BY f.idfilial";
            $stF = $this->pdo->prepare($sqlF);
            $stF->execute(['uid' => $uid]);
        }
        $listaFiliais = $stF->fetchAll();

        // 🔥 CORREÇÃO: Define a filial padrão
        // Se o $fid é 0 ou inválido, usa a primeira filial da lista
        $filialPadrao = $fid;
        if ($filialPadrao <= 0 && count($listaFiliais) > 0) {
            $filialPadrao = $listaFiliais[0]['idfilial'];
        }
        // Se ainda assim for inválido, usa 1 como fallback
        if ($filialPadrao <= 0) {
            $filialPadrao = 1;
        }

        // Se não tem filial definida na requisição, usa a padrão
        if ($fid === 0 && count($listaFiliais) > 0) {
            $fid = $filialPadrao;
        }

        // Travas de segurança
        $travaHierarquia = "";
        $travaEventos = "";
        $params = [];

        if (!empty($dashFiliais) || !empty($dashGestores)) {
            $condsVarg = [];
            $condsVfe = [];
            if (!empty($dashFiliais)) {
                $placeholdersF = implode(',', array_fill(0, count($dashFiliais), '?'));
                $condsVarg[] = "varg.idfilial IN ($placeholdersF)";
                $condsVfe[] = "vfe.idfilial IN ($placeholdersF)";
                foreach ($dashFiliais as $f) {
                    $params[] = (int)$f;
                }
            }
            if (!empty($dashGestores)) {
                $placeholdersG = implode(',', array_fill(0, count($dashGestores), '?'));
                $condsVarg[] = "varg.idsupervisor IN ($placeholdersG)";
                $condsVfe[] = "vfe.idgestor IN ($placeholdersG)";
                foreach ($dashGestores as $g) {
                    $params[] = (int)$g;
                }
            }
            $travaHierarquia = implode(' AND ', $condsVarg);
            $travaEventos = implode(' AND ', $condsVfe);
        } else {
            $travaHierarquia = "varg.idvendrepre = ?";
            $travaEventos = "vfe.idrepresentante = ?";
            $params[] = $uid;
        }

        // ============================================================
        // PARÂMETROS DA QUERY PRINCIPAL
        // ============================================================
        $joinExtra = "";
        $whereDrill = "";
        $whereCards = "";
        $select = "";
        $groupBy = "";
        $paramsQuery = $params;

        // 🔥 NOVA LÓGICA - CADA NÍVEL RETORNA O PRÓXIMO NÍVEL
        switch ($nivel) {
            // ============================================================
            // NÍVEL FILIAL: Retorna os GESTORES da filial
            // ============================================================
            case 'filial':
                $select = "varg.idsupervisor as id, varg.nomegestor as nome";
                $groupBy = "varg.idsupervisor, varg.nomegestor";
                $whereDrill = "varg.idfilial = ? AND varg.idsupervisor IS NOT NULL";
                $whereCards = "varg.idfilial = ? AND varg.idsupervisor IS NOT NULL";
                $paramsQuery[] = $fid;
                break;

            // ============================================================
            // NÍVEL GESTOR: Retorna os REPRESENTANTES do gestor
            // ============================================================
            case 'gestor':
                $select = "varg.idvendrepre as id, varg.nomerepresentante as nome";
                $groupBy = "varg.idvendrepre, varg.nomerepresentante";
                $whereDrill = "varg.idsupervisor = ? AND varg.idvendrepre IS NOT NULL";
                $whereCards = "varg.idsupervisor = ? AND varg.idvendrepre IS NOT NULL";
                $paramsQuery[] = $fid;
                break;

            // ============================================================
            // NÍVEL REPRESENTANTE: Retorna os CLIENTES do representante
            // ============================================================
            case 'representante':
                $select = "varg.idcliforemp as id, varg.cliente as nome";
                $groupBy = "varg.idcliforemp, varg.cliente";
                $whereDrill = "varg.idvendrepre = ? AND varg.vencidos > 0";
                $whereCards = "varg.idvendrepre = ?";
                $paramsQuery[] = $fid;
                break;

            // ============================================================
            // NÍVEL CLIENTE: Retorna os TÍTULOS do cliente (para a modal)
            // ============================================================
            case 'cliente':
                $sql = "SELECT 
                            documento,
                            valorsaldo,
                            to_char(vencimento, 'DD/MM/YYYY') as vencimento_formatado,
                            dias_atraso,
                            evento,
                            descricao,
                            to_char(ultimo_evento, 'DD/MM/YYYY') as ultimo_evento_formatado
                        FROM vw_financeiro_eventos_geral 
                        WHERE idcliforemp = ? 
                        AND valorsaldo > 0.01
                        ORDER BY vencimento ASC, dias_atraso DESC";
                
                $stmtTitulos = $this->pdo->prepare($sql);
                $stmtTitulos->execute([$fid]);
                $titulos = $stmtTitulos->fetchAll(\PDO::FETCH_ASSOC);
                
                $response->getBody()->write(json_encode([
                    'config' => ['usuario' => $nomeLogado, 'filiais' => $listaFiliais, 'filial_padrao' => $filialPadrao],
                    'resumo_filial' => null,
                    'tabela' => $titulos,
                    'taxa_recup' => 0,
                    'recup_detalhe' => null
                ]));
                return $response->withHeader('Content-Type', 'application/json');
                break;

            default:
                $select = "f.idfilial as id, f.nome as nome";
                $joinExtra = "JOIN filial f ON f.idfilial = varg.idfilial";
                $groupBy = "f.idfilial, f.nome";
                $whereDrill = "varg.idfilial = ?";
                $whereCards = "varg.idfilial = ?";
                $paramsQuery[] = $fid;
                break;
        }

        // ============================================================
        // TAXA DE RECUPERAÇÃO
        // ============================================================
        $campoFiltroRec = "";
        $valorFiltroRec = $fid;

        if ($nivel === 'filial') {
            $campoFiltroRec = "vfe.idfilial";
            $valorFiltroRec = $fid;
        } elseif ($nivel === 'gestor') {
            $campoFiltroRec = "vfe.idgestor";
            $valorFiltroRec = $fid;
        } elseif ($nivel === 'representante') {
            $campoFiltroRec = "vfe.idrepresentante";
            $valorFiltroRec = $fid;
        } else {
            $campoFiltroRec = "vfe.idfilial";
            $valorFiltroRec = 1;
        }

        $sqlRec = "SELECT 
            SUM(CASE WHEN vfe.ultimo_evento IS NULL AND vfe.dias_atraso >= :corte AND vfe.valorsaldo > 0.01 THEN 1 ELSE 0 END) as cenario_1,
            SUM(CASE WHEN vfe.ultimo_evento IS NOT NULL AND vfe.valorsaldo > 0.01 THEN 1 ELSE 0 END) as cenario_2,
            SUM(CASE WHEN vfe.ultimo_evento IS NOT NULL AND vfe.valorsaldo <= 0.01 THEN 1 ELSE 0 END) as cenario_3
        FROM vw_financeiro_eventos_geral vfe 
        WHERE {$campoFiltroRec} = :filtro_rec
        AND vfe.vencimento >= (CURRENT_DATE - INTERVAL '{$diasRecup} days')";

        $stmtRec = $this->pdo->prepare($sqlRec);
        $stmtRec->execute([
            'corte' => $corteAtraso,
            'filtro_rec' => $valorFiltroRec
        ]);
        $rowRec = $stmtRec->fetch();

        $c1 = (int)($rowRec['cenario_1'] ?? 0);
        $c2 = (int)($rowRec['cenario_2'] ?? 0);
        $c3 = (int)($rowRec['cenario_3'] ?? 0);

        $baseTotal = $c1 + $c2 + $c3;
        $taxaContexto = $baseTotal > 0 ? round(($c3 * 100) / $baseTotal, 2) : 0;

        // ============================================================
        // CONSULTA PRINCIPAL (TABELA)
        // ============================================================
        $sql = "SELECT $select, 
                       SUM(COALESCE(varg.vencidos,0))::float as vencidos, 
                       SUM(COALESCE(varg.total_receber,0))::float as total_receber,
                       SUM(COALESCE(varg.dias_60 + varg.mais_60_dias,0))::float as valor_iap
                FROM vw_analise_receber_geral_cliente varg
                $joinExtra
                WHERE ($travaHierarquia) AND ($whereDrill)
                GROUP BY $groupBy ORDER BY vencidos DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($paramsQuery);
        $tabela = $stmt->fetchAll();

        foreach ($tabela as &$r) {
            $r['iag'] = $r['total_receber'] > 0 ? round(($r['vencidos'] * 100) / $r['total_receber'], 2) : 0;
            if ($r['iag'] > 10) $r['performance'] = 'CRÍTICO';
            elseif ($r['iag'] >= 5) $r['performance'] = 'ATENÇÃO';
            else $r['performance'] = 'SAUDÁVEL';
        }

        // ============================================================
        // RESUMO DA CARTEIRA (CARDS)
        // ============================================================
        $sqlSum = "SELECT SUM(COALESCE(varg.total_receber,0))::float as total, 
                          SUM(COALESCE(varg.vencidos,0))::float as vencidos,
                          SUM(COALESCE(varg.dias_60 + varg.mais_60_dias,0))::float as valor_iap,
                          SUM(COALESCE(varg.dias_30,0))::float as d30, 
                          SUM(COALESCE(varg.dias_60,0))::float as d60, 
                          SUM(COALESCE(varg.mais_60_dias,0))::float as d90 
                   FROM vw_analise_receber_geral_cliente varg
                   WHERE ($travaHierarquia) AND ($whereCards)";

        $stSum = $this->pdo->prepare($sqlSum);
        $stSum->execute($paramsQuery);
        $resumo = $stSum->fetch();

        // 🔥 CORREÇÃO: Monta a resposta com a filial padrão correta
        $payload = json_encode([
            "config" => [
                "usuario" => $nomeLogado, 
                "filiais" => $listaFiliais, 
                "filial_padrao" => $filialPadrao  // ← CORRIGIDO
            ],
            "resumo_filial" => $resumo,
            "tabela" => $tabela,
            "taxa_recup" => $taxaContexto,
            "recup_detalhe" => ["pagos" => $c3, "total" => $baseTotal]
        ]);

        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json');

    } catch (\Exception $e) {
        error_log('Erro em getDashboard: ' . $e->getMessage());
        $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
        return $response->withStatus(500);
    }
}

 /**
     * POST /v1/financeiro/usuario-info
     * Retorna informações detalhadas do usuário
     */
    public function getUsuarioInfo(Request $request, Response $response): Response
    {
        return $this->getUsuarioPermissoes($request, $response);
    }

   /**
 * POST /v1/financeiro/usuario-permissoes
 * Retorna as permissões de um usuário específico
 */
public function getUsuarioPermissoes(Request $request, Response $response): Response
{
    $input = json_decode($request->getBody()->getContents(), true) ?? [];
    $uid = intval($input['idusuario'] ?? 0);

    try {
        $stmt = $this->pdo->prepare("
            SELECT 
                idcliforemp,
                idusuario,
                username,
                dash_filiais,
                dash_gestores,
                permite_ver_usuarios
            FROM usuario 
            WHERE idcliforemp = :uid
        ");
        $stmt->execute(['uid' => $uid]);
        $usuario = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$usuario) {
            throw new \Exception("Usuário não encontrado");
        }

        // 🔥 CORREÇÃO: Garante que dash_filiais e dash_gestores sejam strings
        $usuario['dash_filiais'] = $usuario['dash_filiais'] ?? '';
        $usuario['dash_gestores'] = $usuario['dash_gestores'] ?? '';

        // Busca os gestores que este usuário pode ver (dash_gestores)
        $gestores = [];
        if (!empty($usuario['dash_gestores'])) {
            $ids = explode(',', $usuario['dash_gestores']);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            
            $stmtG = $this->pdo->prepare("
                SELECT idcliforemp, username as nome
                FROM usuario
                WHERE idcliforemp IN ($placeholders)
                AND inativo = 'N'
                ORDER BY username
            ");
            $stmtG->execute($ids);
            $gestores = $stmtG->fetchAll(\PDO::FETCH_ASSOC);
        }

        // 🔥 CORREÇÃO: Busca as filiais disponíveis para este usuário
        $filiaisDisponiveis = [];
        if (!empty($usuario['dash_filiais'])) {
            $ids = explode(',', $usuario['dash_filiais']);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            
            $stmtF = $this->pdo->prepare("
                SELECT idfilial, nome
                FROM filial
                WHERE idfilial IN ($placeholders)
                AND inativo = 'N'
                ORDER BY idfilial
            ");
            $stmtF->execute($ids);
            $filiaisDisponiveis = $stmtF->fetchAll(\PDO::FETCH_ASSOC);
        }

        $response->getBody()->write(json_encode([
            'usuario' => $usuario,
            'gestores' => $gestores,
            'filiais_disponiveis' => $filiaisDisponiveis
        ]));
        return $response->withHeader('Content-Type', 'application/json');

    } catch (\Exception $e) {
        error_log('Erro em getUsuarioPermissoes: ' . $e->getMessage());
        error_log('Stack trace: ' . $e->getTraceAsString());
        $response->getBody()->write(json_encode([
            'error' => $e->getMessage()
        ]));
        return $response->withStatus(500);
    }
}
    /**
     * POST /v1/financeiro/gestores-por-filial
     * Retorna os gestores de uma filial específica para um usuário
     */
    public function getGestoresPorFilial(Request $request, Response $response): Response
    {
        $input = json_decode($request->getBody()->getContents(), true) ?? [];
        $uid = intval($input['idusuario'] ?? 0);
        $idfilial = intval($input['idfilial'] ?? 0);

        try {
            // 🔥 BUSCA TODOS OS GESTORES DA FILIAL
            $sql = "
                SELECT DISTINCT 
                    v.idsupervisor as id,
                    v.nomegestor as nome
                FROM vw_analise_receber_geral_cliente v
                WHERE v.idfilial = :idfilial
                  AND v.idsupervisor IS NOT NULL
                  AND v.idsupervisor > 0
                ORDER BY v.nomegestor
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['idfilial' => $idfilial]);
            $gestores = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $response->getBody()->write(json_encode([
                'gestores' => $gestores,
                'total' => count($gestores)
            ]));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            error_log('Erro em getGestoresPorFilial: ' . $e->getMessage());
            $response->getBody()->write(json_encode([
                'error' => $e->getMessage(),
                'gestores' => []
            ]));
            return $response->withStatus(500);
        }
    }

    /**
     * POST /v1/financeiro/historico-kpi
     */
    public function getHistoricoKpi(Request $request, Response $response): Response
    {
        $input = json_decode($request->getBody()->getContents(), true) ?? [];
        $uid = intval($input['idusuario'] ?? 0);
        $tipo = $input['tipo'] ?? 'iag_calculado';
        $fid = intval($input['filtro_id'] ?? 0);
        $dia = intval($input['dia_semana'] ?? date('w'));

        $colunasPermitidas = ['iag_calculado', 'iap_calculado', 'taxa_recuperacao'];
        if (!in_array($tipo, $colunasPermitidas)) $tipo = 'iag_calculado';

        $isMaster = in_array((string)$uid, ['11258', '15750', '14073', '5166', '5297']);
        
        if ($fid > 100) {
            $where = "id_referencia = $fid AND idusuario = $fid";
        } else {
            if ($isMaster) {
                $where = "id_referencia = $fid AND idusuario = $uid";
            } else {
                $where = "id_referencia = $uid AND idusuario = $uid";
            }
        }

        try {
            $sql = "SELECT 
                        to_char(data_registro, 'DD/MM') as data,
                        $tipo::float as valor,
                        COALESCE(vencidos, 0)::float as abs_iag,
                        COALESCE(valor_iap, 0)::float as abs_iap,
                        COALESCE(qtd_trabalhados, 0)::int as abs_recup_total,
                        COALESCE(qtd_recuperados, 0)::int as abs_recup_pagos
                    FROM kpi_financeiro_historico
                    WHERE $where 
                    AND EXTRACT(DOW FROM data_registro) = $dia
                    ORDER BY data_registro DESC 
                    LIMIT 5";
            
            $stmt = $this->pdo->query($sql);
            $resultados = $stmt->fetchAll();
            
            $payload = json_encode(array_reverse($resultados));
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([]));
            return $response->withStatus(500);
        }
    }

    /**
     * POST /v1/financeiro/lista-usuarios
     * Lista usuários que o usuário logado pode visualizar
     */
    public function getListaUsuariosHistorico(Request $request, Response $response): Response
    {
        $input = json_decode($request->getBody()->getContents(), true) ?? [];
        $uid = intval($input['idusuario'] ?? 0);
        $fid = intval($input['idfilial'] ?? 0);

        try {
            $stUser = $this->pdo->prepare("
                SELECT 
                    idcliforemp, 
                    idusuario,
                    username, 
                    dash_filiais, 
                    dash_gestores,
                    permite_ver_usuarios,
                    inativo 
                FROM usuario 
                WHERE idcliforemp = :uid
            ");
            $stUser->execute(['uid' => $uid]);
            $userRow = $stUser->fetch();

            if (!$userRow) {
                throw new \Exception("Usuário não encontrado");
            }

            $dashFiliais = !empty($userRow['dash_filiais']) ? array_map('intval', explode(',', $userRow['dash_filiais'])) : [];
            $dashGestores = !empty($userRow['dash_gestores']) ? array_map('intval', explode(',', $userRow['dash_gestores'])) : [];
            $permiteVerTodos = $userRow['permite_ver_usuarios'] === 'S';
            $idusuario = (int)$userRow['idusuario'];

            $usuariosVisualizar = [];
            if (!$permiteVerTodos) {
                $stmtVis = $this->pdo->prepare("
                    SELECT idusuario_visualizar 
                    FROM usuario_visualizacao 
                    WHERE idusuario = :idusuario
                ");
                $stmtVis->execute(['idusuario' => $idusuario]);
                $usuariosVisualizar = $stmtVis->fetchAll(\PDO::FETCH_COLUMN);
            }

            $usuarios = [];

            // 🔥 REGRA 1: PODE VER TODOS
            if ($permiteVerTodos) {
                $sql = "
                    SELECT DISTINCT 
                        u.idcliforemp as id, 
                        u.username as nome
                    FROM usuario u
                    WHERE u.idcliforemp IS NOT NULL 
                      AND u.idcliforemp > 0
                      AND u.inativo = 'N'
                    ORDER BY u.username
                ";
                $stmt = $this->pdo->query($sql);
                $usuarios = $stmt->fetchAll();
            } 
            // 🔥 REGRA 2: PODE VER USUÁRIOS ESPECÍFICOS
            elseif (!empty($usuariosVisualizar)) {
                $placeholders = implode(',', array_fill(0, count($usuariosVisualizar), '?'));
                
                $sql = "
                    SELECT DISTINCT 
                        u.idcliforemp as id, 
                        u.username as nome
                    FROM usuario u
                    WHERE u.idusuario IN ($placeholders)
                      AND u.inativo = 'N'
                    ORDER BY u.username
                ";
                
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($usuariosVisualizar);
                $usuarios = $stmt->fetchAll();
            } 
            // 🔥 REGRA 3: ACESSO POR FILIAIS
            elseif (!empty($dashFiliais)) {
                $placeholdersF = implode(',', array_fill(0, count($dashFiliais), '?'));
                
                $sql = "
                    SELECT DISTINCT 
                        u.idcliforemp as id, 
                        u.username as nome
                    FROM usuario u
                    WHERE u.idcliforemp IS NOT NULL 
                      AND u.idcliforemp > 0
                      AND u.inativo = 'N'
                      AND (
                          u.idcliforemp = :uid
                          OR EXISTS (
                              SELECT 1 FROM vw_analise_receber_geral_cliente v 
                              WHERE v.idvendrepre = u.idcliforemp 
                              AND v.idfilial IN ($placeholdersF)
                          )
                      )
                    ORDER BY 
                        CASE WHEN u.idcliforemp = :uid THEN 0 ELSE 1 END,
                        u.username
                ";
                
                $params = array_merge($dashFiliais, ['uid' => $uid]);
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($params);
                $usuarios = $stmt->fetchAll();
            } 
            // 🔥 REGRA 4: ACESSO POR GESTORES
            elseif (!empty($dashGestores)) {
                $placeholdersG = implode(',', array_fill(0, count($dashGestores), '?'));
                
                $sql = "
                    SELECT DISTINCT 
                        u.idcliforemp as id, 
                        u.username as nome
                    FROM usuario u
                    WHERE u.idcliforemp IS NOT NULL 
                      AND u.idcliforemp > 0
                      AND u.inativo = 'N'
                      AND u.idcliforemp IN (
                          SELECT DISTINCT v.idvendrepre 
                          FROM vw_analise_receber_geral_cliente v
                          WHERE v.idsupervisor IN ($placeholdersG)
                      )
                    ORDER BY u.username
                ";
                
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($dashGestores);
                $usuarios = $stmt->fetchAll();
            } 
            // 🔥 REGRA 5: USUÁRIO COMUM
            else {
                $sql = "
                    SELECT 
                        idcliforemp as id, 
                        username as nome
                    FROM usuario 
                    WHERE idcliforemp = :uid
                      AND inativo = 'N'
                ";
                
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute(['uid' => $uid]);
                $usuarios = $stmt->fetchAll();
            }

            // 🔧 SEMPRE GARANTE O PRÓPRIO USUÁRIO
            $idsUsuarios = array_column($usuarios, 'id');
            if (!in_array($uid, $idsUsuarios)) {
                $sqlOwn = "SELECT idcliforemp as id, username as nome FROM usuario WHERE idcliforemp = :uid AND inativo = 'N'";
                $stmt = $this->pdo->prepare($sqlOwn);
                $stmt->execute(['uid' => $uid]);
                $ownUser = $stmt->fetch();
                if ($ownUser) {
                    $usuarios[] = $ownUser;
                }
            }

            // 🔧 ORDENA
            usort($usuarios, function($a, $b) use ($uid) {
                if ($a['id'] == $uid) return -1;
                if ($b['id'] == $uid) return 1;
                return strcmp($a['nome'], $b['nome']);
            });

            $response->getBody()->write(json_encode($usuarios));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            error_log('Erro em getListaUsuariosHistorico: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            
            $response->getBody()->write(json_encode([
                'erro' => $e->getMessage()
            ]));
            return $response->withStatus(500);
        }
    }

    /**
     * POST /v1/financeiro/detalhes-kpi
     */
    public function getDetalhesAnaliseKpi(Request $request, Response $response): Response
    {
        $input = json_decode($request->getBody()->getContents(), true) ?? [];
        $tipo = $input['tipo'] ?? '';
        $nivel = $input['nivel'] ?? 'filial';
        $fid = intval($input['filtro_id'] ?? 0);
        $uid = intval($input['idusuario'] ?? 0);
        $cenario = intval($input['cenario'] ?? 0);
        $diasRecup = intval($input['dias_recup'] ?? 120);

        try {
            $stUser = $this->pdo->prepare("SELECT dash_filiais, dash_gestores FROM usuario WHERE idcliforemp = :uid");
            $stUser->execute(['uid' => $uid]);
            $userRow = $stUser->fetch();

            $dashFiliais = !empty($userRow['dash_filiais']) ? explode(',', $userRow['dash_filiais']) : [];
            $dashGestores = !empty($userRow['dash_gestores']) ? explode(',', $userRow['dash_gestores']) : [];

            $travaHierarquia = "";
            $travaEventos = "";

            if (!empty($dashFiliais) || !empty($dashGestores)) {
                $condsVarg = [];
                $condsVfe = [];
                if (!empty($dashFiliais)) {
                    $inF = implode(',', array_map('intval', $dashFiliais));
                    $condsVarg[] = "varg.idfilial IN ($inF)";
                    $condsVfe[] = "vfe.idfilial IN ($inF)";
                }
                if (!empty($dashGestores)) {
                    $inG = implode(',', array_map('intval', $dashGestores));
                    $condsVarg[] = "varg.idsupervisor IN ($inG)";
                    $condsVfe[] = "vfe.idgestor IN ($inG)";
                }
                $travaHierarquia = implode(' AND ', $condsVarg);
                $travaEventos = implode(' AND ', $condsVfe);
            } else {
                $travaHierarquia = "varg.idvendrepre = $uid";
                $travaEventos = "vfe.idrepresentante = $uid";
            }

            $whereFiltro = "";
            $campoFiltroTaxa = "";

            switch ($nivel) {
                case 'filial':
                case 'gestor':
                    $whereFiltro = ($fid <= 1) ? "1=1" : "varg.idfilial = $fid";
                    $campoFiltroTaxa = ($fid <= 1) ? "1=1" : "vfe.idfilial = $fid";
                    break;
                case 'representante':
                    $whereFiltro = "varg.idsupervisor = $fid";
                    $campoFiltroTaxa = "vfe.idgestor = $fid";
                    break;
                case 'cliente':
                    $whereFiltro = "varg.idvendrepre = $fid";
                    $campoFiltroTaxa = "vfe.idrepresentante = $fid";
                    break;
            }

            // Títulos do Cliente
            if ($tipo === 'titulos_cliente') {
                $sql = "SELECT 
                            documento, 
                            valorsaldo::float as valor,
                            to_char(vencimento, 'DD/MM/YYYY') as data_vencimento,
                            dias_atraso,
                            evento as ultimo_evento,
                            to_char(ultimo_evento, 'DD/MM/YYYY') as data_evento,
                            usuariocriador as responsavel,
                            descricao as evento_registrado
                        FROM vw_financeiro_eventos_geral vfe
                        WHERE idcliforemp = $fid 
                        AND valorsaldo > 0.01
                        AND ($travaEventos) 
                        ORDER BY vfe.vencimento ASC, vfe.ultimo_evento DESC NULLS LAST
                        LIMIT 20";
                
                $stmt = $this->pdo->query($sql);
                $resultados = $stmt->fetchAll();
                $payload = json_encode($resultados);
                $response->getBody()->write($payload);
                return $response->withHeader('Content-Type', 'application/json');
            }

            // IAG ou IAP
            if ($tipo === 'iag' || $tipo === 'iap') {
                $campo = ($tipo === 'iag') 
                         ? "COALESCE(varg.vencidos, 0)" 
                         : "(COALESCE(varg.dias_60,0) + COALESCE(varg.mais_60_dias,0))";

                $sql = "SELECT varg.cliente as label, SUM($campo)::float as valor 
                        FROM vw_analise_receber_geral_cliente varg 
                        WHERE ($travaHierarquia) 
                        AND $whereFiltro 
                        AND $campo > 0
                        GROUP BY varg.cliente ORDER BY valor DESC LIMIT 10";
                
                $stmt = $this->pdo->query($sql);
                $resultados = $stmt->fetchAll();
                $resultadosFormatados = array_map(function($item) {
                    return [
                        'label' => $item['label'],
                        'valor' => floatval($item['valor']),
                        'is_qtd' => false
                    ];
                }, $resultados);
                $payload = json_encode($resultadosFormatados ?: []);
                $response->getBody()->write($payload);
                return $response->withHeader('Content-Type', 'application/json');
            }

            // Recuperação
            if ($tipo === 'recup') {
                $corteAtraso = ($diasRecup > 0 && $diasRecup <= 7) ? $diasRecup : 8;

                if ($cenario > 0) {
                    $whereCenario = "";
                    $campoValor = "vfe.valorsaldo";

                    if ($cenario === 1) {
                        $whereCenario = "vfe.ultimo_evento IS NULL AND vfe.dias_atraso >= $corteAtraso AND vfe.valorsaldo > 0";
                    } elseif ($cenario === 2) {
                        $whereCenario = "vfe.ultimo_evento IS NOT NULL AND vfe.valorsaldo > 0";
                    } elseif ($cenario === 3) {
                        $whereCenario = "vfe.ultimo_evento IS NOT NULL AND vfe.valorsaldo <= 0.01";
                        $campoValor = "vfe.totalrecebido";
                    }

                    $sql = "SELECT 
                                vfe.nomefantasia as cliente, 
                                vfe.documento, 
                                $campoValor as valorsaldo,
                                to_char(vfe.vencimento, 'DD/MM/YYYY') as data_vencimento,
                                to_char(vfe.ultimo_evento, 'DD/MM/YYYY') as data_evento, 
                                vfe.evento as desc_evento
                            FROM vw_financeiro_eventos_geral vfe 
                            WHERE ($travaEventos) AND $campoFiltroTaxa 
                            AND vfe.vencimento >= (CURRENT_DATE - INTERVAL '$diasRecup days')
                            AND ($whereCenario)
                            ORDER BY $campoValor DESC LIMIT 150";
                    
                    $stmt = $this->pdo->query($sql);
                    $resultados = $stmt->fetchAll();
                    $payload = json_encode($resultados);
                    $response->getBody()->write($payload);
                    return $response->withHeader('Content-Type', 'application/json');
                }

                // Resumo dos 3 cenários
                $sql = "SELECT 
                    SUM(CASE WHEN vfe.ultimo_evento IS NULL AND vfe.dias_atraso >= $corteAtraso AND vfe.valorsaldo > 0 THEN 1 ELSE 0 END) as c1,
                    SUM(CASE WHEN vfe.ultimo_evento IS NOT NULL AND vfe.valorsaldo > 0 THEN 1 ELSE 0 END) as c2,
                    SUM(CASE WHEN vfe.ultimo_evento IS NOT NULL AND vfe.valorsaldo <= 0.01 THEN 1 ELSE 0 END) as c3
                FROM vw_financeiro_eventos_geral vfe 
                WHERE ($travaEventos) 
                AND $campoFiltroTaxa 
                AND vfe.vencimento >= (CURRENT_DATE - INTERVAL '$diasRecup days')";
                
                $stmt = $this->pdo->query($sql);
                $row = $stmt->fetch();

                $labelDias = ($corteAtraso == 8) ? "+7" : ">=" . $corteAtraso;

                $resultados = [
                    ["label" => "1) Clientes $labelDias dias de atraso sem evento", "valor" => (int)$row['c1'], "is_qtd" => true, "cenario_id" => 1],
                    ["label" => "2) Com apontamento, não pago", "valor" => (int)$row['c2'], "is_qtd" => true, "cenario_id" => 2],
                    ["label" => "3) Com apontamento, pago (Recuperados)", "valor" => (int)$row['c3'], "is_qtd" => true, "cenario_id" => 3]
                ];
                
                $payload = json_encode($resultados);
                $response->getBody()->write($payload);
                return $response->withHeader('Content-Type', 'application/json');
            }

            throw new \Exception("Tipo de auditoria inválido.");

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['erro' => $e->getMessage()]));
            return $response->withStatus(500);
        }
    }

    /**
     * POST /v1/financeiro/relatorio-detalhado
     * Retorna dados para geração de PDF
     */
    public function getRelatorioDetalhado(Request $request, Response $response): Response
    {
        $input = json_decode($request->getBody()->getContents(), true) ?? [];
        
        $uid = intval($input['idusuario'] ?? 0);
        $idfilial = intval($input['idfilial'] ?? 0);
        $nivel = $input['nivel'] ?? 'filial';
        $filtroId = intval($input['filtro_id'] ?? 0);
        
        try {
            $whereConditions = [];
            $params = [];
            
            if ($idfilial > 0) {
                $whereConditions[] = "vfe.idfilial = ?";
                $params[] = $idfilial;
            }
            $whereConditions[] = "vfe.valorsaldo > 0.01";
            
            if ($nivel === 'gestor' && $filtroId > 0 && $filtroId != 1) {
                $whereConditions[] = "vfe.idgestor = ?";
                $params[] = $filtroId;
            }
            elseif ($nivel === 'cliente' && $filtroId > 0 && $filtroId != 1) {
                $whereConditions[] = "vfe.idcliforemp = ?";
                $params[] = $filtroId;
            }
            elseif ($nivel === 'representante' && $filtroId > 0 && $filtroId != 1) {
                $whereConditions[] = "vfe.idrepresentante = ?";
                $params[] = $filtroId;
            }
            
            $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";
            
            $sql = "
                SELECT DISTINCT
                    vfe.idfilial, 
                    COALESCE(vfe.idtipoevento, 0) AS idtipoevento,
                    vfe.dataemissao,
                    vfe.metodopagto,
                    vfe.idcliforemp,
                    vfe.nomefantasia, 
                    vfe.documento, 
                    vfe.vencimento, 
                    vfe.valor, 
                    vfe.totalrecebido,
                    vfe.valorsaldo,
                    vfe.dias_atraso,
                    vfe.nome_vendedor,
                    TO_CHAR(vfe.vencimento, 'DD/MM/YYYY') as vencimento_formatado,
                    TO_CHAR(vfe.dataemissao, 'DD/MM/YYYY') as dataemissao_formatado,
                    COALESCE(CAST(vfe.ult_evento_dias AS TEXT), 'Sem Registro') AS ult_evento_dias,
                    COALESCE(TO_CHAR(vfe.ultimo_evento, 'DD/MM/YYYY'), 'Sem Data') AS ultimo_evento,
                    COALESCE(vfe.descricao, 'Sem Registro') AS descricao,
                    COALESCE(vfe.usuario, 'Sem Registro') AS usuario,
                    COALESCE(vfe.usuariocriador, 'Sem Registro') AS usuariocriador,
                    COALESCE(vfe.evento, 'Sem Registro') AS evento,
                    cfe.fantasia AS gestor,
                    SUM(vfe.valor) OVER() AS total_geral_valor,
                    SUM(vfe.totalrecebido) OVER() AS total_geral_recebido,
                    SUM(vfe.valorsaldo) OVER() AS total_geral_saldo
                FROM vw_financeiro_eventos vfe
                LEFT JOIN cliforemp cfe ON cfe.idcliforemp = vfe.idgestor
                {$whereClause}
                GROUP BY
                    vfe.idfilial, 
                    vfe.idtipoevento,
                    vfe.dataemissao,
                    vfe.idcliforemp,
                    vfe.nomefantasia, 
                    vfe.metodopagto,
                    vfe.documento, 
                    vfe.vencimento, 
                    vfe.valor, 
                    vfe.totalrecebido,
                    vfe.valorsaldo,
                    vfe.dias_atraso,
                    vfe.nome_vendedor,
                    vfe.ult_evento_dias,
                    vfe.ultimo_evento,
                    vfe.descricao,
                    vfe.usuario,
                    vfe.usuariocriador,
                    vfe.evento,
                    cfe.fantasia
                ORDER BY
                    gestor,
                    nome_vendedor, 
                    idcliforemp,
                    ult_evento_dias, 
                    dataemissao, 
                    vencimento, 
                    evento ASC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $dados = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            $response->getBody()->write(json_encode($dados, JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\PDOException $e) {
            error_log("Erro PDO: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            error_log("Erro: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
}