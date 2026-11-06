<?php
namespace Nutricional\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PDO;

/**
 * Controller exclusivo para o módulo FLUXO E-COMMERCE (Cliente 16736 AZ PETS CO).
 *
 * Padrões fixados pelo negócio:
 *  - Cliente: 16736 (AZ PETS CO)
 *  - Transação: 4 (FILIAL 1 - PEDIDO INTERNO (SERIE 0))
 *  - Tabela de Preço: 15 ([WEB] ECOMERCE)
 *  - Condição Padrão Tabela: 1002 (AVISTA / PIX) | Cliente: 1072 (PRAZO [ECOMERCE] CARTEIRA)
 *  - Método Padrão Tabela: 1000 (PIX) | Cliente: 14 (CREDIARIO)
 *  - Filial: 1 (Matriz SC)
 *  - Vendedor/Representante: 10119 (tipovendrepre = 2)
 */
class FluxoEcomerceController
{
    public const CLIENTE_ID = 16736;
    public const TABELA_ID = 15;
    public const TRANSACAO_ID = 4;
    public const FILIAL_ID = 1;
    public const VENDEDOR_ID = 10119;
    public const CONDICAO_ID = 1002;
    public const METODO_ID = 1000;
    public const TIPO_VENDREPRE = 2;
    public const SERIE_TRANSACAO = '0';

    private $pdo;

    public function __construct()
    {
        $this->pdo = \getPDO();
    }

    private function json(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    private function usuario(Request $request): array
    {
        $user = $request->getAttribute('user') ?? [];
        $user = is_object($user) ? (array)$user : (array)$user;
        $idusuario = (int)($user['idusuario'] ?? 0);
        $isAdmin = !empty($user['is_admin']) || in_array('admin', (array)($user['permissoes'] ?? []), true);

        $stmt = $this->pdo->prepare('SELECT username, dash_filiais, dash_gestores FROM usuario WHERE idusuario = :id');
        $stmt->execute(['id' => $idusuario]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'idusuario' => $idusuario,
            'username' => (string)($row['username'] ?? ($user['username'] ?? 'PORTAL')),
            'is_admin' => $isAdmin
        ];
    }

    /**
     * Resolve dinamicamente a condição e método de pagamento:
     * 1º Prioridade: O que estiver cadastrado no Cliente (cliforemp.idcondicaopagto e cliente.idmetodo)
     * 2º Prioridade (fallback): Caso não tenha no cliente, busca da Tabela 15 (tabela_preco.idcondicao e tabela_preco.idmetodo)
     * 3º Fallback de segurança se ambos forem nulos no ERP
     */
    private function resolverCondicaoEMetodo(): array
    {
        $idCondicao = 0;
        $idMetodo = 0;
        $origem = 'padrao';

        // 1. Tenta buscar do cadastro do cliente 16736 (AZ PETS CO)
        try {
            $stmtCli = $this->pdo->prepare("
                SELECT c.idcondicaopagto, cli.idmetodo
                FROM cliforemp c
                JOIN cliente cli ON cli.idcliforemp = c.idcliforemp
                WHERE c.idcliforemp = :id
            ");
            $stmtCli->execute(['id' => self::CLIENTE_ID]);
            $rCli = $stmtCli->fetch(PDO::FETCH_ASSOC);
            if ($rCli) {
                if (!empty($rCli['idcondicaopagto'])) {
                    $idCondicao = (int)$rCli['idcondicaopagto'];
                }
                if (!empty($rCli['idmetodo'])) {
                    $idMetodo = (int)$rCli['idmetodo'];
                }
                if ($idCondicao > 0 && $idMetodo > 0) {
                    $origem = 'cliente';
                }
            }
        } catch (\Throwable $e) {
            error_log('[FluxoEcomerce] resolverCondicaoEMetodo cliente: ' . $e->getMessage());
        }

        // 2. Se faltar condição ou método no cliente, busca da Tabela de Preço 15
        if ($idCondicao <= 0 || $idMetodo <= 0) {
            try {
                $stmtTab = $this->pdo->prepare("
                    SELECT idcondicao, idmetodo
                    FROM tabela_preco
                    WHERE idtabela = :id
                ");
                $stmtTab->execute(['id' => self::TABELA_ID]);
                $rTab = $stmtTab->fetch(PDO::FETCH_ASSOC);
                if ($rTab) {
                    if ($idCondicao <= 0 && !empty($rTab['idcondicao'])) {
                        $idCondicao = (int)$rTab['idcondicao'];
                    }
                    if ($idMetodo <= 0 && !empty($rTab['idmetodo'])) {
                        $idMetodo = (int)$rTab['idmetodo'];
                    }
                }
                if ($origem !== 'cliente') {
                    $origem = 'tabela';
                }
            } catch (\Throwable $e) {
                error_log('[FluxoEcomerce] resolverCondicaoEMetodo tabela: ' . $e->getMessage());
            }
        }

        // 3. Fallback de contingência caso ambos estejam zerados no ERP
        if ($idCondicao <= 0) $idCondicao = self::CONDICAO_ID;
        if ($idMetodo <= 0) $idMetodo = self::METODO_ID;

        // Busca descrições oficiais dinamicamente do banco
        $condNome = "Condição $idCondicao";
        try {
            $stmt = $this->pdo->prepare("SELECT descricao FROM condicaopagto WHERE idcondicao = :id");
            $stmt->execute(['id' => $idCondicao]);
            $val = $stmt->fetchColumn();
            if ($val) $condNome = (string)$val;
        } catch (\Throwable $e) {}

        $metNome = "Método $idMetodo";
        try {
            $stmt = $this->pdo->prepare("SELECT descricao FROM metodopagto WHERE idmetodo = :id");
            $stmt->execute(['id' => $idMetodo]);
            $val = $stmt->fetchColumn();
            if ($val) $metNome = (string)$val;
        } catch (\Throwable $e) {}

        return [
            'idcondicao' => $idCondicao,
            'condicao_nome' => $condNome,
            'idmetodo' => $idMetodo,
            'metodo_nome' => $metNome,
            'origem' => $origem
        ];
    }

    private function processarImagem(?string $path_foto_master): string
    {
        $sem = 'https://placehold.co/120x120?text=S/F';
        if (empty($path_foto_master) || in_array(trim($path_foto_master), ['.', '-', 'null', 'undefined'], true)) {
            return $sem;
        }
        $n = str_replace('\\', '/', trim($path_foto_master));
        if (preg_match('/^https?:\/\//i', $n)) {
            return str_replace(' ', '%20', $n);
        }
        $marker = 'fotos para o site/';
        $pos = stripos($n, $marker);
        $rel = $pos !== false ? substr($n, $pos + strlen($marker)) : ltrim(preg_replace('/^fotos\//i', '', $n), '/');
        if (empty($rel)) {
            return $sem;
        }
        $parts = array_map('rawurlencode', explode('/', $rel));
        return 'https://acesso.nutricionalbr.com:2053/fotos/' . implode('/', $parts);
    }

    /**
     * GET /v1/fluxo-ecomerce/contexto
     * Retorna os dados cadastrais fixados do cliente, tabela, transação e usuário.
     */
    public function contexto(Request $request, Response $response): Response
    {
        try {
            $usuario = $this->usuario($request);

            // Dados do Cliente 16736
            $stmtCli = $this->pdo->prepare("
                SELECT c.idcliforemp, c.razao, c.fantasia, c.cnpj, c.uf,
                       c.idcondicaopagto, c.idmetodo,
                       cli.idvendedor, cli.valorlimitecredito
                FROM cliforemp c
                LEFT JOIN cliente cli ON cli.idcliforemp = c.idcliforemp
                WHERE c.idcliforemp = :id
            ");
            $stmtCli->execute(['id' => self::CLIENTE_ID]);
            $cliente = $stmtCli->fetch(PDO::FETCH_ASSOC);

            // Dados da Tabela de Preço 15
            $stmtTab = $this->pdo->prepare("
                SELECT idtabela, descricao as nome, idcondicao, idmetodo, filiais, observacao
                FROM tabela_preco
                WHERE idtabela = :id
            ");
            $stmtTab->execute(['id' => self::TABELA_ID]);
            $tabela = $stmtTab->fetch(PDO::FETCH_ASSOC);

            // Dados da Transação 4 (PEDIDO INTERNO - SERIE 0)
            $stmtTrans = $this->pdo->prepare("
                SELECT idtransacao, descricao as nome, idserie
                FROM pedido_transacao
                WHERE idtransacao = :id
            ");
            $stmtTrans->execute(['id' => self::TRANSACAO_ID]);
            $transacao = $stmtTrans->fetch(PDO::FETCH_ASSOC);

            // Dados da Filial 1
            $stmtFil = $this->pdo->prepare("
                SELECT idfilial, razao, uf
                FROM filial
                WHERE idfilial = :id
            ");
            $stmtFil->execute(['id' => self::FILIAL_ID]);
            $filial = $stmtFil->fetch(PDO::FETCH_ASSOC);

            // Lista de Condições de Pagamento ativas para venda (destacando 1002 e 1072 no topo)
            $stmtCond = $this->pdo->query("
                SELECT idcondicao, descricao, parcelas
                FROM condicaopagto
                WHERE inativo = 'N' AND venda = 'S'
                ORDER BY CASE 
                    WHEN idcondicao = 1002 THEN 1
                    WHEN idcondicao = 1072 THEN 2
                    ELSE 10 
                END, descricao ASC
            ");
            $condicoes = $stmtCond->fetchAll(PDO::FETCH_ASSOC);

            // Lista de Métodos de Pagamento ativos para venda (destacando 1000 e 14 no topo)
            $stmtMet = $this->pdo->query("
                SELECT idmetodo, descricao
                FROM metodopagto
                WHERE inativo = 'N' AND mostravenda = 'S'
                ORDER BY CASE 
                    WHEN idmetodo = 1000 THEN 1
                    WHEN idmetodo = 14 THEN 2
                    ELSE 10 
                END, descricao ASC
            ");
            $metodos = $stmtMet->fetchAll(PDO::FETCH_ASSOC);

            // Hierarquia dinâmica: Cliente 1º -> Tabela 2º -> Fallback
            $padroesDinamicos = $this->resolverCondicaoEMetodo();

            $idCondTabela = (int)($tabela['idcondicao'] ?? 0);
            $idMetTabela = (int)($tabela['idmetodo'] ?? 0);
            $idCondCliente = (int)($cliente['idcondicaopagto'] ?? 0);
            $idMetCliente = (int)($cliente['idmetodo'] ?? 0);

            return $this->json($response, [
                'success' => true,
                'cliente' => $cliente,
                'tabela' => $tabela,
                'transacao' => $transacao,
                'filial' => $filial,
                'usuario' => $usuario,
                'condicoes' => $condicoes,
                'metodos' => $metodos,
                'padroes' => [
                    'idcliente' => self::CLIENTE_ID,
                    'idtabela' => self::TABELA_ID,
                    'idtransacao' => self::TRANSACAO_ID,
                    'idserie' => self::SERIE_TRANSACAO,
                    'idfilial' => self::FILIAL_ID,
                    'idvendrepre' => self::VENDEDOR_ID,
                    'idcondicao' => $padroesDinamicos['idcondicao'],
                    'condicao_nome' => $padroesDinamicos['condicao_nome'],
                    'idmetodo' => $padroesDinamicos['idmetodo'],
                    'metodo_nome' => $padroesDinamicos['metodo_nome'],
                    'origem_regra' => $padroesDinamicos['origem'],
                    'condicao_tabela' => $idCondTabela,
                    'metodo_tabela' => $idMetTabela,
                    'condicao_cliente' => $idCondCliente,
                    'metodo_cliente' => $idMetCliente,
                ]
            ]);
        } catch (\Throwable $e) {
            error_log('[FluxoEcomerce] contexto: ' . $e->getMessage());
            return $this->json($response, ['success' => false, 'error' => 'Erro ao carregar contexto'], 500);
        }
    }

    /**
     * GET /v1/fluxo-ecomerce/itens
     * Retorna os itens ativos da Tabela 15 com valor calculado, estoque filial 1 e foto.
     */
    public function itens(Request $request, Response $response): Response
    {
        try {
            $q = trim((string)($request->getQueryParams()['q'] ?? ''));

            $sql = "
                SELECT 
                    tp.idtabela,
                    tp.descricao as tabela_nome,
                    tp.idcondicao,
                    tp.idmetodo,
                    item.iditem,
                    item.referencia,
                    item.descricao, 
                    ROUND(tpi.valorcalculado::numeric, 2) as valor_unitario,
                    item.path_foto_master,
                    item.pesobruto,
                    item.pesoliquido,
                    item.idunidadebasica,
                    COALESCE((
                        SELECT SUM(lfd.quantidade) 
                        FROM lote_filial_deposito lfd 
                        WHERE lfd.iditem = item.iditem AND lfd.idfilial = :idfilial
                    ), 0) as saldo_estoque
                FROM tabela_preco_item tpi
                JOIN item ON item.iditem = tpi.iditem AND item.inativo = 'N'
                JOIN tabela_preco tp ON tp.idtabela = tpi.idtabela 
                WHERE tpi.idtabela = :idtabela
            ";

            $params = [
                'idtabela' => self::TABELA_ID,
                'idfilial' => self::FILIAL_ID
            ];

            if ($q !== '') {
                $sql .= " AND (item.iditem::text = :q_exato OR item.referencia ILIKE :q_like OR item.descricao ILIKE :q_like)";
                $params['q_exato'] = $q;
                $params['q_like'] = '%' . $q . '%';
            }

            $sql .= " ORDER BY item.descricao ASC";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($itens as &$it) {
                $it['foto_url'] = $this->processarImagem($it['path_foto_master'] ?? null);
                $it['saldo_estoque'] = (float)$it['saldo_estoque'];
                $it['valor_unitario'] = (float)$it['valor_unitario'];
                $it['pesobruto'] = (float)$it['pesobruto'];
                $it['pesoliquido'] = (float)$it['pesoliquido'];
            }

            return $this->json($response, [
                'success' => true,
                'total' => count($itens),
                'itens' => $itens
            ]);
        } catch (\Throwable $e) {
            error_log('[FluxoEcomerce] itens: ' . $e->getMessage());
            return $this->json($response, ['success' => false, 'error' => 'Erro ao buscar itens da tabela de e-commerce'], 500);
        }
    }

    /**
     * GET /v1/fluxo-ecomerce/dashboard
     * Retorna métricas consolidadas, KPIs e ranking de top itens vendidos no período (padrão: mês corrente).
     */
    public function dashboard(Request $request, Response $response): Response
    {
        try {
            $p = $request->getQueryParams();
            $historicoCompleto = !empty($p['historico_completo']) && $p['historico_completo'] === '1';

            $dataInicio = trim((string)($p['data_inicio'] ?? ''));
            $dataFim = trim((string)($p['data_fim'] ?? ''));
            $statusFiltro = trim((string)($p['status'] ?? 'todos'));

            // Se não especificado e não for histórico completo, o padrão é o mês corrente
            if (!$historicoCompleto && $dataInicio === '' && $dataFim === '') {
                $dataInicio = date('Y-m-01');
                $dataFim = date('Y-m-d');
            }

            // 1. Estatísticas Gerais do Cliente no ERP com filtros de data e status
            $sqlKpi = "
                SELECT 
                    COUNT(p.idpedido) as total_pedidos,
                    COALESCE(SUM(p.valortotalpedido), 0) as faturamento_total,
                    COALESCE(AVG(p.valortotalpedido), 0) as ticket_medio,
                    MIN(p.data) as primeiro_pedido,
                    MAX(p.data) as ultimo_pedido
                FROM pedido p
                WHERE p.idcliforemp = :idcliente
                  AND p.status IN (1, 5)
            ";
            $paramsKpi = ['idcliente' => self::CLIENTE_ID];

            if ($dataInicio !== '') {
                $sqlKpi .= " AND p.data >= :data_inicio";
                $paramsKpi['data_inicio'] = $dataInicio;
            }
            if ($dataFim !== '') {
                $sqlKpi .= " AND p.data <= :data_fim";
                $paramsKpi['data_fim'] = $dataFim;
            }
            if ($statusFiltro === 'aberto') {
                $sqlKpi .= " AND p.status = 1";
            } elseif ($statusFiltro === 'faturado') {
                $sqlKpi .= " AND p.status = 5";
            }

            $stmtKpi = $this->pdo->prepare($sqlKpi);
            $stmtKpi->execute($paramsKpi);
            $kpi = $stmtKpi->fetch(PDO::FETCH_ASSOC) ?: [];

            // 2. Pedidos Pendentes de Importação no Palmtop
            $sqlPend = "
                SELECT COUNT(pp.idpedidopda) as total_pendentes,
                       COALESCE(SUM(pp.valortotal), 0) as valor_pendente
                FROM palmtop_pedido pp
                WHERE pp.idcliente = :idcliente
                  AND pp.importado = 'N'
                  AND pp.status <> 2
            ";
            $paramsPend = ['idcliente' => self::CLIENTE_ID];
            if ($dataInicio !== '') {
                $sqlPend .= " AND pp.data >= :data_inicio";
                $paramsPend['data_inicio'] = $dataInicio;
            }
            if ($dataFim !== '') {
                $sqlPend .= " AND pp.data <= :data_fim";
                $paramsPend['data_fim'] = $dataFim;
            }

            $stmtPend = $this->pdo->prepare($sqlPend);
            $stmtPend->execute($paramsPend);
            $pendentes = $stmtPend->fetch(PDO::FETCH_ASSOC) ?: [];

            // 3. Top Itens Vendidos do Cliente no período
            $sqlTop = "
                SELECT 
                    pi.iditem, 
                    i.referencia, 
                    i.descricao, 
                    i.path_foto_master,
                    SUM(pi.qt) as total_quantidade,
                    SUM(pi.valortotal) as total_valor,
                    COUNT(DISTINCT p.idpedido) as total_pedidos
                FROM pedido p
                JOIN pedido_item pi ON pi.idpedido = p.idpedido
                JOIN item i ON i.iditem = pi.iditem
                WHERE p.idcliforemp = :idcliente
                  AND p.status IN (1, 5)
            ";
            $paramsTop = ['idcliente' => self::CLIENTE_ID];

            if ($dataInicio !== '') {
                $sqlTop .= " AND p.data >= :data_inicio";
                $paramsTop['data_inicio'] = $dataInicio;
            }
            if ($dataFim !== '') {
                $sqlTop .= " AND p.data <= :data_fim";
                $paramsTop['data_fim'] = $dataFim;
            }
            if ($statusFiltro === 'aberto') {
                $sqlTop .= " AND p.status = 1";
            } elseif ($statusFiltro === 'faturado') {
                $sqlTop .= " AND p.status = 5";
            }

            $sqlTop .= "
                GROUP BY pi.iditem, i.referencia, i.descricao, i.path_foto_master
                ORDER BY total_quantidade DESC
                LIMIT 8
            ";

            $stmtTop = $this->pdo->prepare($sqlTop);
            $stmtTop->execute($paramsTop);
            $topItens = $stmtTop->fetchAll(PDO::FETCH_ASSOC);

            foreach ($topItens as &$top) {
                $top['foto_url'] = $this->processarImagem($top['path_foto_master'] ?? null);
                $top['total_quantidade'] = (float)$top['total_quantidade'];
                $top['total_valor'] = (float)$top['total_valor'];
                $top['total_pedidos'] = (int)$top['total_pedidos'];
            }

            return $this->json($response, [
                'success' => true,
                'periodo' => [
                    'data_inicio' => $dataInicio,
                    'data_fim' => $dataFim,
                    'status' => $statusFiltro,
                    'historico_completo' => $historicoCompleto
                ],
                'metricas' => [
                    'total_pedidos' => (int)($kpi['total_pedidos'] ?? 0),
                    'faturamento_total' => (float)($kpi['faturamento_total'] ?? 0),
                    'ticket_medio' => (float)($kpi['ticket_medio'] ?? 0),
                    'primeiro_pedido' => $kpi['primeiro_pedido'] ?? null,
                    'ultimo_pedido' => $kpi['ultimo_pedido'] ?? null,
                    'pedidos_pendentes' => (int)($pendentes['total_pendentes'] ?? 0),
                    'valor_pendente' => (float)($pendentes['valor_pendente'] ?? 0),
                ],
                'top_itens' => $topItens
            ]);
        } catch (\Throwable $e) {
            error_log('[FluxoEcomerce] dashboard: ' . $e->getMessage());
            return $this->json($response, ['success' => false, 'error' => 'Erro ao carregar dashboard'], 500);
        }
    }

    /**
     * GET /v1/fluxo-ecomerce/pedidos
     * Lista histórico de pedidos do cliente com filtros de data, status e busca.
     */
    public function pedidos(Request $request, Response $response): Response
    {
        try {
            $p = $request->getQueryParams();
            $historicoCompleto = !empty($p['historico_completo']) && $p['historico_completo'] === '1';

            $dataInicio = trim((string)($p['data_inicio'] ?? ''));
            $dataFim = trim((string)($p['data_fim'] ?? ''));
            $statusFiltro = trim((string)($p['status'] ?? 'todos'));
            $busca = trim((string)($p['q'] ?? ''));

            // Se não especificado e não for histórico completo, o padrão é o mês corrente
            if (!$historicoCompleto && $dataInicio === '' && $dataFim === '') {
                $dataInicio = date('Y-m-01');
                $dataFim = date('Y-m-d');
            }

            // 1. Pedidos do ERP
            $sqlErp = "
                SELECT 
                    pedido.idpedido,
                    pedido.data,
                    pedido.idfilial,
                    fil.razao as filial_nome,
                    cli.idcliforemp as idcliente,
                    cli.fantasia as cliente_nome,
                    COALESCE(pp.usuario, pedido.usuario, 'ERP') as solicitante,
                    CASE 
                        WHEN COALESCE(pedido.idpedidopda, pp.idpedidopda, 0) > 0 THEN 'PORTAL' 
                        ELSE 'ERP' 
                    END as origem,
                    pedido.observacao,
                    pedido.status as status_pedido,
                    CASE 
                        WHEN pedido.status = 5 THEN 'FATURADO'
                        WHEN pedido.status = 1 THEN 'ABERTO'
                        ELSE 'OUTRO'
                    END as status_label,
                    pedido.valortotalpedido as valortotal,
                    STRING_AGG(CONCAT(CAST(pedido_item.qt AS INTEGER), 'x ', item.descricao), CHR(10) ORDER BY item.descricao) AS resumo_itens,
                    COUNT(pedido_item.iditem) as total_itens,
                    SUM(pedido_item.qt) as total_unidades,
                    FALSE as pendente
                FROM pedido
                JOIN filial fil ON fil.idfilial = pedido.idfilial
                JOIN cliforemp cli ON cli.idcliforemp = pedido.idcliforemp
                LEFT JOIN palmtop_pedido pp ON pp.idpedidopda = pedido.idpedidopda
                JOIN pedido_item ON pedido_item.idpedido = pedido.idpedido
                JOIN item ON item.iditem = pedido_item.iditem
                WHERE pedido.idcliforemp = :idcliente
                  AND pedido.status IN (1, 5)
            ";

            $paramsErp = ['idcliente' => self::CLIENTE_ID];

            if ($dataInicio !== '') {
                $sqlErp .= " AND pedido.data >= :data_inicio";
                $paramsErp['data_inicio'] = $dataInicio;
            }
            if ($dataFim !== '') {
                $sqlErp .= " AND pedido.data <= :data_fim";
                $paramsErp['data_fim'] = $dataFim;
            }
            if ($statusFiltro === 'aberto') {
                $sqlErp .= " AND pedido.status = 1";
            } elseif ($statusFiltro === 'faturado') {
                $sqlErp .= " AND pedido.status = 5";
            }

            $sqlErp .= "
                GROUP BY pedido.idpedido, pedido.data, pedido.idfilial, fil.razao, cli.idcliforemp, cli.fantasia,
                         pp.usuario, pedido.usuario, pedido.idpedidopda, pp.idpedidopda, pedido.observacao, pedido.status, pedido.valortotalpedido
                ORDER BY pedido.idpedido DESC
                LIMIT 100
            ";

            $stmtErp = $this->pdo->prepare($sqlErp);
            $stmtErp->execute($paramsErp);
            $pedidosErp = $stmtErp->fetchAll(PDO::FETCH_ASSOC);

            // 2. Pedidos Pendentes no Palmtop (criados no Portal e ainda não importados para o ERP)
            $pedidosPalmtop = [];
            if ($statusFiltro === 'todos' || $statusFiltro === 'pendente') {
                $sqlPp = "
                    SELECT 
                        pp.idpedidopda AS idpedido,
                        pp.data,
                        pp.idfilial,
                        fil.razao as filial_nome,
                        pp.idcliente,
                        pp.nomecliente as cliente_nome,
                        COALESCE(pp.usuario, 'PORTAL') as solicitante,
                        'PORTAL' as origem,
                        pp.observacao,
                        0 as status_pedido,
                        'PENDENTE' as status_label,
                        pp.valortotal,
                        STRING_AGG(CONCAT(CAST(ppi.qt AS INTEGER), 'x ', ppi.descricao), CHR(10) ORDER BY ppi.descricao) AS resumo_itens,
                        COUNT(ppi.iditem) as total_itens,
                        SUM(ppi.qt) as total_unidades,
                        TRUE as pendente
                    FROM palmtop_pedido pp
                    JOIN filial fil ON fil.idfilial = pp.idfilial
                    JOIN palmtop_pedido_item ppi ON ppi.idpedidopda = pp.idpedidopda
                    WHERE pp.idcliente = :idcliente
                      AND pp.importado = 'N'
                      AND pp.status <> 2
                ";
                $paramsPp = ['idcliente' => self::CLIENTE_ID];

                if ($dataInicio !== '') {
                    $sqlPp .= " AND pp.data >= :data_inicio";
                    $paramsPp['data_inicio'] = $dataInicio;
                }
                if ($dataFim !== '') {
                    $sqlPp .= " AND pp.data <= :data_fim";
                    $paramsPp['data_fim'] = $dataFim;
                }

                $sqlPp .= "
                    GROUP BY pp.idpedidopda, pp.data, pp.idfilial, fil.razao, pp.idcliente, pp.nomecliente, pp.usuario, pp.observacao, pp.valortotal
                    ORDER BY pp.idpedidopda DESC
                    LIMIT 50
                ";

                $stmtPp = $this->pdo->prepare($sqlPp);
                $stmtPp->execute($paramsPp);
                $pedidosPalmtop = $stmtPp->fetchAll(PDO::FETCH_ASSOC);
            }

            // Junta tudo mantendo os pendentes no topo
            $todos = array_merge($pedidosPalmtop, $pedidosErp);

            // Filtro textual livre caso preenchido
            if ($busca !== '') {
                $bLower = mb_strtolower($busca);
                $todos = array_values(array_filter($todos, function ($p) use ($bLower) {
                    return str_contains(mb_strtolower((string)$p['idpedido']), $bLower)
                        || str_contains(mb_strtolower((string)$p['observacao']), $bLower)
                        || str_contains(mb_strtolower((string)$p['solicitante']), $bLower)
                        || str_contains(mb_strtolower((string)$p['resumo_itens']), $bLower);
                }));
            }

            return $this->json($response, [
                'success' => true,
                'total' => count($todos),
                'pedidos' => $todos
            ]);
        } catch (\Throwable $e) {
            error_log('[FluxoEcomerce] pedidos: ' . $e->getMessage());
            return $this->json($response, ['success' => false, 'error' => 'Erro ao listar pedidos'], 500);
        }
    }

    /**
     * GET /v1/fluxo-ecomerce/pedidos/{id}
     * Detalhes de um pedido (ERP ou Palmtop).
     */
    public function detalhesPedido(Request $request, Response $response, array $args): Response
    {
        $id = (int)($args['id'] ?? 0);
        if ($id <= 0) {
            return $this->json($response, ['success' => false, 'error' => 'Pedido inválido'], 400);
        }

        try {
            // 1. Tenta buscar no ERP
            $stmt = $this->pdo->prepare("
                SELECT p.idpedido, p.data, p.idfilial, fil.razao as filial_nome,
                       cli.idcliforemp as idcliente, cli.fantasia as cliente_nome, cli.razao,
                       COALESCE(pp.usuario, p.usuario, 'ERP') as solicitante,
                       CASE WHEN COALESCE(p.idpedidopda, pp.idpedidopda, 0) > 0 THEN 'PORTAL' ELSE 'ERP' END as origem,
                       p.observacao, p.status as status_pedido, p.valortotalpedido as valortotal
                FROM pedido p
                JOIN filial fil ON fil.idfilial = p.idfilial
                JOIN cliforemp cli ON cli.idcliforemp = p.idcliforemp
                LEFT JOIN palmtop_pedido pp ON pp.idpedidopda = p.idpedidopda
                WHERE p.idpedido = :id AND p.idcliforemp = :idcliente
            ");
            $stmt->execute(['id' => $id, 'idcliente' => self::CLIENTE_ID]);
            $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($pedido) {
                $stmtItens = $this->pdo->prepare("
                    SELECT pi.iditem, i.descricao, i.referencia, pi.qt,
                           ROUND(pi.valortotal / NULLIF(pi.qt, 0), 2) as valor_unitario,
                           pi.valortotal, i.path_foto_master
                    FROM pedido_item pi
                    JOIN item i ON i.iditem = pi.iditem
                    WHERE pi.idpedido = :id
                    ORDER BY i.descricao
                ");
                $stmtItens->execute(['id' => $id]);
                $itens = $stmtItens->fetchAll(PDO::FETCH_ASSOC);
                foreach ($itens as &$it) {
                    $it['foto_url'] = $this->processarImagem($it['path_foto_master'] ?? null);
                    $it['qt'] = (float)$it['qt'];
                    $it['valor_unitario'] = (float)$it['valor_unitario'];
                    $it['valortotal'] = (float)$it['valortotal'];
                }
                $pedido['itens'] = $itens;
                return $this->json($response, ['success' => true, 'pedido' => $pedido]);
            }

            // 2. Se não achou no ERP, busca nos pendentes do Palmtop
            $stmtPp = $this->pdo->prepare("
                SELECT pp.idpedidopda as idpedido, pp.data, pp.idfilial, fil.razao as filial_nome,
                       pp.idcliente, pp.nomecliente as cliente_nome,
                       pp.usuario as solicitante, 'PORTAL' as origem,
                       pp.observacao, 0 as status_pedido, TRUE as pendente, pp.valortotal
                FROM palmtop_pedido pp
                JOIN filial fil ON fil.idfilial = pp.idfilial
                WHERE pp.idpedidopda = :id AND pp.idcliente = :idcliente
            ");
            $stmtPp->execute(['id' => $id, 'idcliente' => self::CLIENTE_ID]);
            $pedidoPp = $stmtPp->fetch(PDO::FETCH_ASSOC);

            if ($pedidoPp) {
                $stmtItensPp = $this->pdo->prepare("
                    SELECT ppi.iditem, ppi.descricao, i.referencia, ppi.qt,
                           ppi.valor as valor_unitario, ppi.valortotal, i.path_foto_master
                    FROM palmtop_pedido_item ppi
                    LEFT JOIN item i ON i.iditem = ppi.iditem
                    WHERE ppi.idpedidopda = :id
                    ORDER BY ppi.descricao
                ");
                $stmtItensPp->execute(['id' => $id]);
                $itensPp = $stmtItensPp->fetchAll(PDO::FETCH_ASSOC);
                foreach ($itensPp as &$it) {
                    $it['foto_url'] = $this->processarImagem($it['path_foto_master'] ?? null);
                    $it['qt'] = (float)$it['qt'];
                    $it['valor_unitario'] = (float)$it['valor_unitario'];
                    $it['valortotal'] = (float)$it['valortotal'];
                }
                $pedidoPp['itens'] = $itensPp;
                return $this->json($response, ['success' => true, 'pedido' => $pedidoPp]);
            }

            return $this->json($response, ['success' => false, 'error' => 'Pedido não encontrado'], 404);
        } catch (\Throwable $e) {
            error_log('[FluxoEcomerce] detalhesPedido: ' . $e->getMessage());
            return $this->json($response, ['success' => false, 'error' => 'Erro ao carregar detalhes do pedido'], 500);
        }
    }

    /**
     * POST /v1/fluxo-ecomerce/pedidos
     * Cria o pedido no Palmtop com a amarração exata da Tabela 15 e Cliente 16736.
     */
    public function criar(Request $request, Response $response): Response
    {
        $in = json_decode((string)$request->getBody(), true);
        if (!is_array($in)) {
            return $this->json($response, ['success' => false, 'error' => 'Payload JSON inválido'], 400);
        }

        $observacaoUsuario = trim((string)($in['observacao'] ?? ''));

        // Regra de precedência estrita: 1º Cliente -> 2º Tabela 15 -> 3º Fallback
        $padroesDinamicos = $this->resolverCondicaoEMetodo();
        $idCondicaoFinal = !empty($in['idcondicao']) ? (int)$in['idcondicao'] : $padroesDinamicos['idcondicao'];
        $idMetodoFinal = !empty($in['idmetodo']) ? (int)$in['idmetodo'] : $padroesDinamicos['idmetodo'];

        // Garante que se o cadastro do ERP mudou ou se vier zerado, assume a regra dinâmica
        if ($idCondicaoFinal <= 0) $idCondicaoFinal = $padroesDinamicos['idcondicao'];
        if ($idMetodoFinal <= 0) $idMetodoFinal = $padroesDinamicos['idmetodo'];
        $quantidades = [];

        foreach ((array)($in['itens'] ?? []) as $item) {
            $iditem = (int)($item['iditem'] ?? 0);
            $qt = (float)($item['quantidade'] ?? 0);
            if ($iditem > 0 && $qt > 0) {
                $quantidades[$iditem] = ($quantidades[$iditem] ?? 0) + $qt;
            }
        }

        if (empty($quantidades)) {
            return $this->json($response, ['success' => false, 'error' => 'Adicione ao menos um item com quantidade maior que zero.'], 422);
        }

        if (mb_strlen($observacaoUsuario) > 500) {
            return $this->json($response, ['success' => false, 'error' => 'A observação deve ter no máximo 500 caracteres.'], 422);
        }

        try {
            $usuario = $this->usuario($request);

            // 1. Busca os itens na Tabela 15 para garantir preço correto e validação
            $paramItens = [];
            $placeholders = [];
            $idx = 0;
            foreach (array_keys($quantidades) as $kId) {
                $paramKey = ':item_' . $idx++;
                $placeholders[] = $paramKey;
                $paramItens[$paramKey] = (int)$kId;
            }
            $idsPlaceholders = implode(',', $placeholders);

            $sqlItens = "
                SELECT 
                    tp.idtabela,
                    tp.idcondicao,
                    tp.idmetodo,
                    item.iditem,
                    item.referencia,
                    item.descricao, 
                    item.complemento,
                    item.pesobruto,
                    item.pesoliquido,
                    item.idunidadebasica,
                    item.perccomissao,
                    ROUND(tpi.valorcalculado::numeric, 2) as valor_unitario,
                    ef.valorcustocontabil,
                    ef.valorcustomediounitario,
                    ef.custogerencial,
                    ef.percmargem,
                    COALESCE(ie.perc_ipi, 0) as perc_ipi,
                    COALESCE(ie.idsituacaotributaria, 0) as idsituacaotributaria,
                    COALESCE((
                        SELECT SUM(lfd.quantidade) 
                        FROM lote_filial_deposito lfd 
                        WHERE lfd.iditem = item.iditem AND lfd.idfilial = :idfilial
                    ), 0) as saldo_estoque
                FROM tabela_preco_item tpi
                JOIN item ON item.iditem = tpi.iditem AND item.inativo = 'N'
                JOIN tabela_preco tp ON tp.idtabela = tpi.idtabela 
                LEFT JOIN estoque_filial ef ON ef.iditem = item.iditem AND ef.idfilial = :idfilial
                LEFT JOIN filial f ON f.idfilial = :idfilial
                LEFT JOIN imposto_estado ie ON ie.idimposto = ef.idimposto 
                                           AND ie.tipo_enquadramento = f.tipoenquadraformapreco 
                                           AND ie.uf = f.uf
                WHERE tpi.idtabela = :idtabela
                  AND item.iditem IN ($idsPlaceholders)
            ";

            $stmtItens = $this->pdo->prepare($sqlItens);
            $stmtItens->bindValue(':idfilial', self::FILIAL_ID, PDO::PARAM_INT);
            $stmtItens->bindValue(':idtabela', self::TABELA_ID, PDO::PARAM_INT);

            foreach ($paramItens as $pKey => $pVal) {
                $stmtItens->bindValue($pKey, $pVal, PDO::PARAM_INT);
            }
            $stmtItens->execute();
            $itensDb = $stmtItens->fetchAll(PDO::FETCH_ASSOC);

            $mapItens = [];
            foreach ($itensDb as $row) {
                $mapItens[(int)$row['iditem']] = $row;
            }

            // Validação de presença e saldo
            $itensProcessados = [];
            $valorTotalItens = 0;
            $pesoBrutoTotal = 0;
            $pesoLiquidoTotal = 0;

            foreach ($quantidades as $iditem => $qt) {
                if (!isset($mapItens[$iditem])) {
                    return $this->json($response, [
                        'success' => false,
                        'error' => "O item ID {$iditem} não pertence à Tabela 15 ([WEB] ECOMERCE) ou está inativo."
                    ], 422);
                }

                $d = $mapItens[$iditem];
                $saldo = (float)$d['saldo_estoque'];
                if ($qt > $saldo) {
                    return $this->json($response, [
                        'success' => false,
                        'error' => "A quantidade solicitada ({$qt}) para o item \"{$d['descricao']}\" é maior que o saldo em estoque liberado ({$saldo})."
                    ], 422);
                }

                $valorUnit = (float)$d['valor_unitario'];
                $valTotal = round($qt * $valorUnit, 2);
                $pBruto = (float)($d['pesobruto'] ?? 0) * $qt;
                $pLiquido = (float)($d['pesoliquido'] ?? 0) * $qt;

                $itensProcessados[] = [
                    'iditem' => $iditem,
                    'quantidade' => $qt,
                    'valor_unitario' => $valorUnit,
                    'valor_total' => $valTotal,
                    'descricao' => (string)$d['descricao'],
                    'referencia' => (string)$d['referencia'],
                    'complemento' => (string)($d['complemento'] ?? '.'),
                    'idunidade' => (int)($d['idunidadebasica'] ?? 1),
                    'pesobruto' => $pBruto,
                    'pesoliquido' => $pLiquido,
                    'perc_ipi' => (float)($d['perc_ipi'] ?? 0),
                    'perc_comissao' => (float)($d['perccomissao'] ?? 0),
                    'perc_margem' => (float)($d['percmargem'] ?? 0),
                    'valorcustocontabil' => (float)($d['valorcustocontabil'] ?? 0),
                    'valorcustogerencial' => (float)($d['custogerencial'] ?? 0),
                    'valorcustomedio' => (float)($d['valorcustomediounitario'] ?? 0),
                ];

                $valorTotalItens += $valTotal;
                $pesoBrutoTotal += $pBruto;
                $pesoLiquidoTotal += $pLiquido;
            }

            // Gera sequencial oficial
            try {
                $idPedidoPda = (int)$this->pdo->query("SELECT nextval('gi_palmtop_pedido')")->fetchColumn();
            } catch (\Throwable $e) {
                $this->pdo->exec("CREATE SEQUENCE IF NOT EXISTS gi_palmtop_pedido START 1");
                $idPedidoPda = (int)$this->pdo->query("SELECT nextval('gi_palmtop_pedido')")->fetchColumn();
            }

            $nomeCliente = 'AZ PETS CO';
            // Grava apenas o que o usuário digitar; se não digitar nada, grava vazio
            $observacaoFinal = $observacaoUsuario !== '' ? $observacaoUsuario : '';
            $dataAtual = date('Y-m-d');
            $dataHoraAtual = date('Y-m-d H:i:s');
            $dataEntrega = date('Y-m-d', strtotime('+30 days'));

            $this->pdo->beginTransaction();

            // Insere na tabela palmtop_pedido
            $sqlPed = "
                INSERT INTO public.palmtop_pedido (
                    idpedidopda, idempresa, idfilial, idimportacao, idcliente,
                    idcondicao, idmetodo, idtabela, idtransacao, idvendrepre,
                    tipofrete, situacao, valorfrete, valoritens, valorservico,
                    valordesconto, valoripi, valorentrada, valortotal,
                    perc_margem, pesoliquido, pesobruto, fixavencimento,
                    uf_origem, uf_destino, hotsync, nomecliente, usuario,
                    dataenvio, data, dataentrega, datahorapda, datahora,
                    importado, idserie, status, origem, numero,
                    tipovendrepre, observacao, idorigem
                ) VALUES (
                    :idpedidopda, 1, :idfilial, :idimportacao, :idcliente,
                    :idcondicao, :idmetodo, :idtabela, :idtransacao, :idvendrepre,
                    0, 1, 0, :valoritens, 0,
                    0, 0, 0, :valortotal,
                    0, :pesoliquido, :pesobruto, 'N',
                    'SC', 'SC', 'S', :nomecliente, :usuario,
                    :dataenvio, :data, :dataentrega, :datahorapda, :datahora,
                    'N', :idserie, 1, 1, '0',
                    :tipovendrepre, :observacao, 0
                )
            ";

            $stmtPed = $this->pdo->prepare($sqlPed);
            $stmtPed->execute([
                'idpedidopda' => $idPedidoPda,
                'idfilial' => self::FILIAL_ID,
                'idimportacao' => $idPedidoPda,
                'idcliente' => self::CLIENTE_ID,
                'idcondicao' => $idCondicaoFinal,
                'idmetodo' => $idMetodoFinal,
                'idtabela' => self::TABELA_ID,
                'idtransacao' => self::TRANSACAO_ID,
                'idvendrepre' => self::VENDEDOR_ID,
                'valoritens' => $valorTotalItens,
                'valortotal' => $valorTotalItens,
                'pesoliquido' => $pesoLiquidoTotal,
                'pesobruto' => $pesoBrutoTotal,
                'nomecliente' => $nomeCliente,
                'usuario' => $usuario['username'],
                'dataenvio' => $dataAtual,
                'data' => $dataAtual,
                'dataentrega' => $dataEntrega,
                'datahorapda' => $dataHoraAtual,
                'datahora' => $dataHoraAtual,
                'idserie' => self::SERIE_TRANSACAO,
                'tipovendrepre' => self::TIPO_VENDREPRE,
                'observacao' => mb_substr($observacaoFinal, 0, 1000),
            ]);

            // Insere os itens na palmtop_pedido_item
            $sqlItem = "
                INSERT INTO public.palmtop_pedido_item (
                    idimportacao, sequencial, idpedidopda, idpedidoitem, iditem,
                    idunidade, qt, valor, valoripi, valortotal,
                    valorcusto, valordesconto, valorcomissao, valorpauta,
                    perc_desconto, perc_ipi, perc_comissao, perc_margem,
                    complemento, descricao, codigolido, perc_margem_cm,
                    perc_margem_cg, valorcustogerencial, valorcustomedio,
                    valorsugerido, idunidade_impressao, quant_impressao,
                    valorunitarioimpressao, negritopromocao, valoracrescimo,
                    perc_acrescimo, percacrescimopolitica, percdescontopolitica,
                    valorprecovendapolitica, valorfrete, iditemcarrinho,
                    iditemgarantia, percdescontopoliticavalorunit,
                    percacrescimopoliticavalorunit, quant_reserva,
                    idtabelapreco, percprice, valorprice,
                    valorpromocaocondicaozero, valorpromocaocondicao,
                    corpromocao, idpedidocompranf_e, iditempedidocompranf_e
                ) VALUES (
                    :idimportacao, :sequencial, :idpedidopda, 0, :iditem,
                    :idunidade, :qt, :valor, 0, :valortotal,
                    :valorcusto, 0, 0, 0,
                    0, :perc_ipi, :perc_comissao, :perc_margem,
                    :complemento, :descricao, :codigolido, 0,
                    0, :valorcustogerencial, :valorcustomedio,
                    :valorsugerido, :idunidade_impressao, :quant_impressao,
                    :valorunitarioimpressao, 'N', 0,
                    0, 0, 0,
                    0, 0, 0,
                    0, 0,
                    0, :quant_reserva,
                    :idtabelapreco, 0, 0,
                    0, 0,
                    '.', '.', 0
                )
            ";

            $stmtItem = $this->pdo->prepare($sqlItem);

            $seq = 1;
            foreach ($itensProcessados as $it) {
                $stmtItem->execute([
                    'idimportacao' => $idPedidoPda,
                    'sequencial' => $seq++,
                    'idpedidopda' => $idPedidoPda,
                    'iditem' => $it['iditem'],
                    'idunidade' => $it['idunidade'],
                    'qt' => $it['quantidade'],
                    'valor' => $it['valor_unitario'],
                    'valortotal' => $it['valor_total'],
                    'valorcusto' => $it['valorcustocontabil'],
                    'perc_ipi' => $it['perc_ipi'],
                    'perc_comissao' => $it['perc_comissao'],
                    'perc_margem' => $it['perc_margem'],
                    'complemento' => mb_substr($it['complemento'], 0, 100),
                    'descricao' => mb_substr($it['descricao'], 0, 80),
                    'codigolido' => mb_substr($it['referencia'], 0, 30),
                    'valorcustogerencial' => $it['valorcustogerencial'],
                    'valorcustomedio' => $it['valorcustomedio'],
                    'valorsugerido' => $it['valor_unitario'],
                    'idunidade_impressao' => $it['idunidade'],
                    'quant_impressao' => $it['quantidade'],
                    'valorunitarioimpressao' => $it['valor_unitario'],
                    'quant_reserva' => $it['quantidade'],
                    'idtabelapreco' => self::TABELA_ID,
                ]);
            }

            $this->pdo->commit();

            return $this->json($response, [
                'success' => true,
                'message' => "Pedido de E-commerce #{$idPedidoPda} criado com sucesso!",
                'idpedido' => $idPedidoPda,
                'detalhes' => [
                    'cliente' => $nomeCliente,
                    'itens_count' => count($itensProcessados),
                    'total_unidades' => array_sum(array_column($itensProcessados, 'quantidade')),
                    'valor_total' => $valorTotalItens,
                    'observacao' => $observacaoUsuario
                ]
            ], 201);
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('[FluxoEcomerce] criar: ' . $e->getMessage());
            return $this->json($response, [
                'success' => false,
                'error' => 'Erro ao processar e salvar pedido: ' . $e->getMessage()
            ], 500);
        }
    }
}
