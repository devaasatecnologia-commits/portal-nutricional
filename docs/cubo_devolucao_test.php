<?php
/**
 * cubo_devolucao_test.php
 *
 * Script de diagnóstico que executa a consulta de devoluções/comissões
 * filtrando apenas a filial 1 e o período 01/09/2026 a 30/09/2026,
 * comparando o SQL de relatório com o retorno oficial do VendasCuboController.
 */

declare(strict_types=1);

require_once __DIR__ . '/../v1/bootstrap/app.php';
require_once __DIR__ . '/../vendor/autoload.php';

$pdo = getPDO();

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // 1️⃣ Consulta do relatório de referência (filial = 1, período 01/09/2026 a 30/09/2026)
    $sql = <<<'SQL'
SELECT
    PEDIDO.IDFILIAL,
    FILIAL.NOME AS EMPRESA,
    DATE(MAX(PEDIDO.datahora)) AS ULTIMACOMPRA,
    PEDIDO.IDSUPERVISOR COD_GESTOR,
    GEST.FANTASIA AS GESTOR,
    PEDIDO.IDVENDREPRE AS COD_REPRESENTANTE,
    R.RAZAO AS NOME_REPRESENTANTE,
    COALESCE((SELECT SUM(P.VALORTOTALITENS)
              FROM PEDIDO P
              WHERE P.STATUS IN (1)
                AND P.DATA BETWEEN '2026-09-01' AND '2026-09-30'
                AND P.IDTRANSACAO IN (1,4,5)
                AND P.IDFILIAL = PEDIDO.IDFILIAL
                AND P.IDVENDREPRE = PEDIDO.IDVENDREPRE
                AND P.IDSUPERVISOR = PEDIDO.IDSUPERVISOR),0.00) AS SOMA_PEDIDOS_ABERTOS,
    COALESCE((SELECT SUM(NF.VALORTOTALNOTA)
              FROM NFS NF
              WHERE NF.DATAEMISSAO BETWEEN '2026-09-01' AND '2026-09-30'
                AND NF.STATUS IN (2)
                AND NF.IDTRANSACAO IN (1,13,1003)
                AND NF.IDFILIAL = PEDIDO.IDFILIAL
                AND NF.IDVENDREPRE = PEDIDO.IDVENDREPRE
                AND NF.IDSUPERVISOR = PEDIDO.IDSUPERVISOR),0.00) AS VALOR_FATURADO,
    COALESCE((SELECT SUM(N.VALORTOTALNOTA)
              FROM NFE N
              WHERE N.DATAENTRADA BETWEEN '2026-09-01' AND '2026-09-30'
                AND N.STATUS_DEVOLUCAO IN (1)
                AND N.IDTRANSACAO IN (9,15,1001,1004,1005,1011,1014,1031)
                AND N.STATUS IN (2)
                AND N.IDFILIAL = PEDIDO.IDFILIAL
                AND N.IDVENDREPRE = PEDIDO.IDVENDREPRE
                AND N.IDSUPERVISOR = PEDIDO.IDSUPERVISOR),0.00) AS VALOR_DEVOLVIDO,
    COALESCE((SELECT SUM(NF.VALORTOTALNOTA)
              FROM NFS NF
              WHERE NF.DATAEMISSAO BETWEEN '2026-09-01' AND '2026-09-30'
                AND NF.STATUS IN (2)
                AND NF.IDTRANSACAO IN (1,13,1003)
                AND NF.IDFILIAL = PEDIDO.IDFILIAL
                AND NF.IDVENDREPRE = PEDIDO.IDVENDREPRE
                AND NF.IDSUPERVISOR = PEDIDO.IDSUPERVISOR),0.00) -
    COALESCE((SELECT SUM(N.VALORTOTALNOTA)
              FROM NFE N
              WHERE N.DATAENTRADA BETWEEN '2026-09-01' AND '2026-09-30'
                AND N.STATUS_DEVOLUCAO IN (1)
                AND N.IDTRANSACAO IN (9,15,1001,1004,1005,1011,1014,1031)
                AND N.STATUS IN (2)
                AND N.IDFILIAL = PEDIDO.IDFILIAL
                AND N.IDVENDREPRE = PEDIDO.IDVENDREPRE
                AND N.IDSUPERVISOR = PEDIDO.IDSUPERVISOR),0.00) AS VALOR_TOTAL
FROM PEDIDO
LEFT JOIN FILIAL ON FILIAL.IDFILIAL = PEDIDO.IDFILIAL
LEFT JOIN CLIFOREMP GEST ON GEST.IDCLIFOREMP = PEDIDO.IDSUPERVISOR
LEFT JOIN CLIFOREMP R ON R.IDCLIFOREMP = PEDIDO.IDVENDREPRE
WHERE PEDIDO.IDFILIAL = 1
  AND PEDIDO.DATA BETWEEN '2026-09-01' AND '2026-09-30'
GROUP BY
    PEDIDO.IDFILIAL,
    FILIAL.NOME,
    PEDIDO.IDSUPERVISOR,
    GEST.FANTASIA,
    PEDIDO.IDVENDREPRE,
    R.RAZAO
ORDER BY PEDIDO.IDFILIAL, GEST.FANTASIA, R.RAZAO ASC;
SQL;

    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll();

    // 2️⃣ Retorno oficial do VendasCuboController para o mesmo filtro
    $ctrl = new \Nutricional\Controllers\VendasCuboController();
    $ref = new ReflectionClass($ctrl);
    $mFiltros = $ref->getMethod('filtrosVazios');
    $mFiltros->setAccessible(true);
    $f = $mFiltros->invoke($ctrl, '2026-09-01', '2026-09-30');
    $f['filiais'] = [1];

    $mTotais = $ref->getMethod('consultarTotais');
    $mTotais->setAccessible(true);
    $cuboTotais = $mTotais->invoke($ctrl, $f);

    $mDim = $ref->getMethod('consultarDimensao');
    $mDim->setAccessible(true);
    $cuboPorRepresentante = $mDim->invoke($ctrl, $f, 'representante', ['comissao_total', 'valor_devolvido', 'valor_total', 'quantidade_total'], 100);

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => 'success',
        'filial' => 1,
        'period' => ['start' => '2026-09-01', 'end' => '2026-09-30'],
        'cubo_totals' => $cuboTotais,
        'cubo_por_representante' => $cuboPorRepresentante,
        'relatorio_sql_count' => count($rows),
        'relatorio_sql_data' => $rows,
        'runtime' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit(1);
}
