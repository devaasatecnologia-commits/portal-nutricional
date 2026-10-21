<?php
// v1/controllers/RetornoController.php

namespace Nutricional\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class RetornoController
{
    private $pdo;
    
    private $destinos = [
        10117 => ['nome' => 'CONTAINER', 'cor' => '#10b981'],
        16595 => ['nome' => 'MATÉRIA-PRIMA', 'cor' => '#3b82f6'],
        16596 => ['nome' => 'REVENDA', 'cor' => '#f59e0b'],
        16688 => ['nome' => 'TROCAS FORNECEDOR', 'cor' => '#8b5cf6']
    ];
    
    /** Item placeholder (X-ITEM) que mantém o pedido de estoque nunca vazio */
    private const ITEM_PLACEHOLDER = 2181;

    /**
     * Regra do X-ITEM: havendo qualquer item real no pedido, o 2181 é removido;
     * sem nenhum item real, o 2181 (qt 1) é inserido para o pedido não ficar vazio.
     */
    private function sincronizarPlaceholder(int $idpedido): void
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM pedido_item WHERE idpedido = ? AND ativo = 'S' AND iditem <> ?");
        $stmt->execute([$idpedido, self::ITEM_PLACEHOLDER]);
        $reais = (int)$stmt->fetchColumn();

        if ($reais > 0) {
            $stmt = $this->pdo->prepare("DELETE FROM pedido_item WHERE idpedido = ? AND iditem = ?");
            $stmt->execute([$idpedido, self::ITEM_PLACEHOLDER]);
            return;
        }

        $stmt = $this->pdo->prepare("SELECT 1 FROM pedido_item WHERE idpedido = ? AND iditem = ? AND ativo = 'S'");
        $stmt->execute([$idpedido, self::ITEM_PLACEHOLDER]);
        if (!$stmt->fetchColumn()) {
            $dados = $this->getDadosItemCompleto(self::ITEM_PLACEHOLDER, 1);
            $this->inserirItemCompleto($idpedido, self::ITEM_PLACEHOLDER, 1, $dados);
        }
    }

    /**
     * POST /v1/retorno/criar-estoque
     * Cria um novo pedido de estoque (status 1) para o destino, copiando o último pedido
     * do cliente (preferindo o faturado/status 5) com todos os dados e a observação,
     * contendo apenas o X-ITEM (2181) para não ficar vazio.
     */
    public function criarEstoque(Request $request, Response $response): Response
    {
        $input = json_decode($request->getBody()->getContents(), true);
        $idcliforemp = (int)($input['idcliforemp'] ?? 0);

        if (!isset($this->destinos[$idcliforemp])) {
            $response->getBody()->write(json_encode(['error' => 'Destino não permitido']));
            return $response->withStatus(422)->withHeader('Content-Type', 'application/json');
        }

        try {
            $this->pdo->beginTransaction();

            // Evita duplicar se já existir pedido aberto
            $stmt = $this->pdo->prepare("SELECT idpedido FROM pedido WHERE idcliforemp = ? AND status = 1 AND idempresa = 1 AND idfilial = 1 ORDER BY idpedido DESC LIMIT 1 FOR UPDATE");
            $stmt->execute([$idcliforemp]);
            if ($existente = $stmt->fetchColumn()) {
                $this->pdo->commit();
                $response->getBody()->write(json_encode(['success' => true, 'criado' => false, 'idpedido' => (int)$existente, 'message' => 'Já existe pedido aberto para este destino.']));
                return $response->withHeader('Content-Type', 'application/json');
            }

            // Modelo: último faturado (status 5); sem faturado, o último pedido do cliente
            $stmt = $this->pdo->prepare("
                SELECT idpedido FROM pedido
                WHERE idcliforemp = ? AND idempresa = 1 AND idfilial = 1
                ORDER BY (status = 5) DESC, idpedido DESC
                LIMIT 1
            ");
            $stmt->execute([$idcliforemp]);
            $idModelo = (int)$stmt->fetchColumn();
            if ($idModelo <= 0) {
                throw new \DomainException('Não há pedido anterior deste destino para servir de modelo. Crie o primeiro pedido no ERP.');
            }

            $novoId = (int)$this->pdo->query("SELECT nextval('gi_pedido')")->fetchColumn();

            // Colunas que não devem ser herdadas do modelo (usam o DEFAULT da tabela)
            $omitir = ['idpedido', 'idmotivocancelamento', 'numero_nf_e', 'numeroprotocolo_nf_e', 'idpedidopda'];
            // Totais zerados; serão recalculados após inserir o X-ITEM
            $zerar = [
                'valortotalitens', 'valortotalpedido', 'valortotalsaldo', 'valortotalcalculado', 'pesobruto', 'pesoliquido',
                'quantidadetotal', 'valorbaseicms', 'valoricms', 'valorbasepis', 'valorpis', 'valorbasecofins', 'valorcofins',
                'valortotalcustoreposicao', 'valortotalcustogerencial', 'quantmoedatotal', 'valorbaseisentoicms',
                'valortotalbasecbsibs', 'valortotalcbs', 'valortotalibsuf', 'valortotalibs', 'valortotalcbsibs',
            ];
            $fixos = [
                'status' => '1', 'situacao' => '2', 'data' => 'CURRENT_DATE', 'datahora' => 'NOW()',
                'iniciodigitacao' => 'NOW()', 'datahoraultimaatualizacao' => 'NOW()', 'dataentrega' => 'CURRENT_DATE',
                'datacotacao' => 'CURRENT_DATE', 'pex_datafase' => 'CURRENT_DATE',
                'idpedidotransf' => '0', 'idpedido_emapda_matriz' => (string)$novoId,
                'codigo_barras' => $this->pdo->quote(sprintf('001%010d', $novoId)),
            ];

            $colunas = $this->pdo->query("SELECT column_name FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'pedido' ORDER BY ordinal_position")->fetchAll(\PDO::FETCH_COLUMN);
            $nomes = [];
            $valores = [];
            foreach ($colunas as $col) {
                if (in_array($col, $omitir, true)) continue;
                $nomes[] = '"' . $col . '"';
                if (isset($fixos[$col])) {
                    $valores[] = $fixos[$col];
                } elseif (in_array($col, $zerar, true)) {
                    $valores[] = '0';
                } else {
                    $valores[] = 'm."' . $col . '"';
                }
            }
            $nomes[] = 'idpedido';
            $valores[] = (string)$novoId;

            $sql = 'INSERT INTO pedido (' . implode(', ', $nomes) . ') SELECT ' . implode(', ', $valores) . ' FROM pedido m WHERE m.idpedido = ' . $idModelo;
            $this->pdo->exec($sql);

            // Pedido nasce com o X-ITEM e totais/parcela calculados
            $this->sincronizarPlaceholder($novoId);
            $this->atualizarTotaisPedido($novoId);

            $this->pdo->commit();

            $response->getBody()->write(json_encode([
                'success' => true,
                'criado' => true,
                'idpedido' => $novoId,
                'idpedido_modelo' => $idModelo,
                'message' => 'Estoque criado com sucesso para ' . $this->destinos[$idcliforemp]['nome'] . '!',
            ]));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('[RetornoController] Erro em criarEstoque: ' . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus($e instanceof \DomainException ? 409 : 500)->withHeader('Content-Type', 'application/json');
        }
    }

    public function __construct()
    {
        $this->pdo = \getPDO();
    }
    
    /**
     * Processa o caminho da imagem para URL correta
     */
    private function processarImagem($path_foto_master)
    {
        if (empty($path_foto_master)) {
            return null;
        }
        
        $fotoPath = $path_foto_master;
        if (strpos($fotoPath, 'Fotos para o Site\\') !== false) {
            $fotoPath = explode('Fotos para o Site\\', $fotoPath)[1];
        }
        $fotoPath = str_replace('\\', '/', $fotoPath);
        $url = 'https://acesso.nutricionalbr.com:2053/fotos/' . rawurlencode($fotoPath);
        $url = str_replace('%2F', '/', $url);
        return $url;
    }
    
    /**
     * Obtém ou cria o pedido ativo para um estoque
     */
    private function getPedidoAberto($idcliforemp)
    {
        // Pedido de estoque aberto criado no ERP (nunca criamos pedido por aqui)
        $stmt = $this->pdo->prepare("
            SELECT idpedido, numero, data, observacao, valortotalpedido
            FROM pedido
            WHERE idcliforemp = ? AND status = 1 AND idempresa = 1 AND idfilial = 1
            ORDER BY idpedido DESC
            LIMIT 1
        ");
        $stmt->execute([$idcliforemp]);
        $pedido = $stmt->fetch();
        if (!$pedido) {
            $nome = $this->destinos[$idcliforemp]['nome'] ?? $idcliforemp;
            throw new \DomainException("Não há pedido aberto para o destino {$nome}. Crie o pedido no ERP antes de destinar.");
        }
        return $pedido;
    }
    
    /**
     * Atualiza ou cria parcelas para o pedido
     */
    private function atualizarParcelas($idpedido, $valorTotal)
    {
        // Busca se já existe parcela
        $stmt = $this->pdo->prepare("
            SELECT idparcela FROM pedido_parcela 
            WHERE idpedido = ? LIMIT 1
        ");
        $stmt->execute([$idpedido]);
        $existe = $stmt->fetch();
        
        if ($existe) {
            // Atualiza parcela existente
            $stmt = $this->pdo->prepare("
                UPDATE pedido_parcela 
                SET valor = ?, 
                    vencimento = CURRENT_DATE + INTERVAL '30 days'
                WHERE idpedido = ?
            ");
            $stmt->execute([$valorTotal, $idpedido]);
        } else {
            // Cria nova parcela
            $stmt = $this->pdo->prepare("
                INSERT INTO pedido_parcela (
                    idpedido, idempresa, idfilial, vencimento, numerodoc,
                    data, valor, desconto, percdesconto, outrasdespesas,
                    observacao, dias, baixaauto, idbanco, nrconta,
                    nragencia, nrcheque, correntista, cpf, cnpj,
                    cmc7, valordescontofinanceiro, valorcomdescfinanceiro, valorprice
                ) VALUES (
                    ?, 1, 1, CURRENT_DATE + INTERVAL '30 days', '',
                    CURRENT_DATE, ?, 0, 0, 0,
                    'Parcela única - Estoque Provisório', 30, 'N', 0, '',
                    '', '', '', '', '',
                    '', 0, 0, 0
                )
            ");
            $stmt->execute([$idpedido, $valorTotal]);
        }
    }
    
  /**
 * Atualiza TODOS os totais do pedido (valor, peso, quantidade, etc)
 */
private function atualizarTotaisPedido($idpedido)
{
    $stmt = $this->pdo->prepare("
        UPDATE pedido 
        SET 
            valortotalpedido = (
                SELECT COALESCE(SUM(valortotal), 0) 
                FROM pedido_item 
                WHERE idpedido = ? AND ativo = 'S'
            ),
            valortotalitens = (
                SELECT COALESCE(SUM(valortotal), 0) 
                FROM pedido_item 
                WHERE idpedido = ? AND ativo = 'S'
            ),
            valortotalcalculado = (
                SELECT COALESCE(SUM(valortotal), 0) 
                FROM pedido_item 
                WHERE idpedido = ? AND ativo = 'S'
            ),
            pesoliquido = (
                SELECT COALESCE(SUM(qtpesoliquido), 0) 
                FROM pedido_item 
                WHERE idpedido = ? AND ativo = 'S'
            ),
            pesobruto = (
                SELECT COALESCE(SUM(qtpesobruto), 0) 
                FROM pedido_item 
                WHERE idpedido = ? AND ativo = 'S'
            ),
            quantidadetotal = (
                SELECT COALESCE(SUM(qt), 0) 
                FROM pedido_item 
                WHERE idpedido = ? AND ativo = 'S'
            ),
            datahoraultimaatualizacao = NOW()
        WHERE idpedido = ?
    ");
    $stmt->execute([
        $idpedido, $idpedido, $idpedido,
        $idpedido, $idpedido,
        $idpedido,
        $idpedido
    ]);
    
    $stmt = $this->pdo->prepare("
        SELECT valortotalpedido FROM pedido WHERE idpedido = ?
    ");
    $stmt->execute([$idpedido]);
    $pedido = $stmt->fetch();
    
    $this->atualizarParcelas($idpedido, $pedido['valortotalpedido']);
    
    return $pedido['valortotalpedido'];
}
    
    /**
     * Gera próximo iditempedido para um pedido
     */
    private function getNextIdItemPedido($idpedido)
    {
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(MAX(iditempedido), 0) + 1 as proximo 
            FROM pedido_item WHERE idpedido = ?
        ");
        $stmt->execute([$idpedido]);
        $proximo = $stmt->fetch();
        return $proximo['proximo'];
    }
    
   /**
 * Busca dados completos do item para inserção no pedido_item
 * IGUAL AO INSERT MANUAL QUE FUNCIONOU
 */
private function getDadosItemCompleto($iditem, $quantidade)
{
    $sql = "
        WITH item_base AS (
            SELECT 
                i.iditem,
                i.descricao,
                i.referencia,
                i.pesoliquido,
                i.pesobruto,
                i.idunidadebasica as idunidade,
                i.idclassfiscal,
                i.fator_conversao,
                i.complemento,
                ef.valorprecovenda,
                ef.valorcustocontabil,
                trunc(ef.custogerencial, 2) AS custogerencial,
                ROUND((ef.valorprecovenda - trunc(ef.custogerencial, 2)) / ef.valorprecovenda * 100, 4) AS percmargem,
                ef.idimposto,
                ef.idtributo,
                (SELECT tipoenquadraformapreco FROM filial WHERE idfilial = 1) as tipo_enquadramento,
                (SELECT uf FROM filial WHERE idfilial = 1) as uf_filial
            FROM item i
            LEFT JOIN estoque_filial ef ON ef.iditem = i.iditem AND ef.idfilial = 1
            WHERE i.iditem = ? AND i.inativo = 'N'
        ),
        imposto_data AS (
            SELECT 
                ie.idsituacaotributaria,
                ie.perc_icms_interno,
                ie.perc_ipi,
                ie.idsittrib_ipi,
                ie.idsittrib_pis,
                ie.idsittrib_cofins
            FROM item_base ib
            LEFT JOIN imposto_estado ie ON ie.idimposto = ib.idimposto 
                AND ie.tipo_enquadramento = ib.tipo_enquadramento
                AND ie.uf = ib.uf_filial
        ),
        pis_cofins_data AS (
            SELECT 
                cff.perc_cred_pis,
                cff.perc_cred_cofins
            FROM item_base ib
            LEFT JOIN classificacaofiscal_filial cff ON cff.idfilial = 1 AND cff.idclassificacaofiscal = ib.idclassfiscal
        ),
        tributo_data AS (
            SELECT 
                te.perccbs,
                te.percibsuf
            FROM item_base ib
            LEFT JOIN tributo_enquadramento te ON te.idtributo = ib.idtributo 
                AND te.tipo_enquadramento = 1 
                AND te.inativo = 'N'
        )
        SELECT 
            ib.iditem,
            ib.descricao,
            ib.referencia,
            ib.pesoliquido,
            ib.pesobruto,
            ib.idunidade,
            ib.idclassfiscal,
            ib.fator_conversao,
            ib.complemento,
            ib.valorprecovenda,
            ib.valorcustocontabil,
            ib.custogerencial,
            ib.percmargem,
            ib.idimposto,
            ib.idtributo,
            COALESCE(id.idsituacaotributaria, 0) AS idsituacaotributaria,
            COALESCE(id.perc_icms_interno, 12.0) AS perc_icms,
            COALESCE(id.perc_ipi, 0) AS perc_ipi,
            COALESCE(id.idsittrib_ipi, 0) AS idsittrib_ipi,
            COALESCE(id.idsittrib_pis, 0) AS idsittrib_pis,
            COALESCE(id.idsittrib_cofins, 0) AS idsittrib_cofins,
            COALESCE(pcd.perc_cred_pis, 0) AS perc_cred_pis,
            COALESCE(pcd.perc_cred_cofins, 0) AS perc_cred_cofins,
            COALESCE(td.perccbs, 0) AS perccbs,
            COALESCE(td.percibsuf, 0) AS percibsuf
        FROM item_base ib
        CROSS JOIN imposto_data id
        CROSS JOIN pis_cofins_data pcd
        CROSS JOIN tributo_data td
    ";
    
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([$iditem]);
    $dados = $stmt->fetch();
    
    if (!$dados) {
        throw new \Exception("Item não encontrado: {$iditem}");
    }
    
    // Calcular todos os valores baseados na quantidade (igual ao INSERT manual)
    $dados['qt'] = $quantidade;
    $dados['qtpesoliquido'] = round($quantidade * $dados['pesoliquido'], 4);
    $dados['qtpesobruto'] = round($quantidade * $dados['pesobruto'], 4);
    $dados['valortotal'] = round($quantidade * $dados['valorprecovenda'], 2);
    $dados['valortotalcomdesconto'] = round($quantidade * $dados['valorprecovenda'], 2);
    $dados['valoricms'] = round($quantidade * $dados['valorprecovenda'] * $dados['perc_icms'] / 100, 2);
    $dados['valorbasepis'] = round($quantidade * $dados['valorprecovenda'] * 0.88, 2);
    $dados['valorpis'] = round($dados['valorbasepis'] * $dados['perc_cred_pis'] / 100, 2);
    $dados['valorbasecofins'] = round($quantidade * $dados['valorprecovenda'] * 0.88, 2);
    $dados['valorcofins'] = round($dados['valorbasecofins'] * $dados['perc_cred_cofins'] / 100, 2);
    $dados['valorbasecbsibs'] = round($quantidade * $dados['valorprecovenda'] * 0.80, 2);
    $dados['valorcbs'] = round($dados['valorbasecbsibs'] * $dados['perccbs'] / 100, 2);
    $dados['valoribsuf'] = round($dados['valorbasecbsibs'] * $dados['percibsuf'] / 100, 2);
    $dados['valoribs'] = $dados['valoribsuf'];
    $dados['valorcbsibs'] = $dados['valorcbs'] + $dados['valoribsuf'];
    $dados['quantmoedaunitario'] = round($dados['valorprecovenda'], 2);
    $dados['quantmoedatotal'] = round($quantidade * $dados['valorprecovenda'], 2);
    
    return $dados;
}

    /**
     * Aplica a nova quantidade a UMA linha do pedido (filtro por linha + pedido + item).
     * qt = 0 remove a linha do pedido (o histórico fica em retorno_carga); senão atualiza
     * quantidade, saldos, reserva, pesos e impostos.
     */
    private function aplicarQuantidadePedidoItem(int $iditempedido, int $idpedido, int $iditem, float $qt): void
    {
        if ($qt <= 0) {
            $stmt = $this->pdo->prepare("DELETE FROM pedido_item WHERE iditempedido = ? AND idpedido = ? AND iditem = ? AND ativo = 'S'");
            $stmt->execute([$iditempedido, $idpedido, $iditem]);
            return;
        }
        $d = $this->getDadosItemCompleto($iditem, $qt);
        $stmt = $this->pdo->prepare("
            UPDATE pedido_item SET
                qt = :qt, qtsaldo = :qt, quant_original = :qt, quant_reserva = :qt,
                qtsaldoconvertido = :qt, qtsaldogerouop = :qt,
                qtpesoliquido = :pl, qtpesobruto = :pb, pesoliquidooriginal = :pl, pesobrutooriginal = :pb,
                valortotal = :vt, valortotalcomdesconto = :vt, valor_total_com_campanha = :vt,
                valortotal_comst = :vt, quantmoedatotal = :vt, valorbaseicms = :vt, valoricms = :vi,
                valorbasepis = :bp, valorpis = :vp, valorbasecofins = :bc, valorcofins = :vc,
                valorbasecbsibs = :bb, valorcbs = :vcbs, valoribsuf = :vibs, valoribs = :vibs, valorcbsibs = :vcb
            WHERE iditempedido = :id AND idpedido = :ped AND iditem = :item AND ativo = 'S'
        ");
        $stmt->execute([
            ':qt' => $qt, ':pl' => $d['qtpesoliquido'], ':pb' => $d['qtpesobruto'], ':vt' => $d['valortotal'],
            ':vi' => $d['valoricms'], ':bp' => $d['valorbasepis'], ':vp' => $d['valorpis'],
            ':bc' => $d['valorbasecofins'], ':vc' => $d['valorcofins'], ':bb' => $d['valorbasecbsibs'],
            ':vcbs' => $d['valorcbs'], ':vibs' => $d['valoribsuf'], ':vcb' => $d['valorcbsibs'],
            ':id' => $iditempedido, ':ped' => $idpedido, ':item' => $iditem,
        ]);
    }
/**
 * Insere um item no pedido IGUAL AO INSERT MANUAL QUE FUNCIONOU
 * Esta função replica exatamente o SELECT da sua consulta de teste
 */
private function inserirItemCompleto($idpedido, $iditem, $quantidade, $dadosItem)
{
    // Buscar próximo iditempedido
    $stmt = $this->pdo->prepare("
        SELECT COALESCE(MAX(iditempedido), 0) + 1 as proximo 
        FROM pedido_item WHERE idpedido = ?
    ");
    $stmt->execute([$idpedido]);
    $proximo = $stmt->fetch();
    $iditempedido = $proximo['proximo'];
    
    // Extrair dados do item
    $valorUnitario = (float)$dadosItem['valorprecovenda'];
    $pesoLiquidoUnitario = (float)$dadosItem['pesoliquido'];
    $pesoBrutoUnitario = (float)$dadosItem['pesobruto'];
    $percIcms = (float)$dadosItem['perc_icms'];
    $percCredPis = (float)$dadosItem['perc_cred_pis'];
    $percCredCofins = (float)$dadosItem['perc_cred_cofins'];
    $perccbs = (float)$dadosItem['perccbs'];
    $percibsuf = (float)$dadosItem['percibsuf'];
    $percIpi = (float)$dadosItem['perc_ipi'];
    $percmargem = (float)$dadosItem['percmargem'];
    $custogerencial = (float)$dadosItem['custogerencial'];
    $valorcustocontabil = (float)$dadosItem['valorcustocontabil'];
    $referencia = $dadosItem['referencia'];
    $complemento = $dadosItem['complemento'];
    $idclassfiscal = (int)$dadosItem['idclassfiscal'];
    $idsituacaotributaria = (int)$dadosItem['idsituacaotributaria'];
    $fatorConversao = (float)($dadosItem['fator_conversao'] ?? 1.0);
    
    // Calcular todos os valores baseados na quantidade
    $qt = $quantidade;
    $qtpesoliquido = round($qt * $pesoLiquidoUnitario, 4);
    $qtpesobruto = round($qt * $pesoBrutoUnitario, 4);
    $valortotal = round($qt * $valorUnitario, 2);
    $valortotalcomdesconto = round($qt * $valorUnitario, 2);
    $valoricms = round($qt * $valorUnitario * $percIcms / 100, 2);
    $valorbasepis = round($qt * $valorUnitario * 0.88, 2);
    $valorpis = round($valorbasepis * $percCredPis / 100, 2);
    $valorbasecofins = round($qt * $valorUnitario * 0.88, 2);
    $valorcofins = round($valorbasecofins * $percCredCofins / 100, 2);
    $valorbasecbsibs = round($qt * $valorUnitario * 0.80, 2);
    $valorcbs = round($valorbasecbsibs * $perccbs / 100, 2);
    $valoribsuf = round($valorbasecbsibs * $percibsuf / 100, 2);
    $valorcbsibs = $valorcbs + $valoribsuf;
    $quantmoedaunitario = round($valorUnitario, 2);
    $quantmoedatotal = round($qt * $valorUnitario, 2);
    
    // Só as colunas que a regra do ERP preenche; as demais usam o DEFAULT da tabela
    $cols = [
        'idpedido' => $idpedido, 'iditempedido' => $iditempedido, 'idcodempresa' => 1, 'idfilial' => 1,
        'iditem' => $iditem, 'idclassfiscal' => $idclassfiscal, 'idsituacaotributaria' => $idsituacaotributaria,
        'qt' => $qt, 'qtsaldo' => $qt, 'qtfaturado' => 0, 'qtpesoliquido' => $qtpesoliquido, 'qtpesobruto' => $qtpesobruto,
        'valor' => $valorUnitario, 'valortotal' => $valortotal, 'valortotalcomdesconto' => $valortotalcomdesconto,
        'valorcustoreposicao' => $valorcustocontabil, 'valorpauta' => $valorUnitario,
        'idunidade' => (int)($dadosItem['idunidade'] ?? 1), 'idunidade_impressao' => (int)($dadosItem['idunidade'] ?? 1),
        'perc_ipi' => $percIpi, 'perc_icms' => $percIcms, 'valoricms' => $valoricms,
        'valorbaseicms' => $valortotal, 'perc_icmsuf_st' => $percIcms, 'percicmsufdestino' => $percIcms,
        'codigolido' => $referencia, 'complemento' => $complemento,
        'perc_margem' => $percmargem, 'perc_margem_cg' => $percmargem, 'perc_margem_cm' => 100,
        'valorcustogerencial' => $custogerencial,
        'valorprecominimo' => $valorUnitario, 'quant_impressao' => 1, 'fator_conversao' => $fatorConversao ?: 1,
        'valorunitarioimpressao' => $valorUnitario, 'valorsugerido' => $valorUnitario, 'valorprecovendadia' => $valorUnitario,
        'valor_total_com_campanha' => $valortotal, 'valorunit_comst' => $valorUnitario, 'valortotal_comst' => $valortotal,
        'tipo_conversao' => 1, 'quant_original' => $qt, 'quant_reserva' => $qt, 'qtsaldoconvertido' => $qt, 'qtsaldogerouop' => $qt,
        'reserva_estoque' => 'S', 'iddeposito' => 1,
        'pesobrutooriginal' => $qtpesobruto, 'pesoliquidooriginal' => $qtpesoliquido,
        'idimpostovenda' => (int)($dadosItem['idimposto'] ?? 0),
        'idsittrib_ipi' => (int)($dadosItem['idsittrib_ipi'] ?? 0),
        'idsittrib_pis' => (int)($dadosItem['idsittrib_pis'] ?? 0), 'idsittrib_cofins' => (int)($dadosItem['idsittrib_cofins'] ?? 0),
        'valorbasepis' => $valorbasepis, 'valorpis' => $valorpis, 'percpis' => $percCredPis,
        'valorbasecofins' => $valorbasecofins, 'valorcofins' => $valorcofins, 'perccofins' => $percCredCofins,
        'idtributo' => (int)($dadosItem['idtributo'] ?? 1), 'perccbs' => $perccbs, 'percibsuf' => $percibsuf,
        'valorbasecbsibs' => $valorbasecbsibs, 'valorcbs' => $valorcbs, 'valoribsuf' => $valoribsuf,
        'valoribs' => $valoribsuf, 'valorcbsibs' => $valorcbsibs,
        'quantmoedaunitario' => $quantmoedaunitario, 'quantmoedatotal' => $quantmoedatotal,
        'ativo' => 'S',
    ];
    $nomes = implode(', ', array_keys($cols));
    $marcas = implode(', ', array_fill(0, count($cols), '?')) . ', NOW()';
    $stmt = $this->pdo->prepare("INSERT INTO pedido_item ($nomes, datahora) VALUES ($marcas)");
    $stmt->execute(array_values($cols));
        return $iditempedido;
}
    // ==========================================================================
    // MÓDULO 1: RETORNO ITEM (Bipagem rápida)
    // ==========================================================================
    
    /**
     * GET /v1/retorno/tipos-destino
     * Retorna os 3 tipos de destino disponíveis
     */
    public function getTiposDestino(Request $request, Response $response): Response
    {
        $tipos = [
            ['id' => 10117, 'nome' => 'CONTAINER', 'descricao' => 'Estoque provisório - Container', 'cor' => '#10b981'],
            ['id' => 16595, 'nome' => 'MATÉRIA-PRIMA', 'descricao' => 'Estoque provisório - Matéria-prima', 'cor' => '#3b82f6'],
            ['id' => 16596, 'nome' => 'REVENDA', 'descricao' => 'Estoque provisório - Revenda', 'cor' => '#f59e0b'],
            ['id' => 16688, 'nome' => 'TROCAS FORNECEDOR', 'descricao' => 'Estoque provisório - Trocas fornecedor', 'cor' => '#8b5cf6']
        ];
        
        $payload = json_encode($tipos);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json');
    }
    
    /** Garante que o pedido pertence a um dos clientes de estoque do retorno */
    private function pedidoEhEstoqueRetorno(int $idpedido): bool
    {
        $stmt = $this->pdo->prepare("SELECT idcliforemp FROM pedido WHERE idpedido = ?");
        $stmt->execute([$idpedido]);
        $c = $stmt->fetchColumn();
        return $c !== false && isset($this->destinos[(int)$c]);
    }

    /**
     * GET /v1/retorno-embarque/destinos
     * Pedidos ABERTOS (status = 1) dos clientes de destino do retorno
     */
    public function getDestinosAbertos(Request $request, Response $response): Response
    {
        $ids = array_keys($this->destinos);
        $in = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("
            SELECT p.idpedido, p.idcliforemp, c.fantasia, p.observacao, p.data,
                   (SELECT COUNT(*) FROM pedido_item i WHERE i.idpedido = p.idpedido AND i.ativo = 'S' AND i.iditem <> 2181) AS itens
            FROM pedido p
            JOIN cliforemp c ON c.idcliforemp = p.idcliforemp
            WHERE p.status = 1 AND p.idcliforemp IN ($in)
            ORDER BY p.idcliforemp, p.idpedido DESC
        ");
        $stmt->execute($ids);
        $lista = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
            $d = $this->destinos[(int)$r['idcliforemp']];
            $lista[] = [
                'idpedido' => (int)$r['idpedido'],
                'idcliforemp' => (int)$r['idcliforemp'],
                'nome' => $d['nome'],
                'cor' => $d['cor'],
                'fantasia' => trim((string)$r['fantasia']),
                'observacao' => $r['observacao'],
                'data' => $r['data'],
                'itens' => (int)$r['itens'],
            ];
        }
        $response->getBody()->write(json_encode(['success' => true, 'data' => $lista]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * GET /v1/retorno-embarque/{idembarque}
     * Comparativo por item: carregado (+ extras de subida) x devolvido no acerto x já destinado
     */
    public function getComparativoEmbarque(Request $request, Response $response, array $args): Response
    {
        $idembarque = (int)($args['idembarque'] ?? 0);
        if ($idembarque <= 0) {
            $response->getBody()->write(json_encode(['success' => false, 'error' => 'Embarque inválido']));
            return $response->withStatus(422)->withHeader('Content-Type', 'application/json');
        }

        $todos = ($request->getQueryParams()['todos'] ?? '') === '1';
        $existe = fn($t) => (bool)$this->pdo->query("SELECT to_regclass('public.$t')")->fetchColumn();

        $stmt = $this->pdo->prepare("
            SELECT c.iditem, SUM(c.qt_carregada) AS carregado
            FROM pedido_item_carregamento c WHERE c.idembarque = ? GROUP BY c.iditem
        ");
        $stmt->execute([$idembarque]);
        $itens = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
            $itens[(int)$r['iditem']] = ['carregado' => (float)$r['carregado'], 'extra' => 0.0, 'devolvido_acerto' => 0.0, 'destinado' => 0.0];
        }

        if ($existe('carregamento_subida_extras')) {
            $stmt = $this->pdo->prepare("SELECT iditem, SUM(quantidade) AS q FROM carregamento_subida_extras WHERE idembarque = ? GROUP BY iditem");
            $stmt->execute([$idembarque]);
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                $id = (int)$r['iditem'];
                $itens[$id] = $itens[$id] ?? ['carregado' => 0.0, 'extra' => 0.0, 'devolvido_acerto' => 0.0, 'destinado' => 0.0];
                $itens[$id]['extra'] = (float)$r['q'];
            }
        }

        $stmt = $this->pdo->prepare("
            SELECT ai.item_erp_id AS iditem, SUM(ai.quantidade_devolvida) AS q
            FROM frota_acerto_item ai
            JOIN frota_acerto_pedido ap ON ap.id = ai.acerto_pedido_id
            WHERE ap.embarque_id = ? GROUP BY ai.item_erp_id
        ");
        $stmt->execute([$idembarque]);
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
            $id = (int)$r['iditem'];
            $itens[$id] = $itens[$id] ?? ['carregado' => 0.0, 'extra' => 0.0, 'devolvido_acerto' => 0.0, 'destinado' => 0.0];
            $itens[$id]['devolvido_acerto'] = (float)$r['q'];
        }

        if ($existe('retorno_carga') && $this->pdo->query("SELECT 1 FROM information_schema.columns WHERE table_name = 'retorno_carga' AND column_name = 'idembarque'")->fetchColumn()) {
            $stmt = $this->pdo->prepare("
                SELECT iditem, SUM(quant) AS q FROM retorno_carga
                WHERE tipo_movimentacao = 'ENTRADA' AND idembarque = ? GROUP BY iditem
            ");
            $stmt->execute([$idembarque]);
        } else {
            $stmt = $this->pdo->prepare("
                SELECT iditem, SUM(quant) AS q FROM retorno_carga
                WHERE tipo_movimentacao = 'ENTRADA' AND observacao LIKE ? GROUP BY iditem
            ");
            $stmt->execute(['DESTINAR DO EMBARQUE #' . $idembarque . '%']);
        }
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
            $id = (int)$r['iditem'];
            $itens[$id] = $itens[$id] ?? ['carregado' => 0.0, 'extra' => 0.0, 'devolvido_acerto' => 0.0, 'destinado' => 0.0];
            $itens[$id]['destinado'] = (float)$r['q'];
        }

        $lista = [];
        if ($itens) {
            $in = implode(',', array_fill(0, count($itens), '?'));
            $stmt = $this->pdo->prepare("SELECT iditem, descricao, referencia, path_foto_master FROM item WHERE iditem IN ($in)");
            $stmt->execute(array_keys($itens));
            $desc = [];
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r) $desc[(int)$r['iditem']] = $r;
            foreach ($itens as $id => $v) {
                // Esperado de volta: o que o acerto apontou ou o extra de subida (o que for maior, sem duplicar)
                $esperado = max($v['devolvido_acerto'], $v['extra']);
                if (!$todos && $esperado <= 0) continue;
                $pendente = max(0, $esperado - $v['destinado']);
                $lista[] = [
                    'esperado' => $esperado,
                    'iditem' => $id,
                    'descricao' => $desc[$id]['descricao'] ?? "Item $id",
                    'referencia' => $desc[$id]['referencia'] ?? null,
                    'foto' => $desc[$id]['path_foto_master'] ?? null,
                    'carregado' => $v['carregado'],
                    'extra_subida' => $v['extra'],
                    'devolvido_acerto' => $v['devolvido_acerto'],
                    'destinado' => $v['destinado'],
                    'pendente_destinar' => $pendente,
                ];
            }
            usort($lista, fn($a, $b) => [$b['pendente_destinar'], $a['descricao']] <=> [$a['pendente_destinar'], $b['descricao']]);
        }

        $response->getBody()->write(json_encode(['success' => true, 'idembarque' => $idembarque, 'todos' => $todos, 'total_itens_embarque' => count($itens), 'itens' => $lista]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Localiza o item por: qualquer código de barras, referência (1..3, ignorando zeros
     * à esquerda) ou ID. Itens ativos têm prioridade, mas inativos também retornam
     * (mercadoria devolvida pode ser de item já inativado).
     */
    private function localizarProduto(string $busca): ?array
    {
        $busca = trim($busca);
        $semZeros = ltrim($busca, '0');
        $base = "
            SELECT i.iditem, i.descricao, i.referencia, i.pesobruto, i.pesoliquido,
                   ef.valorprecovenda, i.path_foto_master,
                   COALESCE(
                       (SELECT cb.idbarra FROM codigo_barra cb WHERE cb.iditem = i.iditem AND cb.principal = 'S' LIMIT 1),
                       (SELECT cb.idbarra FROM codigo_barra cb WHERE cb.iditem = i.iditem LIMIT 1),
                       'S/COD'
                   ) AS cod_barras
            FROM item i
            LEFT JOIN estoque_filial ef ON ef.iditem = i.iditem AND ef.idfilial = 1
        ";
        $tentativas = [
            ["WHERE EXISTS (SELECT 1 FROM codigo_barra x WHERE x.iditem = i.iditem AND x.idbarra = ?)", [$busca]],
            ["WHERE UPPER(TRIM(i.referencia)) = UPPER(?)", [$busca]],
        ];
        if (ctype_digit($busca) && strlen($busca) <= 9) {
            $tentativas[] = ["WHERE i.iditem = ?", [(int)$busca]];
        }
        if ($semZeros !== '') {
            $tentativas[] = ["WHERE LTRIM(TRIM(i.referencia), '0') = ?", [$semZeros]];
        }
        $tentativas[] = ["WHERE UPPER(TRIM(i.referencia1)) = UPPER(?) OR UPPER(TRIM(i.referencia2)) = UPPER(?) OR UPPER(TRIM(i.referencia3)) = UPPER(?)", [$busca, $busca, $busca]];
        foreach ($tentativas as [$where, $params]) {
            $stmt = $this->pdo->prepare($base . $where . " ORDER BY (i.inativo = 'N') DESC, i.iditem LIMIT 1");
            $stmt->execute($params);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row) return $row;
        }
        return null;
    }
    /**
     * POST /v1/retorno/buscar-produto
     * Busca produto por código de barras
     */
    public function buscarProduto(Request $request, Response $response): Response
    {
        $input = json_decode($request->getBody()->getContents(), true);
        $busca = trim($input['busca'] ?? '');
        
        if (empty($busca)) {
            $response->getBody()->write(json_encode(['error' => 'Código inválido']));
            return $response->withStatus(400);
        }
        
        try {
            $produto = $this->localizarProduto($busca);
            if (!$produto) {
                $response->getBody()->write(json_encode(['error' => 'Produto não encontrado']));
                return $response->withStatus(404);
            }
            
            $produto['foto_url'] = $this->processarImagem($produto['path_foto_master'] ?? null);
            $produto['foto'] = $produto['path_foto_master'] ?? null;
            unset($produto['path_foto_master']);
            
            $payload = json_encode($produto);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500);
        }
    }
    
    /**
     * GET /v1/retorno/produto-existe/{idpedido}/{iditem}
     * Verifica se produto existe no pedido e retorna seus dados
     */
    public function produtoExisteNoPedido(Request $request, Response $response, array $args): Response
    {
        $idpedido = (int)($args['idpedido'] ?? 0);
        $iditem = (int)($args['iditem'] ?? 0);
        
        if ($idpedido <= 0 || $iditem <= 0) {
            $response->getBody()->write(json_encode(['existe' => false]));
            return $response->withHeader('Content-Type', 'application/json');
        }
        
        try {
            $stmt = $this->pdo->prepare("
                SELECT iditempedido, qt as quantidade_atual, 
                       (qt * qtpesoliquido) as peso_total,
                       qtpesoliquido as peso_unitario,
                       valortotal as valor_total
                FROM pedido_item 
                WHERE idpedido = ? AND iditem = ? AND ativo = 'S'
            ");
            $stmt->execute([$idpedido, $iditem]);
            $dados = $stmt->fetch();
            
            $payload = json_encode([
                'existe' => $dados ? true : false,
                'dados' => $dados
            ]);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage(), 'existe' => false]));
            return $response->withStatus(500);
        }
    }
    
   /**
 * POST /v1/retorno/movimentar
 * Movimenta produto no estoque (ENTRADA/SAIDA/AJUSTE)
 */
public function movimentar(Request $request, Response $response): Response
{
    $input = json_decode($request->getBody()->getContents(), true);
    
    $idcliforemp_destino = (int)($input['idcliforemp_destino'] ?? 0);
    $iditem = (int)($input['iditem'] ?? 0);
    $tipo = $input['tipo'] ?? 'ENTRADA';
    $quantidade = (float)($input['quantidade'] ?? 0);
    $nova_quantidade = isset($input['nova_quantidade']) ? (float)$input['nova_quantidade'] : null;
    $peso_real = isset($input['peso_real']) ? (float)$input['peso_real'] : null;
    $lote = $input['lote'] ?? null;
    $validade = $input['validade'] ?? null;
    $motivo = $input['motivo'] ?? 'OUTROS';
    $observacao = $input['observacao'] ?? null;
    $idusuario = (int)(($request->getAttribute('user') ?? [])['idusuario'] ?? ($input['idusuario'] ?? 0));
    $cod_barras = $input['cod_barras'] ?? null;
    $idembarque = (int)($input['idembarque'] ?? 0);
    
    if ($idcliforemp_destino <= 0 || $iditem <= 0) {
        $response->getBody()->write(json_encode(['error' => 'Dados inválidos']));
        return $response->withStatus(400);
    }
    
    if (!isset($this->destinos[$idcliforemp_destino])) {
        $response->getBody()->write(json_encode(['error' => 'Destino não permitido']));
        return $response->withStatus(422);
    }
    
    if (!in_array($tipo, ['ENTRADA', 'SAIDA', 'AJUSTE'], true)) {
        $response->getBody()->write(json_encode(['error' => 'Tipo de movimentação inválido']));
        return $response->withStatus(422);
    }
    
    if ($tipo === 'AJUSTE' && ($nova_quantidade === null || $nova_quantidade < 0)) {
        $response->getBody()->write(json_encode(['error' => 'Nova quantidade inválida']));
        return $response->withStatus(422);
    }
    
    if ($tipo != 'AJUSTE' && $quantidade <= 0) {
        $response->getBody()->write(json_encode(['error' => 'Quantidade inválida']));
        return $response->withStatus(400);
    }
    
    try {
        $this->pdo->beginTransaction();
        
        // Obtém ou cria o pedido ativo para o destino
        $pedido = $this->getPedidoAberto($idcliforemp_destino);
        $idpedido = $pedido['idpedido'];
        
        // Busca produto no pedido
        $stmt = $this->pdo->prepare("
            SELECT iditempedido, qt, qtpesoliquido, qtpesobruto, valor, valortotal
            FROM pedido_item 
            WHERE idpedido = ? AND iditem = ? AND ativo = 'S'
            FOR UPDATE
        ");
        $stmt->execute([$idpedido, $iditem]);
        $produtoPedido = $stmt->fetch();
        
        $quantidade_atual = $produtoPedido ? (float)$produtoPedido['qt'] : 0;
        
        // ================================================================
        // Busca dados completos do item
        // ================================================================
        $dadosItem = $this->getDadosItemCompleto($iditem, 1);
        $valorUnitario = (float)$dadosItem['valorprecovenda'];
        $pesoLiquidoUnitario = (float)$dadosItem['pesoliquido'];
        $pesoBrutoUnitario = (float)$dadosItem['pesobruto'];
        $descricaoItem = $dadosItem['descricao'];
        $percIcms = (float)$dadosItem['perc_icms'];
        $percCredPis = (float)$dadosItem['perc_cred_pis'];
        $percCredCofins = (float)$dadosItem['perc_cred_cofins'];
        $perccbs = (float)$dadosItem['perccbs'];
        $percibsuf = (float)$dadosItem['percibsuf'];
        $percIpi = (float)$dadosItem['perc_ipi'];
        $percmargem = (float)$dadosItem['percmargem'];
        $custogerencial = (float)$dadosItem['custogerencial'];
        $valorcustocontabil = (float)$dadosItem['valorcustocontabil'];
        $idclassfiscal = (int)$dadosItem['idclassfiscal'];
        $idsituacaotributaria = (int)$dadosItem['idsituacaotributaria'];
        $referencia = $dadosItem['referencia'];
        $complemento = $dadosItem['complemento'];
        
        // ================================================================
        // Calcula nova quantidade e direção
        // ================================================================
        if ($tipo == 'AJUSTE') {
            $quantidade_movimentada = abs($nova_quantidade - $quantidade_atual);
            $quantidade_depois = $nova_quantidade;
            $direcao = $nova_quantidade > $quantidade_atual ? 'ENTRADA' : 'SAIDA';
            $quantidade_para_registro = $quantidade_movimentada;
        } else {
            $quantidade_para_registro = $quantidade;
            if ($tipo == 'ENTRADA') {
                $quantidade_depois = $quantidade_atual + $quantidade;
                $direcao = 'ENTRADA';
            } else {
                if ($quantidade > $quantidade_atual) {
                    throw new \Exception("Quantidade insuficiente. Disponível: {$quantidade_atual}");
                }
                $quantidade_depois = $quantidade_atual - $quantidade;
                $direcao = 'SAIDA';
            }
        }
        
        // ================================================================
        // ATUALIZA OU INSERE NO pedido_item
        // ================================================================
        if ($produtoPedido) {
            // ============================================================
            // ATUALIZA ITEM EXISTENTE - recalcula todos os valores
            // ============================================================
            $novoValorTotal = round($quantidade_depois * $valorUnitario, 2);
            $novoPesoLiquido = round($quantidade_depois * $pesoLiquidoUnitario, 4);
            $novoPesoBruto = round($quantidade_depois * $pesoBrutoUnitario, 4);
            $novoValorIcms = round($quantidade_depois * $valorUnitario * $percIcms / 100, 2);
            $novaBasePis = round($quantidade_depois * $valorUnitario * 0.88, 2);
            $novoValorPis = round($novaBasePis * $percCredPis / 100, 2);
            $novaBaseCofins = round($quantidade_depois * $valorUnitario * 0.88, 2);
            $novoValorCofins = round($novaBaseCofins * $percCredCofins / 100, 2);
            $novaBaseCbsibs = round($quantidade_depois * $valorUnitario * 0.80, 2);
            $novoValorCbs = round($novaBaseCbsibs * $perccbs / 100, 2);
            $novoValorIbsuf = round($novaBaseCbsibs * $percibsuf / 100, 2);
            $novoValorCbsibs = $novoValorCbs + $novoValorIbsuf;
            
            $this->aplicarQuantidadePedidoItem((int)$produtoPedido['iditempedido'], (int)$idpedido, $iditem, (float)$quantidade_depois);
            
        } else {
            // ============================================================
            // INSERE NOVO ITEM - usando a função completa (TODAS as colunas)
            // ============================================================
            $this->inserirItemCompleto($idpedido, $iditem, $quantidade_depois, $dadosItem);
        }
        
        // ================================================================
        // REGISTRA NO RETORNO_CARGA
        // ================================================================
        $temColunaEmbarque = $idembarque > 0 && (bool)$this->pdo->query(
            "SELECT 1 FROM information_schema.columns WHERE table_name = 'retorno_carga' AND column_name = 'idembarque'"
        )->fetchColumn();
        $sqlMov = "
            INSERT INTO retorno_carga (
                idpedido, iditem, idcliforemp_destino, tipo_movimentacao,
                quant, quantidade_antes, quantidade_depois,
                peso_real, lote, validade, motivo, observacao,
                idusuario, nome_item, cod_barras, data_hora, data_hora_registro"
                . ($temColunaEmbarque ? ", idembarque" : "") . "
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW()"
                . ($temColunaEmbarque ? ", ?" : "") . ")
            RETURNING id
        ";
        
        $params = [
            $idpedido, $iditem, $idcliforemp_destino, $direcao,
            $quantidade_para_registro, $quantidade_atual, $quantidade_depois,
            $peso_real, $lote, $validade, $motivo, $observacao,
            $idusuario, $descricaoItem, $cod_barras
        ];
        if ($temColunaEmbarque) $params[] = $idembarque;
        $stmt = $this->pdo->prepare($sqlMov);
        $stmt->execute($params);
        $idMovimentacao = $stmt->fetch()['id'];
        
        // ================================================================
        // ATUALIZA TOTAIS DO PEDIDO
        // ================================================================
        $this->sincronizarPlaceholder((int)$idpedido);
        $this->atualizarTotaisPedido($idpedido);
        
        $this->pdo->commit();
        
        $payload = json_encode([
            'success' => true,
            'id_movimentacao' => $idMovimentacao,
            'idpedido' => $idpedido,
            'pedido_numero' => $pedido['numero'],
            'quantidade_atual' => $quantidade_depois,
            'message' => $tipo == 'ENTRADA' ? 'Entrada registrada!' : ($tipo == 'SAIDA' ? 'Saída registrada!' : 'Ajuste realizado!')
        ]);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json');
        
    } catch (\Exception $e) {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
        error_log('[RetornoController] Erro em movimentar: ' . $e->getMessage());
        $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
        return $response->withStatus($e instanceof \DomainException ? 409 : 500);
    }
}
    
    /**
     * POST /v1/retorno/upload-foto
     */
    public function uploadFoto(Request $request, Response $response): Response
    {
        $uploadedFiles = $request->getUploadedFiles();
        
        if (empty($uploadedFiles['foto'])) {
            $response->getBody()->write(json_encode(['error' => 'Nenhuma foto enviada']));
            return $response->withStatus(400);
        }
        
        $foto = $uploadedFiles['foto'];
        $params = $request->getParsedBody();
        $idMovimentacao = (int)($params['id_movimentacao'] ?? 0);
        
        if ($idMovimentacao <= 0) {
            $response->getBody()->write(json_encode(['error' => 'ID da movimentação inválido']));
            return $response->withStatus(400);
        }
        
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
        if (!in_array($foto->getClientMediaType(), $allowedTypes)) {
            $response->getBody()->write(json_encode(['error' => 'Tipo de arquivo não permitido']));
            return $response->withStatus(400);
        }
        
        if ($foto->getError() !== UPLOAD_ERR_OK || $foto->getSize() > 10 * 1024 * 1024) {
            $response->getBody()->write(json_encode(['error' => 'Arquivo inválido ou maior que 10MB']));
            return $response->withStatus(400);
        }
        
        // Extensão derivada do conteúdo real, nunca do nome enviado
        $tmp = $foto->getStream()->getMetadata('uri');
        $mimeReal = $tmp ? (new \finfo(FILEINFO_MIME_TYPE))->file($tmp) : null;
        $extPorMime = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($extPorMime[$mimeReal])) {
            $response->getBody()->write(json_encode(['error' => 'Conteúdo do arquivo não é uma imagem válida']));
            return $response->withStatus(400);
        }
        $ext = $extPorMime[$mimeReal];
        $nomeArquivo = sprintf('movimentacao_%d_%s_%s.%s', $idMovimentacao, date('Ymd_His'), bin2hex(random_bytes(4)), $ext);
        $caminhoRelativo = 'uploads/retorno/' . $nomeArquivo;
        $caminhoAbsoluto = __DIR__ . '/../../../uploads/retorno/' . $nomeArquivo;
        
        $diretorio = dirname($caminhoAbsoluto);
        if (!is_dir($diretorio)) {
            mkdir($diretorio, 0755, true);
        }
        
        try {
            $foto->moveTo($caminhoAbsoluto);
            
            $stmt = $this->pdo->prepare("
                UPDATE retorno_carga SET path_foto = ? WHERE id = ?
            ");
            $stmt->execute([$caminhoRelativo, $idMovimentacao]);
            
            $payload = json_encode([
                'success' => true,
                'message' => 'Foto anexada com sucesso!',
                'path' => $caminhoRelativo
            ]);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            if (file_exists($caminhoAbsoluto)) {
                @unlink($caminhoAbsoluto);
            }
            $response->getBody()->write(json_encode(['error' => 'Erro ao salvar foto: ' . $e->getMessage()]));
            return $response->withStatus(500);
        }
    }
    
    /**
     * GET /v1/retorno/movimentacoes-hoje
     * Lista movimentações do dia atual
     */
    public function getMovimentacoesHoje(Request $request, Response $response): Response
    {
        try {
            $sql = "
                SELECT 
                    rc.id,
                    rc.idpedido,
                    rc.iditem,
                    rc.tipo_movimentacao,
                    rc.quant,
                    rc.quantidade_antes,
                    rc.quantidade_depois,
                    rc.peso_real,
                    rc.lote,
                    rc.validade,
                    rc.motivo,
                    rc.observacao,
                    rc.nome_item,
                    rc.cod_barras,
                    rc.data_hora,
                    rc.path_foto,
                    u.username as nome_usuario,
                    CASE 
                        WHEN rc.idcliforemp_destino = 10117 THEN 'CONTAINER'
                        WHEN rc.idcliforemp_destino = 16595 THEN 'MATÉRIA-PRIMA'
                        WHEN rc.idcliforemp_destino = 16596 THEN 'REVENDA'
                        WHEN rc.idcliforemp_destino = 16688 THEN 'TROCAS FORNECEDOR'
                        ELSE 'ESTOQUE'
                    END as destino_nome
                FROM retorno_carga rc
                LEFT JOIN usuario u ON u.idcliforemp = rc.idusuario
                WHERE DATE(rc.data_hora) = CURRENT_DATE
                ORDER BY rc.data_hora DESC
                LIMIT 100
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            $movimentacoes = $stmt->fetchAll();
            
            foreach ($movimentacoes as &$mov) {
                $mov['quant'] = (float)$mov['quant'];
                $mov['peso_real'] = $mov['peso_real'] ? (float)$mov['peso_real'] : null;
                $mov['quantidade_antes'] = $mov['quantidade_antes'] ? (float)$mov['quantidade_antes'] : null;
                $mov['quantidade_depois'] = $mov['quantidade_depois'] ? (float)$mov['quantidade_depois'] : null;
            }
            
            $payload = json_encode($movimentacoes ?: []);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            error_log('[RetornoController] Erro em getMovimentacoesHoje: ' . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500);
        }
    }
    
    // ==========================================================================
    // MÓDULO 2: AUDITORIA DE ESTOQUE
    // ==========================================================================
    
    /**
     * GET /v1/auditoria-estoque/estoque-ativo/{idcliforemp}
     * Retorna o estoque ativo com produtos e movimentações
     */
    public function getEstoqueAtivo(Request $request, Response $response, array $args): Response
    {
        $idcliforemp = (int)($args['idcliforemp'] ?? 0);
        $idusuario = (int)($request->getQueryParams()['idusuario'] ?? 0);
        
        if ($idcliforemp <= 0 || !isset($this->destinos[$idcliforemp])) {
            $response->getBody()->write(json_encode(['error' => 'Estoque inválido']));
            return $response->withStatus(400);
        }
        
        try {
            $pedido = $this->getPedidoAberto($idcliforemp);
            
            // Busca produtos
            $stmt = $this->pdo->prepare("
                SELECT 
                    pi.iditem, pi.iditempedido, pi.qt as quantidade_atual,
                    pi.qtpesoliquido as peso_unitario, (pi.qt * pi.qtpesoliquido) as peso_total,
                    pi.valor as valor_unitario, pi.valortotal as valor_total,
                    i.descricao as nome_item, i.referencia,
                    COALESCE(cb.idbarra, 'S/COD') as cod_barras,
                    i.path_foto_master as foto
                FROM pedido_item pi
                JOIN item i ON i.iditem = pi.iditem
                LEFT JOIN codigo_barra cb ON cb.iditem = i.iditem AND cb.principal = 'S'
                WHERE pi.idpedido = ? AND pi.ativo = 'S' AND i.inativo = 'N' AND pi.iditem <> 2181
                ORDER BY pi.iditempedido
            ");
            $stmt->execute([$pedido['idpedido']]);
            $produtos = $stmt->fetchAll();
            
            foreach ($produtos as &$produto) {
                $produto['foto_url'] = $this->processarImagem($produto['foto'] ?? null);
                unset($produto['foto']);
            }
            
            // Busca últimas movimentações
            $stmt = $this->pdo->prepare("
                SELECT 
                    rc.id, rc.iditem, rc.tipo_movimentacao, rc.quant, rc.peso_real,
                    rc.motivo, rc.observacao, rc.data_hora, rc.nome_item, rc.lote, rc.validade,
                    u.username as nome_usuario,
                    CASE 
                        WHEN rc.tipo_movimentacao = 'ENTRADA' THEN 'success'
                        WHEN rc.tipo_movimentacao = 'SAIDA' THEN 'danger'
                        ELSE 'warning'
                    END as tipo_cor
                FROM retorno_carga rc
                LEFT JOIN usuario u ON u.idcliforemp = rc.idusuario
                WHERE rc.idpedido = ?
                ORDER BY rc.data_hora DESC
                LIMIT 50
            ");
            $stmt->execute([$pedido['idpedido']]);
            $movimentacoes = $stmt->fetchAll();
            
            $payload = json_encode([
                'estoque' => [
                    'id' => $idcliforemp,
                    'nome' => $this->destinos[$idcliforemp]['nome'],
                    'cor' => $this->destinos[$idcliforemp]['cor']
                ],
                'pedido' => $pedido,
                'produtos' => $produtos,
                'movimentacoes' => $movimentacoes
            ]);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\DomainException $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage(), 'codigo' => 'SEM_PEDIDO_ABERTO']));
            return $response->withStatus(409)->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            error_log('[RetornoController] Erro em getEstoqueAtivo: ' . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500);
        }
    }
    
    /**
     * GET /v1/auditoria-estoque/movimentacoes-produto/{idpedido}/{iditem}
     * Retorna todas as movimentações de um produto específico
     */
    public function getMovimentacoesProduto(Request $request, Response $response, array $args): Response
    {
        $idpedido = (int)($args['idpedido'] ?? 0);
        $iditem = (int)($args['iditem'] ?? 0);
        
        if ($idpedido <= 0 || $iditem <= 0) {
            $response->getBody()->write(json_encode([]));
            return $response->withHeader('Content-Type', 'application/json');
        }
        
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    rc.id, rc.tipo_movimentacao, rc.quant, rc.peso_real,
                    rc.quantidade_antes, rc.quantidade_depois,
                    rc.motivo, rc.observacao, rc.data_hora, rc.lote, rc.validade,
                    rc.path_foto,
                    u.username as nome_usuario,
                    CASE 
                        WHEN rc.tipo_movimentacao = 'ENTRADA' THEN 'success'
                        WHEN rc.tipo_movimentacao = 'SAIDA' THEN 'danger'
                        ELSE 'warning'
                    END as tipo_cor
                FROM retorno_carga rc
                LEFT JOIN usuario u ON u.idcliforemp = rc.idusuario
                WHERE rc.idpedido = ? AND rc.iditem = ?
                ORDER BY rc.data_hora DESC
            ");
            $stmt->execute([$idpedido, $iditem]);
            $movimentacoes = $stmt->fetchAll();
            
            $payload = json_encode($movimentacoes);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500);
        }
    }
    
    /**
     * GET /v1/auditoria-estoque/movimentacoes-pedido/{idpedido}
     * Retorna todas as movimentações de um pedido
     */
    public function getMovimentacoesPedido(Request $request, Response $response, array $args): Response
    {
        $idpedido = (int)($args['idpedido'] ?? 0);
        
        if ($idpedido <= 0) {
            $response->getBody()->write(json_encode([]));
            return $response->withHeader('Content-Type', 'application/json');
        }
        
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    rc.*,
                    u.username as nome_usuario,
                    CASE 
                        WHEN rc.tipo_movimentacao = 'ENTRADA' THEN 'success'
                        WHEN rc.tipo_movimentacao = 'SAIDA' THEN 'danger'
                        ELSE 'warning'
                    END as tipo_cor
                FROM retorno_carga rc
                LEFT JOIN usuario u ON u.idcliforemp = rc.idusuario
                WHERE rc.idpedido = ?
                ORDER BY rc.data_hora DESC
            ");
            $stmt->execute([$idpedido]);
            $movimentacoes = $stmt->fetchAll();
            
            $payload = json_encode($movimentacoes);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500);
        }
    }
    
    /**
 * POST /v1/auditoria-estoque/ajustar-produto
 * Ajusta os dados de um produto existente (edição)
 */
public function ajustarProduto(Request $request, Response $response): Response
{
    $input = json_decode($request->getBody()->getContents(), true);
    
    $idpedido = (int)($input['idpedido'] ?? 0);
    $iditem = (int)($input['iditem'] ?? 0);
    $nova_quantidade = (float)($input['nova_quantidade'] ?? 0);
    $peso_real = isset($input['peso_real']) ? (float)$input['peso_real'] : null;
    $lote = $input['lote'] ?? null;
    $validade = $input['validade'] ?? null;
    $motivo = $input['motivo'] ?? 'AJUSTE_INVENTARIO';
    $observacao = $input['observacao'] ?? null;
    $idusuario = (int)(($request->getAttribute('user') ?? [])['idusuario'] ?? ($input['idusuario'] ?? 0));
    
    if ($idpedido <= 0 || $iditem <= 0 || $nova_quantidade <= 0) {
        $response->getBody()->write(json_encode(['error' => 'Dados inválidos']));
        return $response->withStatus(400);
    }
    
    if (!$this->pedidoEhEstoqueRetorno($idpedido)) {
        $response->getBody()->write(json_encode(['error' => 'Pedido não pertence ao estoque de retorno']));
        return $response->withStatus(422);
    }
    
    try {
        $this->pdo->beginTransaction();
        
        // Busca quantidade atual
        $stmt = $this->pdo->prepare("
            SELECT iditempedido, qt, qtpesoliquido, valor 
            FROM pedido_item 
            WHERE idpedido = ? AND iditem = ? AND ativo = 'S'
            FOR UPDATE
        ");
        $stmt->execute([$idpedido, $iditem]);
        $produtoPedido = $stmt->fetch();
        
        if (!$produtoPedido) {
            throw new \Exception("Produto não encontrado no pedido");
        }
        
        $quantidade_atual = (float)$produtoPedido['qt'];
        $quantidade_movimentada = abs($nova_quantidade - $quantidade_atual);
        $direcao = $nova_quantidade > $quantidade_atual ? 'ENTRADA' : 'SAIDA';
        
        // Busca informações do item
        $stmt = $this->pdo->prepare("
            SELECT i.descricao, i.pesoliquido, i.pesobruto, 
                   COALESCE(ef.valorprecovenda, 0) as valor
            FROM item i
            LEFT JOIN estoque_filial ef ON ef.iditem = i.iditem AND ef.idfilial = 1
            WHERE i.iditem = ?
        ");
        $stmt->execute([$iditem]);
        $itemInfo = $stmt->fetch();
        
        // Atualiza quantidade
        $this->aplicarQuantidadePedidoItem((int)$produtoPedido['iditempedido'], (int)$idpedido, $iditem, (float)$nova_quantidade);
        
        // Registra o ajuste no retorno_carga
        $sqlMov = "
            INSERT INTO retorno_carga (
                idpedido, iditem, idcliforemp_destino, tipo_movimentacao,
                quant, quantidade_antes, quantidade_depois,
                peso_real, lote, validade, motivo, observacao,
                idusuario, nome_item, data_hora, data_hora_registro
            ) VALUES (?, ?, (SELECT idcliforemp FROM pedido WHERE idpedido = ?), 
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            RETURNING id
        ";
        
        $stmt = $this->pdo->prepare($sqlMov);
        $stmt->execute([
            $idpedido, $iditem, $idpedido,
            $direcao, $quantidade_movimentada, $quantidade_atual, $nova_quantidade,
            $peso_real, $lote, $validade, $motivo, $observacao,
            $idusuario, $itemInfo['descricao']
        ]);
        $idMovimentacao = $stmt->fetch()['id'];
        
        // Atualiza totais do pedido
        $this->sincronizarPlaceholder((int)$idpedido);
        $this->atualizarTotaisPedido($idpedido);
        
        $this->pdo->commit();
        
        $payload = json_encode([
            'success' => true,
            'id_movimentacao' => $idMovimentacao,
            'quantidade_atual' => $nova_quantidade,
            'message' => 'Produto ajustado com sucesso!'
        ]);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json');
        
    } catch (\Exception $e) {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
        error_log('[RetornoController] Erro em ajustarProduto: ' . $e->getMessage());
        $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
        return $response->withStatus(500);
    }
}
  /**
 * POST /v1/auditoria-estoque/mover-produto
 * Move produto de um estoque para outro
 */
public function moverProduto(Request $request, Response $response): Response
{
    $input = json_decode($request->getBody()->getContents(), true);
    
    $idpedido_origem = (int)($input['idpedido_origem'] ?? 0);
    $idcliforemp_destino = (int)($input['idcliforemp_destino'] ?? 0);
    $iditem = (int)($input['iditem'] ?? 0);
    $quantidade = (float)($input['quantidade'] ?? 0);
    $idusuario = (int)(($request->getAttribute('user') ?? [])['idusuario'] ?? ($input['idusuario'] ?? 0));
    $motivo = $input['motivo'] ?? 'TRANSFERENCIA';
    $observacao = $input['observacao'] ?? null;
    
    if ($idpedido_origem <= 0 || $idcliforemp_destino <= 0 || $iditem <= 0 || $quantidade <= 0) {
        $response->getBody()->write(json_encode(['error' => 'Dados inválidos']));
        return $response->withStatus(400);
    }
    
    if (!isset($this->destinos[$idcliforemp_destino]) || !$this->pedidoEhEstoqueRetorno($idpedido_origem)) {
        $response->getBody()->write(json_encode(['error' => 'Origem ou destino não permitido']));
        return $response->withStatus(422);
    }
    
    try {
        $this->pdo->beginTransaction();
        
        // Busca o pedido de destino
        $pedidoDestino = $this->getPedidoAberto($idcliforemp_destino);
        $idpedido_destino = $pedidoDestino['idpedido'];
        
        // Busca informações do produto no pedido de origem
        $stmt = $this->pdo->prepare("
            SELECT iditempedido, qt, valor, qtpesoliquido, qtpesobruto
            FROM pedido_item 
            WHERE idpedido = ? AND iditem = ? AND ativo = 'S'
            FOR UPDATE
        ");
        $stmt->execute([$idpedido_origem, $iditem]);
        $produtoOrigem = $stmt->fetch();
        
        if (!$produtoOrigem) {
            throw new \Exception("Produto não encontrado no estoque de origem");
        }
        
        if ($quantidade > $produtoOrigem['qt']) {
            throw new \Exception("Quantidade insuficiente. Disponível: {$produtoOrigem['qt']}");
        }
        
        // Busca informações do item
        $stmt = $this->pdo->prepare("
            SELECT i.descricao, i.pesoliquido, i.pesobruto, 
                   COALESCE(ef.valorprecovenda, 0) as valor
            FROM item i
            LEFT JOIN estoque_filial ef ON ef.iditem = i.iditem AND ef.idfilial = 1
            WHERE i.iditem = ?
        ");
        $stmt->execute([$iditem]);
        $itemInfo = $stmt->fetch();
        
        // 1. Remove do estoque de origem
        $novaQuantOrigem = $produtoOrigem['qt'] - $quantidade;
        $this->aplicarQuantidadePedidoItem((int)$produtoOrigem['iditempedido'], (int)$idpedido_origem, $iditem, (float)$novaQuantOrigem);
        
        // Registra saída no retorno_carga
        $stmt = $this->pdo->prepare("
            INSERT INTO retorno_carga (
                idpedido, iditem, idcliforemp_destino, tipo_movimentacao,
                quant, quantidade_antes, quantidade_depois,
                motivo, observacao, idusuario, nome_item, data_hora, data_hora_registro
            ) VALUES (?, ?, ?, 'SAIDA', ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([
            $idpedido_origem, $iditem, $idcliforemp_destino,
            $quantidade, $produtoOrigem['qt'], $novaQuantOrigem,
            $motivo, $observacao, $idusuario, $itemInfo['descricao']
        ]);
        
        // 2. Adiciona no estoque de destino
        $stmt = $this->pdo->prepare("
            SELECT iditempedido, qt FROM pedido_item 
            WHERE idpedido = ? AND iditem = ? AND ativo = 'S'
        ");
        $stmt->execute([$idpedido_destino, $iditem]);
        $produtoDestino = $stmt->fetch();
        
        if ($produtoDestino) {
            $novaQuantDestino = $produtoDestino['qt'] + $quantidade;
            $this->aplicarQuantidadePedidoItem((int)$produtoDestino['iditempedido'], (int)$idpedido_destino, $iditem, (float)$novaQuantDestino);
        } else {
            $dadosItem = $this->getDadosItemCompleto($iditem, $quantidade);
            $this->inserirItemCompleto($idpedido_destino, $iditem, $quantidade, $dadosItem);
        }
        
        // Registra entrada no retorno_carga
        $stmt = $this->pdo->prepare("
            INSERT INTO retorno_carga (
                idpedido, iditem, idcliforemp_destino, tipo_movimentacao,
                quant, quantidade_antes, quantidade_depois,
                motivo, observacao, idusuario, nome_item, data_hora, data_hora_registro
            ) VALUES (?, ?, ?, 'ENTRADA', ?, 0, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([
            $idpedido_destino, $iditem, $idcliforemp_destino,
            $quantidade, $quantidade,
            $motivo, $observacao, $idusuario, $itemInfo['descricao']
        ]);
        
        // Atualiza totais dos pedidos
        $this->sincronizarPlaceholder((int)$idpedido_origem);
        $this->sincronizarPlaceholder((int)$idpedido_destino);
        $this->atualizarTotaisPedido($idpedido_origem);
        $this->atualizarTotaisPedido($idpedido_destino);
        
        $this->pdo->commit();
        
        $payload = json_encode([
            'success' => true,
            'message' => 'Produto movido com sucesso!',
            'quantidade_origem' => $novaQuantOrigem,
            'quantidade_destino' => $produtoDestino ? $produtoDestino['qt'] + $quantidade : $quantidade
        ]);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json');
        
    } catch (\Exception $e) {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
        error_log('[RetornoController] Erro em moverProduto: ' . $e->getMessage());
        $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
        return $response->withStatus($e instanceof \DomainException ? 409 : 500);
    }
}
    
    /**
     * GET /v1/auditoria-estoque/relatorio/{idpedido}
     * Relatório completo do estoque
     */
    public function getRelatorioEstoque(Request $request, Response $response, array $args): Response
    {
        $idpedido = (int)($args['idpedido'] ?? 0);
        
        if ($idpedido <= 0) {
            $response->getBody()->write(json_encode(['error' => 'ID inválido']));
            return $response->withStatus(400);
        }
        
        try {
            // Busca informações do pedido
            $stmt = $this->pdo->prepare("
                SELECT p.*, 
                       CASE 
                           WHEN p.idcliforemp = 10117 THEN 'CONTAINER'
                           WHEN p.idcliforemp = 16595 THEN 'MATÉRIA-PRIMA'
                           WHEN p.idcliforemp = 16596 THEN 'REVENDA'
                           WHEN p.idcliforemp = 16688 THEN 'TROCAS FORNECEDOR'
                       END as estoque_nome
                FROM pedido p
                WHERE p.idpedido = ?
            ");
            $stmt->execute([$idpedido]);
            $pedido = $stmt->fetch();
            
            if (!$pedido) {
                $response->getBody()->write(json_encode(['error' => 'Pedido não encontrado']));
                return $response->withStatus(404);
            }
            
            // Busca resumo por tipo de movimentação
            $stmt = $this->pdo->prepare("
                SELECT 
                    tipo_movimentacao,
                    COUNT(*) as total_registros,
                    SUM(quant) as total_quantidade,
                    SUM(peso_real) as total_peso_real
                FROM retorno_carga
                WHERE idpedido = ?
                GROUP BY tipo_movimentacao
            ");
            $stmt->execute([$idpedido]);
            $resumo = $stmt->fetchAll();
            
            // Busca produtos com saldo
            $stmt = $this->pdo->prepare("
                SELECT 
                    pi.iditem, pi.qt as quantidade,
                    i.descricao, i.referencia,
                    COALESCE(cb.idbarra, 'S/COD') as cod_barras,
                    (SELECT COUNT(*) FROM retorno_carga WHERE idpedido = pi.idpedido AND iditem = pi.iditem) as total_movimentacoes
                FROM pedido_item pi
                JOIN item i ON i.iditem = pi.iditem
                LEFT JOIN codigo_barra cb ON cb.iditem = i.iditem AND cb.principal = 'S'
                WHERE pi.idpedido = ? AND pi.ativo = 'S' AND i.inativo = 'N' AND pi.iditem <> 2181
                ORDER BY pi.iditempedido
            ");
            $stmt->execute([$idpedido]);
            $produtos = $stmt->fetchAll();
            
            $payload = json_encode([
                'pedido' => $pedido,
                'resumo_movimentacoes' => $resumo,
                'produtos' => $produtos,
                'data_geracao' => date('Y-m-d H:i:s')
            ]);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            error_log('[RetornoController] Erro em getRelatorioEstoque: ' . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500);
        }
    }
}