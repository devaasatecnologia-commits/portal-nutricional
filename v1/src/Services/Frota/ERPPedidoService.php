<?php
// src/Services/Frota/ERPPedidoService.php

namespace Nutricional\Services\Frota;

/**
 * Service responsável pela criação de pedidos no ERP
 * 
 * Esta classe gerencia a criação de pedidos de faltante e devolução
 * diretamente nas tabelas do ERP (palmtop_pedido e palmtop_pedido_item)
 */
class ERPPedidoService
{
    /** @var \PDO */
    private $pdo;
    
    /** @var bool Modo de teste - se true, não insere no banco */
    private $sandboxMode = true;
    
    /** @var string Caminho do arquivo de log */
    private $logFile;
    
    /** @var array Configurações padrão */
    private $config = [
        'idempresa' => 1,
        'idcondicao' => 65,
        'idmetodo' => 7,
        'idtabela' => 0,
        'tipofrete' => 0,
        'situacao' => 1,
        'fixavencimento' => 'N',
        'uf_origem' => 'SC',
        'hotsync' => 'S',
        'importado' => 'N',
        'status' => 1,
        'origem' => 1,
        'tipovendrepre' => 1,
        'idorigem' => 0
    ];
    
    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->sandboxMode = strtolower((string)($_ENV['ERP_PEDIDO_SANDBOX'] ?? 'true')) !== 'false';
        $this->logFile = __DIR__ . '/../../../logs/erp_pedido_' . date('Y-m-d') . '.log';
        
        // Criar diretório de logs se não existir
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }
    
    /**
     * Define o modo sandbox
     */
    public function setSandboxMode(bool $mode): void
    {
        $this->sandboxMode = $mode;
    }
    
    /**
     * Cria um pedido no ERP
     * 
     * @param array $dados Dados do pedido
     * @return array Resultado da operação
     */
    public function criarPedidoERP(array $dados): array
    {
        try {
            // ================================================================
            // 1. VALIDAÇÃO DOS DADOS
            // ================================================================
            $validacao = $this->validarDados($dados);
            if (!$validacao['success']) {
                return $validacao;
            }
            
            // ================================================================
            // 2. BUSCAR DADOS COMPLEMENTARES
            // ================================================================
            $cliente = $this->buscarCliente($dados['idcliente']);
            if (!$cliente) {
                return $this->respostaErro("Cliente {$dados['idcliente']} não encontrado");
            }
            
            $transacao = $this->buscarTransacao($dados['idtransacao']);
            if (!$transacao) {
                return $this->respostaErro("Transação {$dados['idtransacao']} não encontrada");
            }
            
            // ================================================================
            // 3. GERAR SEQUENCIAIS
            // ================================================================
            $sequencialPortal = $this->gerarSequencialPortal();
            $idPedidoPDA = $this->gerarIdPedidoPDA();
            
            // ================================================================
            // 4. PROCESSAR ITENS
            // ================================================================
            $itensProcessados = [];
            $valorTotalItens = 0;
            $pesoBrutoTotal = 0;
            $pesoLiquidoTotal = 0;
            
            foreach ($dados['itens'] as $item) {
                $infoItem = $this->buscarInfoItem($item['iditem'], $dados['idfilial'] ?? 1);
                if (!$infoItem) {
                    return $this->respostaErro("Item {$item['iditem']} não encontrado no estoque");
                }
                
                $quantidade = (float)($item['quantidade'] ?? 1);
                $valorUnitario = (float)($infoItem['valorprecovenda'] ?? $item['valor_unitario'] ?? 0);
                $valorTotalItem = $quantidade * $valorUnitario;
                
                // Verificar saldo
                $saldo = $this->verificarSaldoItem($item['iditem'], $dados['idfilial'] ?? 1);
                if (!$this->sandboxMode && $quantidade > $saldo) {
                    return $this->respostaErro(
                        "Saldo insuficiente para item {$item['iditem']}. " .
                        "Disponível: {$saldo}, Solicitado: {$quantidade}"
                    );
                }
                
                $itensProcessados[] = [
                    'iditem' => (int)$item['iditem'],
                    'quantidade' => $quantidade,
                    'valor_unitario' => $valorUnitario,
                    'valor_total' => $valorTotalItem,
                    'peso_bruto' => (float)($infoItem['pesobruto'] ?? 0) * $quantidade,
                    'peso_liquido' => (float)($infoItem['pesoliquido'] ?? 0) * $quantidade,
                    'idunidade' => (int)($infoItem['idunidadebasica'] ?? $item['idunidade'] ?? 0),
                    'descricao' => $infoItem['descricao'] ?? $item['descricao'] ?? '',
                    'referencia' => $infoItem['referencia'] ?? $item['referencia'] ?? '',
                    'complemento' => $infoItem['complemento'] ?? $item['complemento'] ?? '',
                    'perc_comissao' => (float)($infoItem['perccomissao'] ?? 0),
                    'perc_margem' => (float)($infoItem['percmargem'] ?? 0),
                    'valorcustocontabil' => (float)($infoItem['valorcustocontabil'] ?? 0),
                    'valorcustogerencial' => (float)($infoItem['custogerencial'] ?? 0),
                    'valorcustomedio' => (float)($infoItem['valorcustomediounitario'] ?? 0),
                    'idimposto' => (int)($infoItem['idimposto'] ?? 0),
                    'idsituacaotributaria' => (int)($infoItem['idsituacaotributaria'] ?? 0),
                    'percipi' => (float)($infoItem['perc_ipi'] ?? 0)
                ];
                
                $valorTotalItens += $valorTotalItem;
                $pesoBrutoTotal += (float)($infoItem['pesobruto'] ?? 0) * $quantidade;
                $pesoLiquidoTotal += (float)($infoItem['pesoliquido'] ?? 0) * $quantidade;
            }
            
            // ================================================================
            // 5. MONTAR DADOS COMPLETOS DO PEDIDO
            // ================================================================
            $nomeCliente = $cliente['fantasia'] ?? $cliente['razao'] ?? 'PORTAL[' . $sequencialPortal . ']';
            
            $dadosPedido = array_merge($this->config, [
                'idpedidopda' => $idPedidoPDA,
                'sequencial_portal' => $sequencialPortal,
                'idfilial' => (int)($dados['idfilial'] ?? 1),
                'idcliente' => (int)$dados['idcliente'],
                'idtransacao' => (int)$dados['idtransacao'],
                'idserie' => $transacao['idserie'] ?? '.',
                'idvendrepre' => (int)($cliente['idvendedor'] ?? 0),
                'uf_destino' => $cliente['uf'] ?? 'SC',
                'nomecliente' => $nomeCliente,
                'usuario' => $dados['usuario'] ?? 'SISTEMA',
                'dataenvio' => date('Y-m-d'),
                'data' => date('Y-m-d'),
                'dataentrega' => date('Y-m-d', strtotime('+30 days')),
                'datahorapda' => date('Y-m-d H:i:s'),
                'datahora' => date('Y-m-d H:i:s'),
                'observacao' => $this->montarObservacao($dados),
                'valortotalitens' => $valorTotalItens,
                'valortotalpedido' => $valorTotalItens,
                'pesobruto' => $pesoBrutoTotal,
                'pesoliquido' => $pesoLiquidoTotal,
                'itens' => $itensProcessados
            ]);
            
            // ================================================================
            // 6. REGISTRAR LOG DA OPERAÇÃO
            // ================================================================
            $this->registrarLog('PRE_VALIDACAO', $dadosPedido);
            
            // ================================================================
            // 7. MODO SANDBOX
            // ================================================================
            if ($this->sandboxMode) {
                return [
                    'success' => true,
                    'sandbox' => true,
                    'message' => 'MODO SANDBOX: Pedido validado com sucesso. Nenhuma inserção foi feita.',
                    'data' => $dadosPedido,
                    'sql' => [
                        'pedido' => $this->gerarSQLPedido($dadosPedido),
                        'itens' => $this->gerarSQLItens($dadosPedido),
                        'update' => $this->gerarSQLUpdate($dadosPedido)
                    ]
                ];
            }
            
            // ================================================================
            // 8. MODO PRODUÇÃO
            // ================================================================
            return $this->executarInsercao($dadosPedido);
            
        } catch (\Exception $e) {
            $this->registrarLog('ERRO_GERAL', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->respostaErro('Erro inesperado: ' . $e->getMessage());
        }
    }
    
    /**
     * Valida os dados do pedido
     */
    private function validarDados(array $dados): array
    {
        if (empty($dados['idcliente'])) {
            return $this->respostaErro('Cliente não informado');
        }
        
        if (empty($dados['idtransacao'])) {
            return $this->respostaErro('Tipo de transação não informado');
        }
        
        if (empty($dados['itens']) || !is_array($dados['itens'])) {
            return $this->respostaErro('Nenhum item informado');
        }
        
        foreach ($dados['itens'] as $item) {
            if (empty($item['iditem'])) {
                return $this->respostaErro('Item sem ID informado');
            }
            if (empty($item['quantidade']) || $item['quantidade'] <= 0) {
                return $this->respostaErro("Item {$item['iditem']} com quantidade inválida");
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Busca dados do cliente
     */
    private function buscarCliente(int $idCliente)
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                idcliforemp,
                fantasia,
                razao,
                cpf,
                fone,
                email,
                endereco,
                bairro,
                cidade,
                uf,
                cep,
                idvendedor
            FROM cliforemp
            WHERE idcliforemp = :id
        ");
        $stmt->execute(['id' => $idCliente]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Busca dados da transação
     */
    private function buscarTransacao(int $idTransacao)
    {
        $stmt = $this->pdo->prepare("
            SELECT idtransacao, idserie, descricao
            FROM pedido_transacao
            WHERE idtransacao = :id
        ");
        $stmt->execute(['id' => $idTransacao]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Busca informações do item no estoque
     */
    private function buscarInfoItem(int $idItem, int $idFilial)
    {
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT
                i.iditem,
                i.referencia,
                i.descricao,
                i.complemento,
                i.pesobruto,
                i.pesoliquido,
                i.idunidadebasica,
                i.perccomissao,
                e.valorprecovenda,
                e.valorcustocontabil,
                e.valorcustomediounitario,
                e.percmargem,
                e.custogerencial,
                e.idimposto,
                imp.idsituacaotributaria,
                imp.perc_ipi
            FROM estoque_filial e
            JOIN item i ON i.iditem = e.iditem
            LEFT JOIN imposto imp ON imp.idimposto = e.idimposto
            LEFT JOIN imposto_estado ie ON ie.idimposto = imp.idimposto
            WHERE e.iditem = :iditem
              AND e.idfilial = :idfilial
        ");
        $stmt->execute(['iditem' => $idItem, 'idfilial' => $idFilial]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Verifica saldo em estoque
     */
    private function verificarSaldoItem(int $idItem, int $idFilial): float
    {
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(quantidade), 0) as saldo
            FROM lote_filial_deposito
            WHERE iditem = :iditem
              AND idfilial = :idfilial
        ");
        $stmt->execute(['iditem' => $idItem, 'idfilial' => $idFilial]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return (float)($result['saldo'] ?? 0);
    }
    
    /**
     * Gera sequencial para o portal
     */
    private function gerarSequencialPortal(): int
    {
        try {
            $stmt = $this->pdo->query("SELECT nextval('pedido_portal') as sequencial");
            return (int)$stmt->fetchColumn();
        } catch (\Exception $e) {
            // Se a sequence não existir, criar
            $this->pdo->exec("CREATE SEQUENCE IF NOT EXISTS pedido_portal START 1");
            $stmt = $this->pdo->query("SELECT nextval('pedido_portal') as sequencial");
            return (int)$stmt->fetchColumn();
        }
    }
    
    /**
     * Gera ID para palmtop_pedido
     */
    private function gerarIdPedidoPDA(): int
    {
        try {
            $stmt = $this->pdo->query("SELECT nextval('gi_palmtop_pedido') as id");
            return (int)$stmt->fetchColumn();
        } catch (\Exception $e) {
            // Se a sequence não existir, criar
            $this->pdo->exec("CREATE SEQUENCE IF NOT EXISTS gi_palmtop_pedido START 1");
            $stmt = $this->pdo->query("SELECT nextval('gi_palmtop_pedido') as id");
            return (int)$stmt->fetchColumn();
        }
    }
    
  /**
 * Monta observação com informações do acerto
 * 🔥 CORRIGIDO: Usa os dados do motorista, veículo e tipo de problema
 */
private function montarObservacao(array $dados): string
{
    $parts = [];
    
    // ============================================================
    // TIPO DE PROBLEMA EM DESTAQUE
    // ============================================================
    if (!empty($dados['tipo_problema'])) {
        $tipoLabel = strtoupper($dados['tipo_problema']);
        $tipoEmoji = $dados['tipo_problema'] === 'faltante' ? '⚠️' : '🔄';
        $parts[] = "{$tipoEmoji} TIPO: {$tipoLabel}";
    }
    
    // ============================================================
    // DADOS DO ACERTO
    // ============================================================
    if (!empty($dados['acerto_id'])) {
        $parts[] = "ACERTO: #" . $dados['acerto_id'];
    }
    
    if (!empty($dados['embarque_id'])) {
        $parts[] = "EMBARQUE: #" . $dados['embarque_id'];
    }
    
    if (!empty($dados['entrega_id'])) {
        $parts[] = "ENTREGA: #" . $dados['entrega_id'];
    }
    
    // ============================================================
    // DADOS DO MOTORISTA
    // ============================================================
    if (!empty($dados['motorista_nome'])) {
        $parts[] = "MOTORISTA: " . $dados['motorista_nome'];
    }
    if (!empty($dados['motorista_cpf'])) {
        $parts[] = "CPF: " . $dados['motorista_cpf'];
    }
    
    // ============================================================
    // DADOS DO VEÍCULO
    // ============================================================
    if (!empty($dados['veiculo_placa'])) {
        $parts[] = "VEÍCULO: " . $dados['veiculo_placa'];
    }
    if (!empty($dados['veiculo_modelo'])) {
        $parts[] = "MODELO: " . $dados['veiculo_modelo'];
    }
    if (!empty($dados['veiculo_marca'])) {
        $parts[] = "MARCA: " . $dados['veiculo_marca'];
    }
    
    // ============================================================
    // DATAS E HORÁRIOS
    // ============================================================
    if (!empty($dados['data_entrega'])) {
        $parts[] = "DATA ENTREGA: " . $dados['data_entrega'];
    }
    if (!empty($dados['hora_entrega'])) {
        $parts[] = "HORA: " . $dados['hora_entrega'];
    }
    if (!empty($dados['data_checkin'])) {
        $parts[] = "CHECK-IN: " . $dados['data_checkin'];
    }
    
    // ============================================================
    // CLIENTE E RECEBEDOR
    // ============================================================
    if (!empty($dados['cliente_nome'])) {
        $parts[] = "CLIENTE: " . $dados['cliente_nome'];
    }
    if (!empty($dados['nome_recebedor'])) {
        $parts[] = "RECEBEDOR: " . $dados['nome_recebedor'];
    }
    
    // ============================================================
    // MOTIVO E OBSERVAÇÕES
    // ============================================================
    if (!empty($dados['motivo'])) {
        $parts[] = "MOTIVO: " . $dados['motivo'];
    }
    
    if (!empty($dados['pedido_original'])) {
        $parts[] = "PEDIDO ORIGINAL: " . $dados['pedido_original'];
    }
    
    // Observação adicional
    if (!empty($dados['observacao'])) {
        $parts[] = $dados['observacao'];
    }
    
    // ============================================================
    // ITENS AFETADOS
    // ============================================================
    if (!empty($dados['itens']) && is_array($dados['itens'])) {
        $itensList = [];
        foreach ($dados['itens'] as $item) {
            $ref = $item['referencia'] ?? 'Item';
            $qtd = $item['quantidade'] ?? 0;
            $itensList[] = "{$ref}: {$qtd} un";
        }
        if (!empty($itensList)) {
            $parts[] = "ITENS: " . implode('; ', $itensList);
        }
    }
    
    // ============================================================
    // USUÁRIO
    // ============================================================
    if (!empty($dados['usuario'])) {
        $parts[] = "USUÁRIO: " . $dados['usuario'];
    }
    
    // ============================================================
    // LIMITAR TAMANHO
    // ============================================================
    $observacao = implode(' | ', $parts);
    return substr($observacao, 0, 1000);
}
    
    /**
     * Escapa valores para SQL
     */
    private function esc($valor)
    {
        if ($valor === null || $valor === '') {
            return "''";
        }
        if (is_numeric($valor) && !is_string($valor)) {
            return $valor;
        }
        return "'" . str_replace("'", "''", $valor) . "'";
    }
    
    /**
     * Gera SQL para inserir o pedido
     */
    private function gerarSQLPedido(array $dados): string
    {
        return "
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
                {$dados['idpedidopda']}, 
                {$dados['idempresa']}, 
                {$dados['idfilial']}, 
                {$dados['sequencial_portal']}, 
                {$dados['idcliente']},
                {$dados['idcondicao']}, 
                {$dados['idmetodo']}, 
                {$dados['idtabela']}, 
                {$dados['idtransacao']}, 
                {$dados['idvendrepre']},
                {$dados['tipofrete']}, 
                {$dados['situacao']}, 
                0, 
                {$dados['valortotalitens']}, 
                0,
                0, 0, 0, 
                {$dados['valortotalpedido']},
                0, 
                {$dados['pesoliquido']}, 
                {$dados['pesobruto']}, 
                '{$dados['fixavencimento']}',
                '{$dados['uf_origem']}', 
                '{$dados['uf_destino']}', 
                '{$dados['hotsync']}', 
                {$this->esc($dados['nomecliente'])}, 
                {$this->esc($dados['usuario'])},
                '{$dados['dataenvio']}', 
                '{$dados['data']}', 
                '{$dados['dataentrega']}', 
                '{$dados['datahorapda']}', 
                '{$dados['datahora']}',
                '{$dados['importado']}', 
                {$this->esc($dados['idserie'])}, 
                {$dados['status']}, 
                {$dados['origem']}, 
                '0',
                {$dados['tipovendrepre']}, 
                {$this->esc($dados['observacao'])}, 
                {$dados['idorigem']}
            )
        ";
    }
    
    /**
     * Gera SQL para inserir um item
     */
    private function gerarSQLItem(array $dados, array $item, int $index): string
    {
        $sequencial = $index + 1;
        
        return "
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
                {$dados['sequencial_portal']}, 
                {$sequencial}, 
                {$dados['idpedidopda']}, 
                0, 
                {$item['iditem']},
                {$item['idunidade']}, 
                {$item['quantidade']}, 
                {$item['valor_unitario']}, 
                0, 
                {$item['valor_total']},
                {$item['valorcustocontabil']}, 
                0, 
                0, 
                0,
                0, 
                {$item['percipi']}, 
                {$item['perc_comissao']}, 
                {$item['perc_margem']},
                {$this->esc(substr($item['complemento'] ?? '.', 0, 100))}, 
                {$this->esc(substr($item['descricao'] ?? '.', 0, 80))}, 
                {$this->esc($item['referencia'] ?? '.')}, 
                0,
                0, 
                {$item['valorcustogerencial']}, 
                {$item['valorcustomedio']},
                {$item['valor_unitario']}, 
                {$item['idunidade']}, 
                {$item['quantidade']},
                {$item['valor_unitario']}, 
                'N', 
                0,
                0, 0, 0,
                0, 0, 0,
                0, 0, 0,
                0, {$item['quantidade']},
                0, 0, 0,
                0, 0,
                '.', '.', 0
            )
        ";
    }
    
    /**
     * Gera SQL para todos os itens
     */
    private function gerarSQLItens(array $dados): string
    {
        $sqls = [];
        foreach ($dados['itens'] as $index => $item) {
            $sqls[] = $this->gerarSQLItem($dados, $item, $index);
        }
        return implode("\n\n", $sqls);
    }
    
    /**
     * Gera SQL para atualizar totais do pedido
     */
    private function gerarSQLUpdate(array $dados): string
    {
        return "
            UPDATE public.palmtop_pedido 
            SET valoritens = {$dados['valortotalitens']},
                valortotal = {$dados['valortotalpedido']},
                pesobruto = {$dados['pesobruto']},
                pesoliquido = {$dados['pesoliquido']}
            WHERE idpedidopda = {$dados['idpedidopda']}
        ";
    }
    
    /**
     * Executa as inserções no banco
     */
    private function executarInsercao(array $dados): array
    {
        try {
            $this->pdo->beginTransaction();
            
            // Inserir pedido
            $sqlPedido = $this->gerarSQLPedido($dados);
            $this->pdo->exec($sqlPedido);
            
            // Inserir itens
            foreach ($dados['itens'] as $index => $item) {
                $sqlItem = $this->gerarSQLItem($dados, $item, $index);
                $this->pdo->exec($sqlItem);
            }
            
            // Atualizar totais
            $sqlUpdate = $this->gerarSQLUpdate($dados);
            $this->pdo->exec($sqlUpdate);
            
            $this->pdo->commit();
            
            $this->registrarLog('SUCESSO', [
                'idpedidopda' => $dados['idpedidopda'],
                'sequencial_portal' => $dados['sequencial_portal'],
                'total_itens' => count($dados['itens']),
                'valor_total' => $dados['valortotalpedido']
            ]);
            
            return [
                'success' => true,
                'message' => 'Pedido criado com sucesso no ERP',
                'data' => [
                    'idpedidopda' => $dados['idpedidopda'],
                    'sequencial_portal' => $dados['sequencial_portal'],
                    'total_itens' => count($dados['itens']),
                    'valor_total' => $dados['valortotalpedido']
                ]
            ];
            
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            $this->registrarLog('ERRO', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->respostaErro('Erro ao criar pedido no ERP: ' . $e->getMessage());
        }
    }
    
    /**
     * Registra log da operação
     */
    private function registrarLog(string $tipo, $dados): void
    {
        $log = [
            'timestamp' => date('Y-m-d H:i:s'),
            'tipo' => $tipo,
            'dados' => $dados
        ];
        
        file_put_contents(
            $this->logFile,
            json_encode($log, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . 
            "\n" . str_repeat('-', 80) . "\n",
            FILE_APPEND
        );
    }
    
    /**
     * Retorna resposta de erro padronizada
     */
    private function respostaErro(string $mensagem): array
    {
        return [
            'success' => false,
            'message' => $mensagem
        ];
    }
}