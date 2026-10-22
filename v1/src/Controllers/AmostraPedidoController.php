<?php
namespace Nutricional\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PDO;
use Nutricional\Services\Frota\ERPPedidoService;

/**
 * Pedido de amostra / material de apoio criado pelo gestor.
 *
 * Regras:
 *  - O usuário só opera nas filiais de usuario.dash_filiais (admin: filiais 1 e 6);
 *  - Só pode usar as transações da view transacao_pedido (ex: 8 e 10);
 *  - Materiais e estoque são consultados na Matriz 1 (view amostra_brindes_com_saldo com filial 1);
 *  - Para a Filial 6 (PR), aplica-se a retenção configurada na administração
 *    (padrão: 30% liberado / -70% retido, ou regra customizada por item em amostra_estoque_regra_item);
 *  - Rastreamento completo de solicitante e origem (Portal vs ERP).
 */
class AmostraPedidoController
{
    private const FILIAIS_ADMIN = [1, 6];
    private const TRANSACOES_LISTA = [8, 10];
    private const ITENS_FORA_DA_LISTA = [1134, 2181, 1617];
    private const PALMTOP_STATUS_CANCELADO = 2;

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

        $ints = static fn (string $csv): array => array_values(array_unique(array_filter(
            array_map('intval', explode(',', $csv)),
            static fn (int $f): bool => $f > 0
        )));
        $filiais = $ints((string)($row['dash_filiais'] ?? ''));
        if ($filiais === [] && $isAdmin) {
            $filiais = self::FILIAIS_ADMIN;
        }

        return [
            'idusuario' => $idusuario,
            'username' => (string)($row['username'] ?? ($user['username'] ?? '')),
            'filiais' => $filiais,
            'gestores' => $isAdmin ? [] : $ints((string)($row['dash_gestores'] ?? '')),
        ];
    }

    /** Cliente tipo 5 e, se o usuario tem gestores, com pedidos de algum deles. */
    private function clienteWhere(array $gestores, string $alias = 'c'): string
    {
        $sql = "EXISTS (SELECT 1 FROM cliente tc WHERE tc.idcliforemp = $alias.idcliforemp AND tc.idtipocliente = 5)";
        if ($gestores !== []) {
            $sql .= " AND EXISTS (SELECT 1 FROM pedido pg WHERE pg.idcliforemp = $alias.idcliforemp AND pg.idsupervisor IN ("
                . implode(',', $gestores) . '))';
        }
        return $sql;
    }

    private function transacoes(): array
    {
        $rows = $this->pdo->query('SELECT codigo, nome FROM transacao_pedido ORDER BY codigo')->fetchAll(PDO::FETCH_ASSOC);
        return array_map(static fn (array $r): array => ['codigo' => (int)$r['codigo'], 'nome' => $r['nome']], $rows);
    }

    private static function impostoDaTransacao(int $idtransacao): int
    {
        return $idtransacao === 8 ? 4 : 6;
    }

    private function processarImagem(?string $path_foto_master): string
    {
        $sem = 'https://placehold.co/100x100?text=S/F';
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

    private function carregarConfigAmostra(): array
    {
        try {
            $stmt = $this->pdo->query('
                SELECT percentual_padrao 
                FROM amostra_estoque_config 
                WHERE filial_origem = 1 AND filial_destino = 6 
                LIMIT 1
            ');
            $percPadrao = (float)($stmt->fetchColumn() ?: 30.00);

            $stmtRegras = $this->pdo->query('
                SELECT iditem, percentual_liberado 
                FROM amostra_estoque_regra_item 
                WHERE filial_destino = 6 AND ativo = true
            ');
            $regras = [];
            while ($r = $stmtRegras->fetch(PDO::FETCH_ASSOC)) {
                $regras[(int)$r['iditem']] = (float)$r['percentual_liberado'];
            }

            return [
                'percentual_padrao' => $percPadrao,
                'regras_itens' => $regras,
            ];
        } catch (\Throwable $e) {
            error_log('[Amostras] erro carregar config: ' . $e->getMessage());
            return [
                'percentual_padrao' => 30.00,
                'regras_itens' => [],
            ];
        }
    }

    private function itensDisponiveis(int $idtransacao, int $idfilial, string $busca = '', array $iditens = []): array
    {
        $sql = "SELECT a.iditem,
                       a.referencia,
                       a.descricao,
                       a.codigobarra,
                       a.saldo_estoque AS saldo_matriz,
                       i.path_foto_master
                FROM amostra_brindes_com_saldo a
                JOIN item i ON i.iditem = a.iditem
                WHERE a.idimposto = :imposto 
                  AND a.filial = 1 
                  AND a.saldo_estoque > 0";
        $params = [
            'imposto' => self::impostoDaTransacao($idtransacao),
        ];

        if ($iditens !== []) {
            $sql .= ' AND a.iditem IN (' . implode(',', array_map('intval', $iditens)) . ')';
        }
        if ($busca !== '') {
            $sql .= ' AND (a.iditem::text = :exato OR a.referencia ILIKE :parcial OR a.descricao ILIKE :parcial)';
            $params['exato'] = $busca;
            $params['parcial'] = '%' . $busca . '%';
        }
        $sql .= ' ORDER BY a.descricao LIMIT 300';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $config = $this->carregarConfigAmostra();
        $percPadrao = $config['percentual_padrao'];
        $regras = $config['regras_itens'];

        $itens = [];
        foreach ($rows as $r) {
            $id = (int)$r['iditem'];
            $saldoMatriz = (float)$r['saldo_matriz'];

            if ($idfilial === 6) {
                $isCustom = isset($regras[$id]);
                $perc = $isCustom ? $regras[$id] : $percPadrao;
                $saldoLiberado = (float)floor($saldoMatriz * ($perc / 100.0));
            } else {
                $isCustom = false;
                $perc = 100.00;
                $saldoLiberado = $saldoMatriz;
            }

            // Se a busca não for por itens específicos e o saldo liberado for 0, omitir da listagem para agilidade
            if ($iditens === [] && $saldoLiberado <= 0) {
                continue;
            }

            $itens[] = [
                'iditem' => $id,
                'referencia' => trim((string)$r['referencia']),
                'descricao' => trim((string)$r['descricao']),
                'codigobarra' => trim((string)$r['codigobarra']),
                'saldo_matriz' => $saldoMatriz,
                'saldo_estoque' => $saldoLiberado,
                'quantidade_estoque' => $saldoLiberado,
                'percentual_liberado' => $perc,
                'is_regra_custom' => $isCustom,
                'foto' => $r['path_foto_master'],
                'foto_url' => $this->processarImagem($r['path_foto_master'] ?? null),
            ];
        }

        return $itens;
    }

    /** GET /v1/amostras/contexto */
    public function contexto(Request $request, Response $response): Response
    {
        try {
            $usuario = $this->usuario($request);
            $filiais = [];
            if ($usuario['filiais'] !== []) {
                $in = implode(',', $usuario['filiais']);
                $filiais = $this->pdo->query("SELECT idfilial, razao FROM filial WHERE idfilial IN ($in) ORDER BY idfilial")
                    ->fetchAll(PDO::FETCH_ASSOC);
            }

            $config = $this->carregarConfigAmostra();

            return $this->json($response, [
                'success' => true,
                'usuario' => $usuario['username'],
                'filiais' => $filiais,
                'transacoes' => $this->transacoes(),
                'config_filial_pr' => [
                    'percentual_padrao' => $config['percentual_padrao'],
                    'regras_especificas_ativas' => count($config['regras_itens']),
                ],
            ]);
        } catch (\Throwable $e) {
            error_log('[Amostras] contexto: ' . $e->getMessage());
            return $this->json($response, ['success' => false, 'error' => 'Erro ao carregar o contexto'], 500);
        }
    }

    /** GET /v1/amostras/itens?idtransacao=&idfilial=&q= */
    public function itens(Request $request, Response $response): Response
    {
        $p = $request->getQueryParams();
        $idtransacao = (int)($p['idtransacao'] ?? 0);
        $idfilial = (int)($p['idfilial'] ?? 0);

        try {
            $usuario = $this->usuario($request);
            if (!in_array($idfilial, $usuario['filiais'], true)) {
                return $this->json($response, ['success' => false, 'error' => 'Filial não permitida para o seu usuário'], 403);
            }
            if (!in_array($idtransacao, array_column($this->transacoes(), 'codigo'), true)) {
                return $this->json($response, ['success' => false, 'error' => 'Transação não permitida'], 403);
            }

            return $this->json($response, [
                'success' => true,
                'itens' => $this->itensDisponiveis($idtransacao, $idfilial, trim((string)($p['q'] ?? ''))),
            ]);
        } catch (\Throwable $e) {
            error_log('[Amostras] itens: ' . $e->getMessage());
            return $this->json($response, ['success' => false, 'error' => 'Erro ao buscar itens'], 500);
        }
    }

    /** GET /v1/amostras/clientes?q= */
    public function clientes(Request $request, Response $response): Response
    {
        $q = trim((string)($request->getQueryParams()['q'] ?? ''));
        if (mb_strlen($q) < 2) {
            return $this->json($response, ['success' => true, 'clientes' => []]);
        }

        try {
            $usuario = $this->usuario($request);
            $stmt = $this->pdo->prepare("
                SELECT c.idcliforemp, c.fantasia, c.razao, c.cnpj
                FROM cliforemp c
                WHERE (c.idcliforemp::text = :exato
                   OR c.cnpj = :exato
                   OR c.fantasia ILIKE :parcial
                   OR c.razao ILIKE :parcial)
                  AND " . $this->clienteWhere($usuario['gestores']) . "
                ORDER BY (c.idcliforemp::text = :exato) DESC, c.fantasia
                LIMIT 20");
            $stmt->execute(['exato' => $q, 'parcial' => '%' . $q . '%']);

            return $this->json($response, ['success' => true, 'clientes' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (\Throwable $e) {
            error_log('[Amostras] clientes: ' . $e->getMessage());
            return $this->json($response, ['success' => false, 'error' => 'Erro ao buscar clientes'], 500);
        }
    }

    /** GET /v1/amostras/pedidos */
    public function pedidos(Request $request, Response $response): Response
    {
        try {
            $usuario = $this->usuario($request);
            if ($usuario['filiais'] === []) {
                return $this->json($response, ['success' => true, 'pedidos' => [], 'pendentes' => [], 'metricas' => []]);
            }
            $filiais = implode(',', $usuario['filiais']);
            $transacoes = implode(',', self::TRANSACOES_LISTA);
            $excluidos = implode(',', self::ITENS_FORA_DA_LISTA);

            $pedidos = $this->pdo->query("
                SELECT pedido.data,
                       pedido.idpedido,
                       pedido.idfilial,
                       fil.razao as filial_nome,
                       cli.idcliforemp as idcliente,
                       cli.fantasia as fantasia,
                       cli.razao as razao_social,
                       sup.fantasia as supervisor_nome,
                       COALESCE(pp.usuario, pedido.usuario, 'ERP') as solicitante,
                       CASE 
                           WHEN COALESCE(pedido.idpedidopda, pp.idpedidopda, 0) > 0 THEN 'PORTAL' 
                           ELSE 'ERP' 
                       END as origem,
                       pedido.observacao,
                       pedido.status as status_pedido,
                       pedido.valortotalpedido,
                       STRING_AGG(CONCAT(CAST(pedido_item.qt AS INTEGER), 'x ', item.descricao), CHR(10) ORDER BY item.descricao) AS itens,
                       COUNT(pedido_item.iditem) as total_itens,
                       SUM(pedido_item.qt) as total_unidades
                FROM pedido
                JOIN filial fil ON fil.idfilial = pedido.idfilial
                JOIN cliforemp cli ON cli.idcliforemp = pedido.idcliforemp
                JOIN cliente ON cliente.idcliforemp = cli.idcliforemp
                LEFT JOIN cliforemp sup ON sup.idcliforemp = pedido.idsupervisor
                LEFT JOIN palmtop_pedido pp ON pp.idpedidopda = pedido.idpedidopda
                JOIN pedido_item ON pedido_item.idpedido = pedido.idpedido
                JOIN item ON item.iditem = pedido_item.iditem
                WHERE item.inativo = 'N'
                  AND item.iditem NOT IN ($excluidos)
                  AND pedido.idtransacao IN ($transacoes)
                  AND cliente.idtipocliente = 5
                  AND pedido.status IN (1, 5)
                  AND COALESCE(pp.status, 0) <> " . self::PALMTOP_STATUS_CANCELADO . "
                  AND pedido.idfilial IN ($filiais)
                  AND " . $this->clienteWhere($usuario['gestores'], 'cli') . "
                GROUP BY pedido.data, pedido.idpedido, pedido.idfilial, fil.razao, cli.idcliforemp, cli.fantasia, cli.razao, sup.fantasia,
                         pp.usuario, pedido.usuario, pedido.idpedidopda, pp.idpedidopda, pedido.observacao, pedido.status, pedido.valortotalpedido
                ORDER BY pedido.idpedido DESC
                LIMIT 120
            ")->fetchAll(PDO::FETCH_ASSOC);

            // Criados pelo portal e ainda não importados pelo ERP (cancelados no palmtop ficam de fora)
            $pendentes = $this->pdo->query("
                SELECT pp.data,
                       pp.idpedidopda AS idpedido,
                       pp.idfilial,
                       fil.razao as filial_nome,
                       pp.nomecliente AS fantasia,
                       COALESCE(pp.usuario, 'PORTAL') as solicitante,
                       'PORTAL' as origem,
                       pp.observacao,
                       0 as status_pedido,
                       TRUE as pendente,
                       STRING_AGG(CONCAT(CAST(ppi.qt AS INTEGER), 'x ', ppi.descricao), CHR(10) ORDER BY ppi.descricao) AS itens,
                       COUNT(ppi.iditem) as total_itens,
                       SUM(ppi.qt) as total_unidades
                FROM palmtop_pedido pp
                JOIN filial fil ON fil.idfilial = pp.idfilial
                JOIN palmtop_pedido_item ppi ON ppi.idpedidopda = pp.idpedidopda
                WHERE pp.importado = 'N'
                  AND pp.status <> " . self::PALMTOP_STATUS_CANCELADO . "
                  AND pp.idtransacao IN ($transacoes)
                  AND pp.idfilial IN ($filiais)
                  AND pp.data >= CURRENT_DATE - INTERVAL '15 days'
                GROUP BY pp.data, pp.idpedidopda, pp.idfilial, fil.razao, pp.nomecliente, pp.usuario, pp.observacao
                ORDER BY pp.idpedidopda DESC
                LIMIT 50
            ")->fetchAll(PDO::FETCH_ASSOC);

            // KPIs
            $totalPendentes = count($pendentes);
            $totalAbertos = 0;
            $totalFaturados = 0;
            $origemPortal = $totalPendentes;
            $origemErp = 0;
            $totalUnidades = 0;

            foreach ($pedidos as $p) {
                if ((int)$p['status_pedido'] === 5) {
                    $totalFaturados++;
                } else {
                    $totalAbertos++;
                }
                if ($p['origem'] === 'PORTAL') {
                    $origemPortal++;
                } else {
                    $origemErp++;
                }
                $totalUnidades += (float)($p['total_unidades'] ?? 0);
            }
            foreach ($pendentes as $pen) {
                $totalUnidades += (float)($pen['total_unidades'] ?? 0);
            }

            $metricas = [
                'total_pedidos' => count($pedidos) + $totalPendentes,
                'total_pendentes' => $totalPendentes,
                'total_abertos' => $totalAbertos,
                'total_faturados' => $totalFaturados,
                'origem_portal' => $origemPortal,
                'origem_erp' => $origemErp,
                'total_unidades' => (int)$totalUnidades,
            ];

            return $this->json($response, [
                'success' => true,
                'pedidos' => $pedidos,
                'pendentes' => $pendentes,
                'metricas' => $metricas,
            ]);
        } catch (\Throwable $e) {
            error_log('[Amostras] pedidos: ' . $e->getMessage());
            return $this->json($response, ['success' => false, 'error' => 'Erro ao listar pedidos'], 500);
        }
    }

    /** GET /v1/amostras/pedidos/{id} */
    public function detalhesPedido(Request $request, Response $response, array $args): Response
    {
        $id = (int)($args['id'] ?? 0);
        if ($id <= 0) {
            return $this->json($response, ['success' => false, 'error' => 'Pedido inválido'], 400);
        }

        try {
            $usuario = $this->usuario($request);
            $filiais = implode(',', $usuario['filiais'] ?: self::FILIAIS_ADMIN);

            // Tenta buscar no pedido ERP
            $stmt = $this->pdo->prepare("
                SELECT p.idpedido, p.data, p.idfilial, fil.razao as filial_nome,
                       cli.idcliforemp as idcliente, cli.fantasia, cli.razao,
                       sup.fantasia as supervisor_nome,
                       COALESCE(pp.usuario, p.usuario, 'ERP') as solicitante,
                       CASE WHEN COALESCE(p.idpedidopda, pp.idpedidopda, 0) > 0 THEN 'PORTAL' ELSE 'ERP' END as origem,
                       p.observacao, p.status as status_pedido, p.valortotalpedido
                FROM pedido p
                JOIN filial fil ON fil.idfilial = p.idfilial
                JOIN cliforemp cli ON cli.idcliforemp = p.idcliforemp
                LEFT JOIN cliforemp sup ON sup.idcliforemp = p.idsupervisor
                LEFT JOIN palmtop_pedido pp ON pp.idpedidopda = p.idpedidopda
                WHERE p.idpedido = :id AND p.idfilial IN ($filiais)
            ");
            $stmt->execute(['id' => $id]);
            $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($pedido) {
                $stmtItens = $this->pdo->prepare("
                    SELECT pi.iditem, i.descricao, i.referencia, pi.qt,
                           pi.vlrunitario, pi.valortotalitem, i.path_foto_master
                    FROM pedido_item pi
                    JOIN item i ON i.iditem = pi.iditem
                    WHERE pi.idpedido = :id
                    ORDER BY i.descricao
                ");
                $stmtItens->execute(['id' => $id]);
                $itens = $stmtItens->fetchAll(PDO::FETCH_ASSOC);
                foreach ($itens as &$it) {
                    $it['foto_url'] = $this->processarImagem($it['path_foto_master'] ?? null);
                }
                $pedido['itens'] = $itens;
                return $this->json($response, ['success' => true, 'pedido' => $pedido]);
            }

            // Se não encontrou no ERP, busca nos pendentes do palmtop
            $stmtPp = $this->pdo->prepare("
                SELECT pp.idpedidopda as idpedido, pp.data, pp.idfilial, fil.razao as filial_nome,
                       pp.idcliente, pp.nomecliente as fantasia,
                       pp.usuario as solicitante, 'PORTAL' as origem,
                       pp.observacao, 0 as status_pedido, TRUE as pendente
                FROM palmtop_pedido pp
                JOIN filial fil ON fil.idfilial = pp.idfilial
                WHERE pp.idpedidopda = :id AND pp.idfilial IN ($filiais)
            ");
            $stmtPp->execute(['id' => $id]);
            $pedidoPp = $stmtPp->fetch(PDO::FETCH_ASSOC);

            if ($pedidoPp) {
                $stmtItensPp = $this->pdo->prepare("
                    SELECT ppi.iditem, ppi.descricao, i.referencia, ppi.qt,
                           ppi.vlrunitario, (ppi.qt * ppi.vlrunitario) as valortotalitem, i.path_foto_master
                    FROM palmtop_pedido_item ppi
                    LEFT JOIN item i ON i.iditem = ppi.iditem
                    WHERE ppi.idpedidopda = :id
                    ORDER BY ppi.descricao
                ");
                $stmtItensPp->execute(['id' => $id]);
                $itensPp = $stmtItensPp->fetchAll(PDO::FETCH_ASSOC);
                foreach ($itensPp as &$it) {
                    $it['foto_url'] = $this->processarImagem($it['path_foto_master'] ?? null);
                }
                $pedidoPp['itens'] = $itensPp;
                return $this->json($response, ['success' => true, 'pedido' => $pedidoPp]);
            }

            return $this->json($response, ['success' => false, 'error' => 'Pedido não encontrado'], 404);
        } catch (\Throwable $e) {
            error_log('[Amostras] detalhesPedido: ' . $e->getMessage());
            return $this->json($response, ['success' => false, 'error' => 'Erro ao carregar detalhes'], 500);
        }
    }

    /** POST /v1/amostras/pedidos */
    public function criar(Request $request, Response $response): Response
    {
        $in = json_decode((string)$request->getBody(), true);
        if (!is_array($in)) {
            return $this->json($response, ['success' => false, 'error' => 'Dados inválidos'], 400);
        }

        $idfilial = (int)($in['idfilial'] ?? 0);
        $idtransacao = (int)($in['idtransacao'] ?? 0);
        $idcliente = (int)($in['idcliente'] ?? 0);
        $observacao = trim((string)($in['observacao'] ?? ''));

        $quantidades = [];
        foreach ((array)($in['itens'] ?? []) as $item) {
            $iditem = (int)($item['iditem'] ?? 0);
            $qt = (float)($item['quantidade'] ?? 0);
            if ($iditem <= 0 || $qt <= 0) {
                return $this->json($response, ['success' => false, 'error' => 'Item ou quantidade inválidos'], 422);
            }
            $quantidades[$iditem] = ($quantidades[$iditem] ?? 0) + $qt;
        }

        if ($idfilial <= 0 || $idtransacao <= 0 || $idcliente <= 0 || $quantidades === []) {
            return $this->json($response, ['success' => false, 'error' => 'Sem dados para enviar, favor revisar.'], 422);
        }
        if ($observacao === '') {
            return $this->json($response, ['success' => false, 'error' => 'A observação é obrigatória.'], 422);
        }
        if (mb_strlen($observacao) > 500) {
            return $this->json($response, ['success' => false, 'error' => 'Observação deve ter até 500 caracteres'], 422);
        }

        try {
            $usuario = $this->usuario($request);
            if (!in_array($idfilial, $usuario['filiais'], true)) {
                return $this->json($response, ['success' => false, 'error' => 'Filial não permitida para o seu usuário'], 403);
            }
            if (!in_array($idtransacao, array_column($this->transacoes(), 'codigo'), true)) {
                return $this->json($response, ['success' => false, 'error' => 'Transação não permitida'], 403);
            }

            $clienteOk = $this->pdo->prepare('SELECT 1 FROM cliforemp c WHERE c.idcliforemp = :id AND ' . $this->clienteWhere($usuario['gestores']));
            $clienteOk->execute(['id' => $idcliente]);
            if (!$clienteOk->fetchColumn()) {
                return $this->json($response, ['success' => false, 'error' => 'Cliente não permitido para o seu usuário'], 403);
            }

            $disponiveis = [];
            foreach ($this->itensDisponiveis($idtransacao, $idfilial, '', array_keys($quantidades)) as $d) {
                $disponiveis[$d['iditem']] = $d;
            }

            $itens = [];
            foreach ($quantidades as $iditem => $qt) {
                $d = $disponiveis[$iditem] ?? null;
                if ($d === null) {
                    return $this->json($response, ['success' => false, 'error' => "Item $iditem sem saldo disponível para esta transação/filial."], 422);
                }
                if ($qt > $d['saldo_estoque']) {
                    return $this->json($response, [
                        'success' => false,
                        'error' => "A quantidade {$qt} do item {$d['descricao']} é maior que o saldo liberado: {$d['saldo_estoque']}.",
                    ], 422);
                }
                $itens[] = ['iditem' => $iditem, 'quantidade' => $qt];
            }

            try {
                $seq = (int)$this->pdo->query("SELECT nextval('pedido_portal')")->fetchColumn();
                $nomeCliente = $seq > 0 ? "PORTAL[$seq]" : 'PORTAL';
            } catch (\Throwable $e) {
                $nomeCliente = 'PORTAL';
            }

            $resultado = (new ERPPedidoService($this->pdo))->criarPedidoERP([
                'idfilial' => $idfilial,
                'idtransacao' => $idtransacao,
                'idcliente' => $idcliente,
                'itens' => $itens,
                'usuario' => $usuario['username'],
                'nomecliente' => $nomeCliente,
                'observacao' => $observacao,
                'observacao_livre' => true,
                'ignorar_saldo_lote' => true,
            ]);

            if (empty($resultado['success'])) {
                return $this->json($response, ['success' => false, 'error' => $resultado['message'] ?? 'Falha ao criar o pedido'], 422);
            }

            error_log(sprintf('[Amostras] usuario=%d filial=%d transacao=%d cliente=%d itens=%d sandbox=%s',
                $usuario['idusuario'], $idfilial, $idtransacao, $idcliente, count($itens), !empty($resultado['sandbox']) ? 'S' : 'N'));

            $sandbox = !empty($resultado['sandbox']);
            $out = [
                'success' => true,
                'sandbox' => $sandbox,
                'message' => $sandbox
                    ? 'MODO SANDBOX: pedido validado, nada foi gravado no ERP.'
                    : 'Pedido ' . ($resultado['data']['idpedidopda'] ?? '') . ' criado com sucesso!',
                'idpedido' => $resultado['data']['idpedidopda'] ?? null,
            ];
            if ($sandbox) {
                $out['sql'] = $resultado['sql'] ?? null;
            }
            return $this->json($response, $out, 201);
        } catch (\Throwable $e) {
            error_log('[Amostras] criar: ' . $e->getMessage());
            return $this->json($response, ['success' => false, 'error' => 'Erro ao processar o pedido'], 500);
        }
    }
}
