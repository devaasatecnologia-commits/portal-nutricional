<?php

namespace Nutricional\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PDO;
use Exception;

class AnaliseCarteiraController
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = \getPDO();
    }

    /**
     * GET /v1/analise-carteira/admin/gestores
     */
    public function getTodosGestores(Request $request, Response $response): Response
    {
        try {
            $sql = "SELECT DISTINCT 
                        varg.idsupervisor as id, 
                        varg.nomegestor as nome
                    FROM vw_analise_receber_geral varg 
                    WHERE varg.idsupervisor IS NOT NULL
                    ORDER BY varg.nomegestor";
            
            $stmt = $this->pdo->query($sql);
            $gestores = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->json($response, $gestores);
        } catch (Exception $e) {
            error_log('Erro getTodosGestores: ' . $e->getMessage());
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /v1/analise-carteira/gestores
     */
    public function getGestores(Request $request, Response $response): Response
    {
        $input = json_decode($request->getBody()->getContents(), true) ?? [];
        $uid = (int)($input['idusuario'] ?? 0);

        try {
            $stUser = $this->pdo->prepare(
                "SELECT dash_gestores FROM usuario WHERE idcliforemp = :uid"
            );
            $stUser->execute(['uid' => $uid]);
            $userRow = $stUser->fetch();

            $dashGestores = !empty($userRow['dash_gestores']) 
                ? explode(',', $userRow['dash_gestores']) 
                : [];

            if (empty($dashGestores)) {
                return $this->json($response, []);
            }

            $inG = implode(',', array_map('intval', $dashGestores));
            
            $sql = "SELECT DISTINCT 
                        varg.idsupervisor as id, 
                        varg.nomegestor as nome
                    FROM vw_analise_receber_geral varg 
                    WHERE varg.idsupervisor IN ($inG)
                    ORDER BY varg.nomegestor";
            
            $stmt = $this->pdo->query($sql);
            $gestores = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->json($response, $gestores);
        } catch (Exception $e) {
            error_log('Erro getGestores: ' . $e->getMessage());
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /v1/analise-carteira/resumo-gestor
     */
    public function getResumoGestor(Request $request, Response $response): Response
    {
        $input = json_decode($request->getBody()->getContents(), true) ?? [];
        $uid = (int)($input['idusuario'] ?? 0);
        $idGestor = (int)($input['id_gestor'] ?? 0);

        try {
            // Para admin (uid=4), não precisa verificar dash_gestores
            if ($uid === 4) {
                $whereGestor = $idGestor > 0 ? "varg.idsupervisor = $idGestor" : "1=1";
            } else {
                $stUser = $this->pdo->prepare(
                    "SELECT dash_gestores FROM usuario WHERE idcliforemp = :uid"
                );
                $stUser->execute(['uid' => $uid]);
                $userRow = $stUser->fetch();

                $dashGestores = !empty($userRow['dash_gestores']) 
                    ? explode(',', $userRow['dash_gestores']) 
                    : [];

                if (empty($dashGestores)) {
                    return $this->json($response, ['error' => 'Sem permissão'], 403);
                }

                $inG = implode(',', array_map('intval', $dashGestores));
                if ($idGestor > 0 && in_array((string)$idGestor, $dashGestores)) {
                    $whereGestor = "varg.idsupervisor = $idGestor";
                } else {
                    $whereGestor = "varg.idsupervisor IN ($inG)";
                }
            }

            // KPIs Consolidados
            $sqlKpi = "SELECT 
                COALESCE(SUM(varg.vencidos), 0)::float as vencidos,
                COALESCE(SUM(varg.total_receber), 0)::float as total_receber,
                COALESCE(SUM(varg.dias_30), 0)::float as d30,
                COALESCE(SUM(varg.dias_60), 0)::float as d60,
                COALESCE(SUM(varg.mais_60_dias), 0)::float as d90,
                COALESCE(SUM(varg.a_vencer), 0)::float as a_vencer,
                COALESCE(SUM(varg.prox_30_dias), 0)::float as prox_30,
                COALESCE(SUM(varg.total_titulos), 0)::int as qtd_titulos,
                COALESCE(SUM(varg.total_cliente), 0)::int as qtd_clientes,
                COALESCE(SUM(varg.total_titulos_vencidos), 0)::int as titulos_vencidos,
                ROUND(SUM(varg.vencidos) * 100.0 / NULLIF(SUM(varg.total_receber), 0), 2) as iag
            FROM vw_analise_receber_geral varg 
            WHERE $whereGestor";

            error_log('SQL KPIs: ' . $sqlKpi);
            
            $stmt = $this->pdo->query($sqlKpi);
            $kpis = $stmt->fetch(PDO::FETCH_ASSOC);

            // Dados para gráfico de pizza
            $sqlPizza = "SELECT 
                '30 Dias' as faixa, 
                COALESCE(SUM(varg.dias_30), 0)::float as valor
            FROM vw_analise_receber_geral varg WHERE $whereGestor
            UNION ALL
            SELECT 
                '60 Dias' as faixa, 
                COALESCE(SUM(varg.dias_60), 0)::float as valor
            FROM vw_analise_receber_geral varg WHERE $whereGestor
            UNION ALL
            SELECT 
                '+60 Dias' as faixa, 
                COALESCE(SUM(varg.mais_60_dias), 0)::float as valor
            FROM vw_analise_receber_geral varg WHERE $whereGestor";

            $stmtPizza = $this->pdo->query($sqlPizza);
            $dadosPizza = $stmtPizza->fetchAll(PDO::FETCH_ASSOC);

            // TOP 5 Representantes
            $sqlTopRep = "SELECT 
                varg.nomerepresentante as nome,
                COALESCE(SUM(varg.vencidos), 0)::float as vencidos,
                COALESCE(SUM(varg.total_receber), 0)::float as total_receber,
                ROUND(SUM(varg.vencidos) * 100.0 / NULLIF(SUM(varg.total_receber), 0), 2) as iag,
                COALESCE(SUM(varg.total_titulos_vencidos), 0)::int as titulos_vencidos,
                COALESCE(SUM(varg.total_cliente), 0)::int as clientes,
                CASE 
                    WHEN SUM(varg.vencidos) * 100.0 / NULLIF(SUM(varg.total_receber), 0) > 15 THEN 'Crítico'
                    WHEN SUM(varg.vencidos) * 100.0 / NULLIF(SUM(varg.total_receber), 0) BETWEEN 5 AND 15 THEN 'Atenção'
                    ELSE 'Saudável'
                END as performance
            FROM vw_analise_receber_geral varg 
            WHERE $whereGestor
            GROUP BY varg.idvendrepre, varg.nomerepresentante
            ORDER BY vencidos DESC
            LIMIT 5";

            $stmtTop = $this->pdo->query($sqlTopRep);
            $topReps = $stmtTop->fetchAll(PDO::FETCH_ASSOC);

            return $this->json($response, [
                'kpis' => $kpis,
                'dados_pizza' => $dadosPizza,
                'top_representantes' => $topReps
            ]);
        } catch (Exception $e) {
            error_log('Erro getResumoGestor: ' . $e->getMessage());
            error_log('SQL com erro: ' . ($sqlKpi ?? 'N/A'));
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

   /**
 * POST /v1/analise-carteira/tabela-gestor
 */
public function getTabelaGestor(Request $request, Response $response): Response
{
    $input = json_decode($request->getBody()->getContents(), true) ?? [];
    $uid = (int)($input['idusuario'] ?? 0);
    $idGestor = (int)($input['id_gestor'] ?? 0);

    try {
        // Para admin (uid=4), não precisa verificar dash_gestores
        if ($uid === 4) {
            $whereGestor = $idGestor > 0 ? "varg.idsupervisor = $idGestor" : "1=1";
        } else {
            $stUser = $this->pdo->prepare(
                "SELECT dash_gestores FROM usuario WHERE idcliforemp = :uid"
            );
            $stUser->execute(['uid' => $uid]);
            $userRow = $stUser->fetch();

            $dashGestores = !empty($userRow['dash_gestores']) 
                ? explode(',', $userRow['dash_gestores']) 
                : [];

            if (empty($dashGestores)) {
                return $this->json($response, ['error' => 'Sem permissão'], 403);
            }

            $inG = implode(',', array_map('intval', $dashGestores));
            if ($idGestor > 0 && in_array((string)$idGestor, $dashGestores)) {
                $whereGestor = "varg.idsupervisor = $idGestor";
            } else {
                $whereGestor = "varg.idsupervisor IN ($inG)";
            }
        }

        // SQL simplificado - sem subquery no SELECT
   $sql = "SELECT 
    varg.idvendrepre as id_representante,
    varg.nomerepresentante as \"Nome do Representante\",
    COALESCE(SUM(varg.vencidos), 0)::float as \"Vencidos\",
    COALESCE(SUM(varg.total_receber), 0)::float as \"Total Receber\",
    COALESCE(SUM(varg.a_vencer), 0)::float as \"À Vencer\",
    COALESCE(SUM(varg.prox_30_dias), 0)::float as \"Próx. 30 Dias\",
    ROUND(SUM(varg.vencidos) * 100.0 / NULLIF(SUM(varg.total_receber), 0), 2) as \"IAG Rep (%)\",
    COALESCE(SUM(varg.dias_30), 0)::float as \"30 Dias\",
    COALESCE(SUM(varg.dias_60), 0)::float as \"60 Dias\",
    COALESCE(SUM(varg.mais_60_dias), 0)::float as \"M60 Dias\",
    ROUND(SUM(varg.dias_30) * 100.0 / NULLIF(SUM(varg.vencidos), 0), 2) as \"Perc. 30 Dias\",
    ROUND(SUM(varg.dias_60) * 100.0 / NULLIF(SUM(varg.vencidos), 0), 2) as \"Perc. 60 Dias\",
    ROUND(SUM(varg.mais_60_dias) * 100.0 / NULLIF(SUM(varg.vencidos), 0), 2) as \"Perc. M60 Dias\",
    COALESCE(SUM(varg.total_titulos_vencidos), 0)::int as \"Total Titulos\",
    COALESCE(SUM(varg.total_cliente), 0)::int as \"Total Clientes\",
    CASE 
        WHEN SUM(varg.vencidos) * 100.0 / NULLIF(SUM(varg.total_receber), 0) > 15 THEN 'CRÍTICO'
        WHEN SUM(varg.vencidos) * 100.0 / NULLIF(SUM(varg.total_receber), 0) BETWEEN 5 AND 15 THEN 'ATENÇÃO'
        ELSE 'SAUDÁVEL'
    END || ' (' || ROUND(SUM(varg.vencidos) * 100.0 / NULLIF(SUM(varg.total_receber), 0), 2) || '%)' as \"Performance_Com_IAG\"
FROM vw_analise_receber_geral varg 
WHERE $whereGestor
GROUP BY varg.idvendrepre, varg.nomerepresentante
ORDER BY \"Vencidos\" DESC";

        error_log('SQL Tabela Gestor: ' . $sql);

        $stmt = $this->pdo->query($sql);
        $tabela = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calcular Percentual em PHP (evita subquery)
        $totalVencidos = 0;
        foreach ($tabela as $row) {
            $totalVencidos += (float)($row['Vencidos'] ?? 0);
        }

        foreach ($tabela as &$row) {
            $vencidos = (float)($row['Vencidos'] ?? 0);
            $row['Percentual'] = $totalVencidos > 0 
                ? round(($vencidos / $totalVencidos) * 100, 2) 
                : 0;
        }

        error_log('Registros encontrados: ' . count($tabela));

        return $this->json($response, $tabela);
    } catch (Exception $e) {
        error_log('Erro getTabelaGestor: ' . $e->getMessage());
        error_log('SQL com erro: ' . ($sql ?? 'N/A'));
        return $this->json($response, ['error' => 'Erro ao consultar: ' . $e->getMessage()], 500);
    }
}

    /**
     * POST /v1/analise-carteira/titulos-representante
     */
    public function getTitulosRepresentante(Request $request, Response $response): Response
    {
        $input = json_decode($request->getBody()->getContents(), true) ?? [];
        $uid = (int)($input['idusuario'] ?? 0);
        $idRepresentante = (int)($input['id_representante'] ?? 0);

        try {
            if ($uid === 4) {
                // Admin vê tudo
                $whereExtra = "";
            } else {
                $stUser = $this->pdo->prepare(
                    "SELECT dash_gestores FROM usuario WHERE idcliforemp = :uid"
                );
                $stUser->execute(['uid' => $uid]);
                $userRow = $stUser->fetch();

                $dashGestores = !empty($userRow['dash_gestores']) 
                    ? explode(',', $userRow['dash_gestores']) 
                    : [];

                if (empty($dashGestores)) {
                    return $this->json($response, ['error' => 'Sem permissão'], 403);
                }

                $inG = implode(',', array_map('intval', $dashGestores));
                $whereExtra = "AND varg.idsupervisor IN ($inG)";
            }

            $sql = "SELECT DISTINCT
                vfe.nomefantasia as \"Nome Fantasia\",
                vfe.documento as \"Documento\",
                TO_CHAR(vfe.vencimento, 'DD/MM/YYYY') as \"Vencimento\",
                vfe.valorsaldo::float as \"Valor Saldo\",
                vfe.dias_atraso as \"Dias em Atrasos\",
                vfe.ult_evento_dias as \"Dias do Último Evento\",
                vfe.usuario as \"Usuário\",
                vfe.evento as \"Registro do Evento\"
            FROM vw_financeiro_eventos vfe
            JOIN vw_analise_receber_geral varg ON varg.idvendrepre = vfe.idrepresentante
            WHERE vfe.idrepresentante = :id_rep
                $whereExtra
                AND vfe.vencimento < (CURRENT_DATE - INTERVAL '3 days')
                AND vfe.valorsaldo > 0
            ORDER BY  TO_CHAR(vfe.vencimento, 'DD/MM/YYYY') ASC";

            error_log('SQL Títulos: ' . str_replace(':id_rep', (string)$idRepresentante, $sql));

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['id_rep' => $idRepresentante]);
            $titulos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            error_log('Títulos encontrados: ' . count($titulos));

            return $this->json($response, $titulos);
        } catch (Exception $e) {
            error_log('Erro getTitulosRepresentante: ' . $e->getMessage());
            return $this->json($response, ['error' => 'Erro ao consultar: ' . $e->getMessage()], 500);
        }
    }


    /**
     * POST /v1/analise-carteira/exportar
     */
    public function exportar(Request $request, Response $response): Response
    {
        $input = json_decode($request->getBody()->getContents(), true) ?? [];
        $tipo = $input['tipo'] ?? 'representantes';

        try {
            if ($tipo === 'representantes') {
                return $this->getTabelaGestor($request, $response);
            } elseif ($tipo === 'titulos') {
                return $this->getTitulosRepresentante($request, $response);
            }

            return $this->json($response, ['error' => 'Tipo inválido'], 400);
        } catch (Exception $e) {
            error_log('Erro exportar: ' . $e->getMessage());
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    private function json($response, $data, $status = 200): Response
    {
        $payload = json_encode($data, JSON_UNESCAPED_UNICODE);
        $response->getBody()->write($payload);
        return $response->withStatus($status)
            ->withHeader('Content-Type', 'application/json');
    }
}