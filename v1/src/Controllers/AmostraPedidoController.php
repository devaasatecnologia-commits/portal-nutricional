<?php
namespace Nutricional\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PDO;
use Nutricional\Services\Frota\ERPPedidoService;

/**
 * Pedido de amostra/brinde criado pelo gestor.
 *
 * Regras:
 *  - o usuario so opera nas filiais de usuario.dash_filiais (admin: filiais 1 e 6);
 *  - so pode usar as transacoes da view transacao_pedido;
 *  - itens e saldo vem da view amostra_brindes_com_saldo
 *    (idimposto 4 para a transacao 8, 6 para as demais; filial 6 ou 1).
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
        $idusuario = (int)($user['idusuario'] ?? 0);
        $isAdmin = (bool)($user['is_admin'] ?? false) || in_array('admin', $user['permissoes'] ?? [], true);

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
            // vazio = sem restricao de gestor (mesmo criterio dos demais modulos)
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

    private static function filialDaView(int $idfilial): int
    {
        return $idfilial === 6 ? 6 : 1;
    }

    private static function impostoDaTransacao(int $idtransacao): int
    {
        return $idtransacao === 8 ? 4 : 6;
    }

    private function itensDisponiveis(int $idtransacao, int $idfilial, string $busca = '', array $iditens = []): array
    {
        $sql = "SELECT iditem,
                       split_part(produto_qt_estoque, ' - Qt. Estoque:', 1) AS descricao,
                       quantidade_estoque, saldo_estoque
                FROM amostra_brindes_com_saldo
                WHERE idimposto = :imposto AND filial = :filial AND saldo_estoque <> 0";
        $params = [
            'imposto' => self::impostoDaTransacao($idtransacao),
            'filial' => self::filialDaView($idfilial),
        ];

        if ($iditens !== []) {
            $sql .= ' AND iditem IN (' . implode(',', array_map('intval', $iditens)) . ')';
        }
        if ($busca !== '') {
            $sql .= ' AND (iditem::text = :exato OR produto_qt_estoque ILIKE :parcial)';
            $params['exato'] = $busca;
            $params['parcial'] = '%' . $busca . '%';
        }
        $sql .= ' ORDER BY 2 LIMIT 300';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(static fn (array $r): array => [
            'iditem' => (int)$r['iditem'],
            'descricao' => trim((string)$r['descricao']),
            'quantidade_estoque' => (float)$r['quantidade_estoque'],
            'saldo_estoque' => (float)$r['saldo_estoque'],
        ], $rows);
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

            return $this->json($response, [
                'success' => true,
                'usuario' => $usuario['username'],
                'filiais' => $filiais,
                'transacoes' => $this->transacoes(),
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
                SELECT c.idcliforemp, c.fantasia, c.razao
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
                return $this->json($response, ['success' => true, 'pedidos' => [], 'pendentes' => []]);
            }
            $filiais = implode(',', $usuario['filiais']);
            $transacoes = implode(',', self::TRANSACOES_LISTA);
            $excluidos = implode(',', self::ITENS_FORA_DA_LISTA);

            $pedidos = $this->pdo->query("
                SELECT pedido.data,
                       pedido.idpedido,
                       cli.fantasia,
                       STRING_AGG(CONCAT(CAST(pedido_item.qt AS INTEGER), ' - ', item.descricao), CHR(10)) AS itens,
                       pedido.observacao,
                       pp.status AS status_palmtop
                FROM pedido_item
                JOIN pedido ON pedido.idpedido = pedido_item.idpedido
                LEFT JOIN palmtop_pedido pp ON pp.idpedidopda = pedido.idpedidopda
                JOIN item ON item.iditem = pedido_item.iditem
                JOIN cliforemp cli ON cli.idcliforemp = pedido.idcliforemp
                JOIN cliente ON cliente.idcliforemp = cli.idcliforemp
                WHERE item.inativo = 'N'
                  AND item.iditem NOT IN ($excluidos)
                  AND pedido.idtransacao IN ($transacoes)
                  AND cliente.idtipocliente = 5
                  AND pedido.status IN (1, 5)
                  AND pedido.idfilial IN ($filiais)
                  AND " . $this->clienteWhere($usuario['gestores'], 'cli') . "
                GROUP BY pedido.data, pedido.idpedido, cli.fantasia, pedido.observacao, pp.status
                ORDER BY pedido.idpedido DESC
                LIMIT 100
            ")->fetchAll(PDO::FETCH_ASSOC);

            // Pedidos criados pelo portal que o ERP ainda não importou (inclui cancelados no palmtop)
            $pendentes = $this->pdo->query("
                SELECT pp.data, pp.idpedidopda AS idpedido, pp.nomecliente AS fantasia,
                       STRING_AGG(CONCAT(CAST(ppi.qt AS INTEGER), ' - ', ppi.descricao), CHR(10)) AS itens,
                       pp.observacao,
                       pp.status AS status_palmtop
                FROM palmtop_pedido pp
                JOIN palmtop_pedido_item ppi ON ppi.idpedidopda = pp.idpedidopda
                WHERE pp.importado = 'N'
                  AND pp.idtransacao IN ($transacoes)
                  AND pp.idfilial IN ($filiais)
                  AND pp.data >= CURRENT_DATE - INTERVAL '7 days'
                GROUP BY pp.data, pp.idpedidopda, pp.nomecliente, pp.observacao, pp.status
                ORDER BY pp.idpedidopda DESC
                LIMIT 50
            ")->fetchAll(PDO::FETCH_ASSOC);

            $marcar = static function (array $rows): array {
                foreach ($rows as &$r) {
                    $r['cancelado'] = (int)($r['status_palmtop'] ?? 0) === self::PALMTOP_STATUS_CANCELADO;
                }
                unset($r);
                return $rows;
            };

            return $this->json($response, [
                'success' => true,
                'pedidos' => $marcar($pedidos),
                'pendentes' => $marcar($pendentes),
            ]);
        } catch (\Throwable $e) {
            error_log('[Amostras] pedidos: ' . $e->getMessage());
            return $this->json($response, ['success' => false, 'error' => 'Erro ao listar pedidos'], 500);
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
                if ($qt > $d['quantidade_estoque']) {
                    return $this->json($response, [
                        'success' => false,
                        'error' => "A quantidade {$qt} do item {$d['descricao']} é maior que o estoque disponível: {$d['quantidade_estoque']}.",
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
