<?php

namespace Nutricional\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PDO;
use Exception;

class ClientePedidosController
{
    private $pdo;
    
    public function __construct()
    {
        $this->pdo = \getPDO();
    }
    
    /**
     * GET /v1/marketing/clientes/{id}/pedidos-erp
     * Busca pedidos do cliente no ERP pelo ID do cliente CRM
     */
    public function getPedidosClienteCRM(Request $request, Response $response, array $args): Response
    {
        $idCrm = (int)($args['id'] ?? 0);
        
        try {
            // Buscar cliente CRM para obter CNPJ/CPF
            $stmt = $this->pdo->prepare("
                SELECT id, nome, empresa, cnpj_cpf, cliforemp_id 
                FROM mkt_clientes 
                WHERE id = :id
            ");
            $stmt->execute(['id' => $idCrm]);
            $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$cliente) {
                return $this->json($response, [
                    'success' => false,
                    'error' => 'Cliente não encontrado'
                ], 404);
            }
            
            // Buscar pedidos pelo CNPJ/CPF ou pelo cliforemp_id
            return $this->buscarPedidosPorCliente($response, $cliente);
            
        } catch (Exception $e) {
            error_log('Erro em getPedidosClienteCRM: ' . $e->getMessage());
            return $this->json($response, [
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * POST /v1/marketing/clientes/buscar-por-cnpj
     * Busca cliente e pedidos pelo CNPJ/CPF
     */
    public function buscarPorCnpj(Request $request, Response $response): Response
    {
        $input = json_decode($request->getBody()->getContents(), true) ?? [];
        $cnpjCpf = trim($input['cnpj_cpf'] ?? '');
        $limite = (int)($input['limite'] ?? 5);
        
        if (empty($cnpjCpf)) {
            return $this->json($response, [
                'success' => false,
                'error' => 'CNPJ/CPF é obrigatório'
            ], 400);
        }
        
        try {
            $cnpjCpf = preg_replace('/[^0-9]/', '', $cnpjCpf);
            
            // Validar CNPJ/CPF
            if (strlen($cnpjCpf) < 11) {
                return $this->json($response, [
                    'success' => false,
                    'error' => 'CNPJ/CPF inválido (mínimo 11 dígitos)'
                ], 400);
            }
            
            // Buscar cliente no ERP
            $stmt = $this->pdo->prepare("
                SELECT 
                    idcliforemp,
                    fantasia,
                    razao,
                    fone,
                    email,
                    cnpj,
                    cpf,
                    ie,
                    endereco,
                    numero,
                    bairro,
                    cep,
                    complemento,
                    uf,
                    inativo,
                    datacadastro,
                    (SELECT descricao FROM cidade WHERE idcidade = cliforemp.idcidade) as cidade,
                    (SELECT fantasia FROM cliforemp WHERE idcliforemp = cliforemp.idvendedor) as nome_vendedor
                FROM cliforemp 
                WHERE REPLACE(REPLACE(REPLACE(cnpj, '.', ''), '/', ''), '-', '') = :cnpj
                OR REPLACE(REPLACE(REPLACE(cpf, '.', ''), '/', ''), '-', '') = :cpf
                LIMIT :limite
            ");
            $stmt->bindParam(':cnpj', $cnpjCpf);
            $stmt->bindParam(':cpf', $cnpjCpf);
            $stmt->bindParam(':limite', $limite, PDO::PARAM_INT);
            $stmt->execute();
            $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($clientes)) {
                return $this->json($response, [
                    'success' => false,
                    'error' => 'Cliente não encontrado no ERP'
                ], 404);
            }
            
            // Se encontrou apenas um, retorna diretamente com pedidos
            if (count($clientes) === 1) {
                $cliente = $clientes[0];
                $pedidos = $this->buscarPedidosPorIdCliforemp($cliente['idcliforemp']);
                
                return $this->json($response, [
                    'success' => true,
                    'cliente' => $cliente,
                    'pedidos' => $pedidos,
                    'total_pedidos' => count($pedidos),
                    'cliforemp_id' => $cliente['idcliforemp']
                ]);
            }
            
            // Se encontrou múltiplos, retorna lista para seleção
            return $this->json($response, [
                'success' => true,
                'multiples' => true,
                'clientes' => $clientes,
                'total_encontrados' => count($clientes),
                'message' => 'Múltiplos clientes encontrados. Selecione um.'
            ]);
            
        } catch (Exception $e) {
            error_log('Erro em buscarPorCnpj: ' . $e->getMessage());
            return $this->json($response, [
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * POST /v1/marketing/clientes/selecionar-erp
     * Seleciona um cliente ERP entre múltiplos encontrados
     */
    public function selecionarClienteERP(Request $request, Response $response): Response
    {
        $input = json_decode($request->getBody()->getContents(), true) ?? [];
        $idErp = (int)($input['id_erp'] ?? 0);
        $idCrm = (int)($input['id_crm'] ?? 0);
        
        if ($idErp <= 0) {
            return $this->json($response, [
                'success' => false,
                'error' => 'ID do cliente ERP é obrigatório'
            ], 400);
        }
        
        try {
            // Buscar dados do cliente ERP
            $stmt = $this->pdo->prepare("
                SELECT 
                    idcliforemp,
                    fantasia,
                    razao,
                    fone,
                    email,
                    cnpj,
                    cpf,
                    ie,
                    endereco,
                    numero,
                    bairro,
                    cep,
                    complemento,
                    uf,
                    inativo,
                    datacadastro,
                    (SELECT descricao FROM cidade WHERE idcidade = cliforemp.idcidade) as cidade
                FROM cliforemp 
                WHERE idcliforemp = :id
            ");
            $stmt->execute(['id' => $idErp]);
            $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$cliente) {
                return $this->json($response, [
                    'success' => false,
                    'error' => 'Cliente ERP não encontrado'
                ], 404);
            }
            
            // Se tem ID CRM, vincular
            if ($idCrm > 0) {
                $this->vincularClienteCRMERP($idCrm, $idErp);
                $this->atualizarDadosClienteERP($idCrm, $idErp);
            }
            
            $pedidos = $this->buscarPedidosPorIdCliforemp($idErp);
            
            return $this->json($response, [
                'success' => true,
                'cliente' => $cliente,
                'pedidos' => $pedidos,
                'total_pedidos' => count($pedidos),
                'cliforemp_id' => $idErp,
                'vinculado' => $idCrm > 0
            ]);
            
        } catch (Exception $e) {
            error_log('Erro em selecionarClienteERP: ' . $e->getMessage());
            return $this->json($response, [
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Busca pedidos por cliente CRM
     */
    private function buscarPedidosPorCliente(Response $response, array $cliente): Response
    {
        $cnpjCpf = $cliente['cnpj_cpf'] ?? '';
        $cliforempId = $cliente['cliforemp_id'] ?? 0;
        
        // Se tem cliforemp_id, buscar direto
        if ($cliforempId > 0) {
            $pedidos = $this->buscarPedidosPorIdCliforemp($cliforempId);
            
            // Também buscar dados do ERP para atualizar o cliente
            $this->atualizarDadosClienteERP($cliente['id'], $cliforempId);
            
            return $this->json($response, [
                'success' => true,
                'pedidos' => $pedidos,
                'total_pedidos' => count($pedidos),
                'cliente_erp_encontrado' => true,
                'cliforemp_id' => $cliforempId
            ]);
        }
        
        // Se não tem cliforemp_id, buscar pelo CNPJ/CPF
        if (!empty($cnpjCpf)) {
            $cnpjCpf = preg_replace('/[^0-9]/', '', $cnpjCpf);
            
            $stmt = $this->pdo->prepare("
                SELECT idcliforemp 
                FROM cliforemp 
                WHERE REPLACE(REPLACE(REPLACE(cnpj, '.', ''), '/', ''), '-', '') = :cnpj
                OR REPLACE(REPLACE(REPLACE(cpf, '.', ''), '/', ''), '-', '') = :cpf
                LIMIT 1
            ");
            $stmt->execute([
                'cnpj' => $cnpjCpf,
                'cpf' => $cnpjCpf
            ]);
            $erp = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($erp) {
                $pedidos = $this->buscarPedidosPorIdCliforemp($erp['idcliforemp']);
                
                // Vincular cliente CRM com ERP
                $this->vincularClienteCRMERP($cliente['id'], $erp['idcliforemp']);
                $this->atualizarDadosClienteERP($cliente['id'], $erp['idcliforemp']);
                
                return $this->json($response, [
                    'success' => true,
                    'pedidos' => $pedidos,
                    'total_pedidos' => count($pedidos),
                    'cliente_erp_encontrado' => true,
                    'cliforemp_id' => $erp['idcliforemp']
                ]);
            }
        }
        
        // Cliente não encontrado no ERP
        return $this->json($response, [
            'success' => false,
            'pedidos' => [],
            'total_pedidos' => 0,
            'cliente_erp_encontrado' => false,
            'message' => 'Cliente não encontrado no ERP'
        ]);
    }
    
    /**
     * Busca pedidos por ID do cliforemp
     */
    private function buscarPedidosPorIdCliforemp(int $idcliforemp): array
    {
        try {
            $sql = "
                SELECT 
                    p.idpedido,
                    p.data,
                    p.valortotalpedido,
                    p.status,
                    p.situacao,
                    p.observacao,
                    p.idcliforemp,
                    p.idfilial,
                    p.idtransacao,
                    (SELECT descricao FROM pedido_transacao WHERE idtransacao = p.idtransacao) as transacao_desc,
                    (SELECT nome FROM filial WHERE idfilial = p.idfilial) as filial_nome,
                    (SELECT COUNT(*) FROM pedido_item WHERE idpedido = p.idpedido) as total_itens,
                    (
                        SELECT json_agg(
                            json_build_object(
                                'iditem', pi.iditem,
                                'idpedido_item', pi.iditem,
                                'codigo', i.iditem,
                                'descricao', i.descricao,
                                'unidade', (SELECT descricao FROM unidade WHERE idunidade = i.idunidadebasica),
                                'quantidade', pi.qt,
                                'preco', pi.valor,
                                'subtotal', pi.qt * pi.valor,
                                'desconto', pi.valordesconto,
                                'valor_total', (pi.qt * pi.valor) - COALESCE(pi.valordesconto, 0)
                            )
                        )
                        FROM pedido_item pi
                        LEFT JOIN item i ON i.iditem = pi.iditem
                        WHERE pi.idpedido = p.idpedido
                    ) as itens
                FROM pedido p
                WHERE p.idcliforemp = :idcliforemp
                and p.status in (1,4,5)
                ORDER BY p.data DESC
                LIMIT 20
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['idcliforemp' => $idcliforemp]);
            $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Processar resultados
            foreach ($pedidos as &$pedido) {
                $pedido['itens'] = json_decode($pedido['itens'] ?? '[]', true) ?: [];
                $pedido['valor_total_formatado'] = 'R$ ' . number_format($pedido['valortotalpedido'] ?? 0, 2, ',', '.');
                $pedido['dtemissao_formatada'] = $pedido['data'] ? date('d/m/Y', strtotime($pedido['data'])) : '—';
                $pedido['status_pedido'] = $this->getStatusPedido($pedido['status'] ?? '');
                $pedido['situacao_desc'] = $this->getSituacaoPedido($pedido['situacao'] ?? '');
                
                // Formatar data para exibição
                if (!empty($pedido['data'])) {
                    try {
                        $pedido['data_formatada'] = date('d/m/Y H:i', strtotime($pedido['data']));
                    } catch (Exception $e) {
                        $pedido['data_formatada'] = $pedido['data'];
                    }
                }
            }
            
            return $pedidos;
            
        } catch (Exception $e) {
            error_log('Erro em buscarPedidosPorIdCliforemp: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Traduz status do pedido
     */
    private function getStatusPedido(string $status): string
    {
        $statusMap = [
            '1' => 'Aberto',
            '2' => 'Fechado',
            '3' => 'Cancelado',
            '4' => 'Faturado Parcial',
            '5' => 'Faturado',
            '6' => 'Saldo cancelado'
        ];
        return $statusMap[$status] ?? 'Desconhecido';
    }
    
    /**
     * Traduz situação do pedido
     */
    private function getSituacaoPedido(string $situacao): string
    {
        $situacaoMap = [
            '1'  => 'Aprovado comercial',
            '2'  => 'Aguardando aprovação',
            '3'  => 'Enviado embarque',
            '4'  => 'Rejeitado',
            '5'  => 'Enviado produção',
            '6'  => 'Reaberto',
            '7'  => 'Alterado por nota fiscal',
            '8'  => 'Saldo cancelado',
            '9'  => 'Reaberto parcial',
            '10' => 'Pré-venda (cupom fiscal)',
            '11' => 'Renovado validade',
            '12' => 'Conferindo expedição',
            '13' => 'Agrupado',
            '16' => 'Devolução entrega futura',
            '17' => 'DAV (orçamento)',
            '18' => 'Alterado vendedor/representante',
            '19' => 'DAV faturada (CF)',
            '21' => 'Transformado'
        ];
        return $situacaoMap[$situacao] ?? 'Desconhecido';
    }
    
    /**
     * Vincula cliente CRM ao ERP
     */
    private function vincularClienteCRMERP(int $idCrm, int $idErp): void
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE mkt_clientes 
                SET cliforemp_id = :id_erp, 
                    data_atualizacao = NOW() 
                WHERE id = :id_crm
            ");
            $stmt->execute([
                'id_erp' => $idErp,
                'id_crm' => $idCrm
            ]);
        } catch (Exception $e) {
            error_log('Erro ao vincular cliente: ' . $e->getMessage());
        }
    }
    
    /**
     * Atualiza dados do cliente com informações do ERP
     */
    private function atualizarDadosClienteERP(int $idCrm, int $idErp): void
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE mkt_clientes c
                SET 
                    nome = COALESCE(cli.fantasia, cli.razao, c.nome),
                    empresa = COALESCE(cli.razao, cli.fantasia, c.empresa),
                    telefone = COALESCE(cli.fone, c.telefone),
                    email = COALESCE(cli.email, c.email),
                    cidade = COALESCE((SELECT descricao FROM cidade WHERE idcidade = cli.idcidade), c.cidade),
                    uf = COALESCE(cli.uf, c.uf),
                    endereco = COALESCE(cli.endereco, c.endereco),
                    numero = COALESCE(cli.numero, c.numero),
                    bairro = COALESCE(cli.bairro, c.bairro),
                    cep = COALESCE(cli.cep, c.cep),
                    complemento = COALESCE(cli.complemento, c.complemento),
                    cnpj_cpf = COALESCE(cli.cnpj, cli.cpf, c.cnpj_cpf),
                    ie = COALESCE(cli.ie, c.ie),
                    data_atualizacao = NOW()
                FROM cliforemp cli
                WHERE c.id = :id_crm
                AND cli.idcliforemp = :id_erp
            ");
            $stmt->execute([
                'id_crm' => $idCrm,
                'id_erp' => $idErp
            ]);
        } catch (Exception $e) {
            error_log('Erro ao atualizar dados do cliente: ' . $e->getMessage());
        }
    }
    
    /**
     * Formata um número de CNPJ/CPF para exibição
     */
    private function formatarCnpjCpf(string $valor): string
    {
        $numeros = preg_replace('/[^0-9]/', '', $valor);
        
        if (strlen($numeros) === 14) {
            return preg_replace('/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/', '$1.$2.$3/$4-$5', $numeros);
        }
        
        if (strlen($numeros) === 11) {
            return preg_replace('/^(\d{3})(\d{3})(\d{3})(\d{2})$/', '$1.$2.$3-$4', $numeros);
        }
        
        return $valor;
    }
    
    /**
     * Response JSON helper
     */
    private function json($response, $data, $status = 200): Response
    {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}