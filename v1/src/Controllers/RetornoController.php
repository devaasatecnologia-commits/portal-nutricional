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
        16596 => ['nome' => 'REVENDA', 'cor' => '#f59e0b']
    ];
    
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
    private function getOrCreatePedidoAtivo($idcliforemp, $idusuario)
    {
        // Busca pedido em aberto (status = 1)
        $stmt = $this->pdo->prepare("
            SELECT idpedido, numero, data, observacao, valortotalpedido
            FROM pedido 
            WHERE idcliforemp = ? AND status = 1
            ORDER BY idpedido DESC
            LIMIT 1
        ");
        $stmt->execute([$idcliforemp]);
        $pedido = $stmt->fetch();
        
        if ($pedido) {
            return $pedido;
        }
        
        // Cria novo pedido usando sequence gi_pedido
        $stmt = $this->pdo->query("SELECT nextval('gi_pedido') as proximo");
        $proximo = $stmt->fetch();
        $numeroPedido = $proximo['proximo'];
        
        $nomeDestino = $this->destinos[$idcliforemp]['nome'];
        
        $stmt = $this->pdo->prepare("
            INSERT INTO pedido (
                idcliforemp, numero, data, status, situacao, observacao, 
                idusuario, datahora, idempresa, idfilial,
                valortotalpedido, valortotalitens, valortotalcalculado,
                pesobruto, pesoliquido, quantidadetotal
            ) VALUES (?, ?, CURRENT_DATE, 1, 1, ?, ?, NOW(), 1, 1, 0, 0, 0, 0, 0, 0)
            RETURNING idpedido, numero
        ");
        $stmt->execute([$idcliforemp, $numeroPedido, "[ESTOQUE] Lote - " . $nomeDestino, $idusuario]);
        $pedido = $stmt->fetch();
        
        // Cria parcela inicial zerada
        $this->atualizarParcelas($pedido['idpedido'], 0);
        
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
    
    // ================================================================
    // INSERT COMPLETO - IGUAL AO TESTE MANUAL (TODOS OS CAMPOS)
    // ================================================================
    $sql = "
        INSERT INTO pedido_item (
            idpedido, iditempedido, idcodempresa, idfilial, iditem,
            idclassfiscal, idsituacaotributaria,
            qt, qtsaldo, qtfaturado, qtpesoliquido, qtpesobruto,
            valor, valoripi, valortotal, valortotalcomdesconto,
            valorcustoreposicao, valordesconto, valoriss, valorcomissao,
            valorpauta, idunidade, perc_desconto, perc_ipi, perc_icms,
            perc_reducaobasecalculo, perc_iss, perc_comissao,
            codigolido, valoricms, origemcomissao,
            idsolicitacaocompra, perc_dif_aliquota, valortotal_dif_aliquota,
            perc_margem, perc_comissao_supervisor, valor_comissao_supervisor,
            perc_acrescimo, valor_acrescimo, perc_margem_cm, perc_margem_cg,
            valorcustogerencial, complemento,
            percmargemsubsttrib, valorprecominimo, idunidade_impressao,
            quant_impressao, fator_conversao, valorunitarioimpressao,
            valorsugerido, idof, status, perc_campanha, valor_campanha,
            valor_total_com_campanha, reserva_estoque, tipo_conversao,
            valor_ipi_cadimposto, valor_pis_cadimposto, valor_cofins_cadimposto,
            quant_na_caixa, idoflote, valorbase_substtribimp, quant_original,
            data_previsao_entrega, idtabelapreco, naofaturar, iddeposito,
            quant_venda_minima, quant_reserva, valorbase_st, valor_st,
            perc_icmsuf_st, valorunit_comst, valortotal_comst, editou_qt_logistica,
            idsittrib_origem, idsituacaotrib_cliente, perc_reducaoicms_interno,
            perc_price, valor_price, iditemsolicitacao,
            idsittrib_ipi, idsittrib_pis, idsittrib_cofins, icms_diferido,
            perc_mva_ajustado, perc_desconto_original, perc_desconto_condpagto,
            valor_desconto_price, valor_price_sem_ipi, idcondicao_promocao,
            valorcondicao_promocao, permite_desconto, promocao, valorpromocao,
            idpolitica, percdescontopolitica, percacrescimopolitica,
            valorprecovendapolitica, valorprecovendadia, percdescontopadrao,
            percmvabeneficio, percicmsbeneficio, valorbaseicms, valorfrete,
            valoroutrasdespesas, valorbaseipi, valorbaseiss,
            valorbasepis, valorpis, percpis, valorbasecofins, valorcofins,
            perccofins, valorbaseisentoicms, valorseguro, quantdevolvidoef,
            valortac, valoracrescimofinanceiro,
            ativo, datahora, origemproduto, foto_item_auto,
            quantmoedaunitario, quantmoedatotal, idpedidocompranf_e,
            iditempedidocompranf_e, idordembeneficiamento, quantmoedadesconto,
            valorbaseicmsufdestino, percfcp, percicmsufdestino, percpartilhaicms,
            valorfcp, valoricmsufdestino, valoricmsuforigem, idenquadramentoipi,
            qt_embarque, md5, valorcustoobsoleto, valorcomissaotelevendas,
            perccomissaotelevendas, perccomissaopenalizacao, nrarttmp,
            percdescontotabelapreco, valorunconvertida, corpromocao, percdificms,
            valordificms, iddepositoentrada, nrart, percreducaopis, percreducaocofins,
            qtsaldoconvertido, qtfaturadoconvertido, basepissuframa, valorpissuframa,
            basecofinssuframa, valorcofinssuframa, valoricmssuframa, baseicmssuframa,
            percicmssuframa, valorbaseicmssuframa, baseipisuframa, valoripisuframa,
            pesobrutooriginal, pesoliquidooriginal, idimpostovenda,
            diasgarantiafabricante, datagarantiafabricante, iditempedidogarantia,
            iditemgarantia, percgarantia, diasgarantiaestendida, datagarantiaestendida,
            pesoliquidoembarque, pesobrutoembarque, iditemcarrinho,
            percdescontopoliticavalorunit, percacrescimopoliticavalorunit,
            valorcustomedio, qtsaldogerouop, percicmsdesonerado,
            valorbaseicmsdesonerado, valoricmsdesonerado, valordescontope,
            pex_conf_embarque, pex_qt_recebida, pex_saldo_embarque,
            pex_conf_embarque_carregamento, pex_qt_recebida_carregamento,
            pex_saldo_embarque_carregamento, idtributo, idsituacao, idclasstrib,
            perccbs, percibsmun, percibsuf, percreducaocbs, percreducaoibsuf,
            percreducaoibsmun, percefetibsmun, percefetibsuf, percefetcbs,
            negritopromocao, nrregistro, nrreceita, idcidasc, uncidasc,
            conversaoforcar, kit, tipoitemgarantia, manutencaodata, manutencaoqtd,
            cstcbsibs, classtribcbsibs, valorbasecbsibs, valorcbs, valoribsuf,
            valoribsmun, valoribs, valorcbsibs, percdiferimentocbs,
            percdiferimentoibsuf, percdiferimentoibsmun, valordiferimentocbs,
            valordiferimentoibsmun, valordiferimentoibsuf
        ) VALUES (
            ?, ?, 1, 1, ?,
            ?, ?,
            ?, 0, 0, ?, ?,
            ?, 0, ?, ?,
            ?, 0, 0, 0,
            ?, 1, 0, ?, ?,
            0, 0, 0,
            ?, ?,
            0,
            0, 0, 0,
            ?, 0, 0,
            0, 0, 100, ?,
            ?, ?,
            0, ?, 1,
            ?, ?, ?,
            ?, 0, 1, 0, 0,
            ?, 'S', 1,
            0, 0, 0,
            ?, 0, 0, ?,
            CURRENT_DATE, 0, 'S', 1,
            0, ?, 0, 0,
            ?, ?, 0, 'N',
            0, 0, 0,
            0, 0, 0,
            0, 0, 0, 0,
            'N', 0, 0, 0,
            0, 0, 0, 0,
            'S', 'N', 0,
            0, 0, 0, 0,
            0, 0, 0, 0, 0, 0,
            0, 0, 0, 0, 0, 0, 0, 0,
            0, 0, 0, 0, 0, 0, 0, 0,
            0, 0, 0, 0, 0, 0, 0,
            0, 0, 0, 0, 0, 0,
            'S', NOW(), 0, NULL,
            ?, ?,
            '.', '0', '.',
            0, 0, 0, 0, 0,
            0, 0, 0, 0,
            0, 0, 0, 0, 0,
            0, 0, 0, 0,
            0, 0, 0, 0, 0,
            0, 0, 0, 0, 0, 0,
            0, 0, 0, 0, 0, 0, 0,
            0, 0, 0, 0, 0, 0, 0,
            0, 0, 0, 0, 0, 0, 0,
            0, 0, 0, 0, 0,
            0, 0, 0, 0, 0,
            0, 0, 0, 0, 0,
            0, 0, 0, 0, 0,
            0, 0, 0, 0, 0,
            0, 0, 0, 0, 0, 0, 0, 0,
            0, 0, 0, 0, 0, 0, 0,
            0, 0, 0, 0, 0, 0,
            0, 0, 0, 0, 0,
            0, 0, 0, 0, 0,
            0, 0, 0, 0, 0
        )
    ";
    
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([
        $idpedido, $iditempedido, $iditem,
        $idclassfiscal, $idsituacaotributaria,
        $qt, $qtpesoliquido, $qtpesobruto,
        $valorUnitario, $valortotal, $valortotalcomdesconto,
        $valorcustocontabil,
        $valorUnitario,
        $percIpi, $percIcms,
        $referencia, $valoricms,
        $percmargem, $percmargem,
        $custogerencial, $complemento,
        $valorbasepis, $valorpis,
        $valorbasecofins, $valorcofins,
        $valorbasecbsibs, $valorcbs,
        $valoribsuf, $valorcbsibs,
        $quantmoedaunitario, $quantmoedatotal
    ]);
    
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
            ['id' => 16596, 'nome' => 'REVENDA', 'descricao' => 'Estoque provisório - Revenda', 'cor' => '#f59e0b']
        ];
        
        $payload = json_encode($tipos);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json');
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
            $produto = null;
            
            // Busca por ID
            if (is_numeric($busca)) {
                $stmt = $this->pdo->prepare("
                    SELECT i.iditem, i.descricao, i.referencia, i.pesobruto, i.pesoliquido, 
                           ef.valorprecovenda, i.path_foto_master,
                           COALESCE(cb.idbarra, 'S/COD') as cod_barras
                    FROM item i
                    LEFT JOIN estoque_filial ef ON ef.iditem = i.iditem AND ef.idfilial = 1
                    LEFT JOIN codigo_barra cb ON cb.iditem = i.iditem AND cb.principal = 'S'
                    WHERE i.iditem = ? AND i.inativo = 'N'
                ");
                $stmt->execute([$busca]);
                $produto = $stmt->fetch();
            }
            
            // Busca por código de barras
            if (!$produto) {
                $stmt = $this->pdo->prepare("
                    SELECT i.iditem, i.descricao, i.referencia, i.pesobruto, i.pesoliquido, 
                           ef.valorprecovenda, i.path_foto_master, cb.idbarra as cod_barras
                    FROM item i
                    LEFT JOIN estoque_filial ef ON ef.iditem = i.iditem AND ef.idfilial = 1
                    JOIN codigo_barra cb ON cb.iditem = i.iditem AND cb.principal = 'S'
                    WHERE cb.idbarra = ? AND i.inativo = 'N'
                    LIMIT 1
                ");
                $stmt->execute([$busca]);
                $produto = $stmt->fetch();
            }
            
            if (!$produto) {
                $response->getBody()->write(json_encode(['error' => 'Produto não encontrado']));
                return $response->withStatus(404);
            }
            
            $produto['foto_url'] = $this->processarImagem($produto['path_foto_master'] ?? null);
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
    $idusuario = (int)($input['idusuario'] ?? 0);
    $cod_barras = $input['cod_barras'] ?? null;
    
    if ($idcliforemp_destino <= 0 || $iditem <= 0) {
        $response->getBody()->write(json_encode(['error' => 'Dados inválidos']));
        return $response->withStatus(400);
    }
    
    if ($tipo != 'AJUSTE' && $quantidade <= 0) {
        $response->getBody()->write(json_encode(['error' => 'Quantidade inválida']));
        return $response->withStatus(400);
    }
    
    try {
        $this->pdo->beginTransaction();
        
        // Obtém ou cria o pedido ativo para o destino
        $pedido = $this->getOrCreatePedidoAtivo($idcliforemp_destino, $idusuario);
        $idpedido = $pedido['idpedido'];
        
        // Busca produto no pedido
        $stmt = $this->pdo->prepare("
            SELECT iditempedido, qt, qtpesoliquido, qtpesobruto, valor, valortotal
            FROM pedido_item 
            WHERE idpedido = ? AND iditem = ? AND ativo = 'S'
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
            
            $stmt = $this->pdo->prepare("
                UPDATE pedido_item 
                SET 
                    qt = ?,
                    qtpesoliquido = ?,
                    qtpesobruto = ?,
                    valortotal = ?,
                    valortotalcomdesconto = ?,
                    valoricms = ?,
                    valorbasepis = ?,
                    valorpis = ?,
                    valorbasecofins = ?,
                    valorcofins = ?,
                    valorbasecbsibs = ?,
                    valorcbs = ?,
                    valoribsuf = ?,
                    valorcbsibs = ?
                WHERE iditempedido = ?
            ");
            $stmt->execute([
                $quantidade_depois,
                $novoPesoLiquido,
                $novoPesoBruto,
                $novoValorTotal,
                $novoValorTotal,
                $novoValorIcms,
                $novaBasePis,
                $novoValorPis,
                $novaBaseCofins,
                $novoValorCofins,
                $novaBaseCbsibs,
                $novoValorCbs,
                $novoValorIbsuf,
                $novoValorCbsibs,
                $produtoPedido['iditempedido']
            ]);
            
        } else {
            // ============================================================
            // INSERE NOVO ITEM - usando a função completa (TODAS as colunas)
            // ============================================================
            $this->inserirItemCompleto($idpedido, $iditem, $quantidade_depois, $dadosItem);
        }
        
        // ================================================================
        // REGISTRA NO RETORNO_CARGA
        // ================================================================
        $sqlMov = "
            INSERT INTO retorno_carga (
                idpedido, iditem, idcliforemp_destino, tipo_movimentacao,
                quant, quantidade_antes, quantidade_depois,
                peso_real, lote, validade, motivo, observacao,
                idusuario, nome_item, cod_barras, data_hora, data_hora_registro
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            RETURNING id
        ";
        
        $stmt = $this->pdo->prepare($sqlMov);
        $stmt->execute([
            $idpedido, $iditem, $idcliforemp_destino, $direcao,
            $quantidade_para_registro, $quantidade_atual, $quantidade_depois,
            $peso_real, $lote, $validade, $motivo, $observacao,
            $idusuario, $descricaoItem, $cod_barras
        ]);
        $idMovimentacao = $stmt->fetch()['id'];
        
        // ================================================================
        // ATUALIZA TOTAIS DO PEDIDO
        // ================================================================
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
        return $response->withStatus(500);
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
        
        $ext = pathinfo($foto->getClientFilename(), PATHINFO_EXTENSION);
        $nomeArquivo = sprintf('movimentacao_%d_%s.%s', $idMovimentacao, date('Ymd_His'), $ext);
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
            $pedido = $this->getOrCreatePedidoAtivo($idcliforemp, $idusuario);
            
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
                WHERE pi.idpedido = ? AND pi.ativo = 'S' AND i.inativo = 'N'
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
    $idusuario = (int)($input['idusuario'] ?? 0);
    
    if ($idpedido <= 0 || $iditem <= 0 || $nova_quantidade <= 0) {
        $response->getBody()->write(json_encode(['error' => 'Dados inválidos']));
        return $response->withStatus(400);
    }
    
    try {
        $this->pdo->beginTransaction();
        
        // Busca quantidade atual
        $stmt = $this->pdo->prepare("
            SELECT iditempedido, qt, qtpesoliquido, valor 
            FROM pedido_item 
            WHERE idpedido = ? AND iditem = ? AND ativo = 'S'
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
        $stmt = $this->pdo->prepare("
            UPDATE pedido_item 
            SET qt = ?, 
                valortotal = qt * ?,
                qtpesoliquido = qt * ?,
                qtpesobruto = qt * ?
            WHERE iditempedido = ?
        ");
        $stmt->execute([
            $nova_quantidade, 
            $itemInfo['valor'],
            $itemInfo['pesoliquido'],
            $itemInfo['pesobruto'],
            $produtoPedido['iditempedido']
        ]);
        
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
    $idusuario = (int)($input['idusuario'] ?? 0);
    $motivo = $input['motivo'] ?? 'TRANSFERENCIA';
    $observacao = $input['observacao'] ?? null;
    
    if ($idpedido_origem <= 0 || $idcliforemp_destino <= 0 || $iditem <= 0 || $quantidade <= 0) {
        $response->getBody()->write(json_encode(['error' => 'Dados inválidos']));
        return $response->withStatus(400);
    }
    
    try {
        $this->pdo->beginTransaction();
        
        // Busca o pedido de destino
        $pedidoDestino = $this->getOrCreatePedidoAtivo($idcliforemp_destino, $idusuario);
        $idpedido_destino = $pedidoDestino['idpedido'];
        
        // Busca informações do produto no pedido de origem
        $stmt = $this->pdo->prepare("
            SELECT iditempedido, qt, valor, qtpesoliquido, qtpesobruto
            FROM pedido_item 
            WHERE idpedido = ? AND iditem = ? AND ativo = 'S'
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
        $stmt = $this->pdo->prepare("
            UPDATE pedido_item 
            SET qt = ?,
                valortotal = qt * ?,
                qtpesoliquido = qt * ?,
                qtpesobruto = qt * ?
            WHERE iditempedido = ?
        ");
        $stmt->execute([
            $novaQuantOrigem, $itemInfo['valor'], $itemInfo['pesoliquido'], $itemInfo['pesobruto'],
            $produtoOrigem['iditempedido']
        ]);
        
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
            $stmt = $this->pdo->prepare("
                UPDATE pedido_item 
                SET qt = ?,
                    valortotal = qt * ?,
                    qtpesoliquido = qt * ?,
                    qtpesobruto = qt * ?
                WHERE iditempedido = ?
            ");
            $stmt->execute([
                $novaQuantDestino, $itemInfo['valor'], $itemInfo['pesoliquido'], $itemInfo['pesobruto'],
                $produtoDestino['iditempedido']
            ]);
        } else {
            $iditempedido = $this->getNextIdItemPedido($idpedido_destino);
            $dadosItem = $this->getDadosItemCompleto($iditem, $quantidade);
            
            $stmt = $this->pdo->prepare("
                INSERT INTO pedido_item (
                    idpedido, iditempedido, idcodempresa, idfilial, iditem,
                    idclassfiscal, idsituacaotributaria,
                    qt, qtsaldo, qtfaturado, qtpesoliquido, qtpesobruto,
                    valor, valoripi, valortotal, valortotalcomdesconto,
                    valorcustoreposicao, valordesconto, valoriss, valorcomissao,
                    valorpauta, idunidade, perc_desconto, perc_ipi, perc_icms,
                    perc_reducaobasecalculo, perc_iss, perc_comissao,
                    codigolido, valoricms, origemcomissao,
                    perc_margem, perc_margem_cm, perc_margem_cg,
                    valorcustogerencial, complemento,
                    valorbasepis, valorpis, valorbasecofins, valorcofins,
                    valorbasecbsibs, valorcbs, valoribsuf, valorcbsibs,
                    ativo, datahora
                ) VALUES (
                    ?, ?, 1, 1, ?,
                    ?, ?,
                    ?, 0, 0, ?, ?,
                    ?, 0, ?, ?,
                    ?, 0, 0, 0,
                    ?, 1, 0, ?, ?,
                    0, 0, 0,
                    ?, ?,
                    0,
                    ?, 100, ?,
                    ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    'S', NOW()
                )
            ");
            $stmt->execute([
                $idpedido_destino, $iditempedido, $dadosItem['iditem'],
                $dadosItem['idclassfiscal'], $dadosItem['idsituacaotributaria'],
                $dadosItem['qt'], $dadosItem['qtpesoliquido'], $dadosItem['qtpesobruto'],
                $dadosItem['valorprecovenda'], $dadosItem['valortotal'], $dadosItem['valortotal'],
                $dadosItem['valorcustocontabil'],
                $dadosItem['valorprecovenda'],
                $dadosItem['perc_ipi'], $dadosItem['perc_icms'],
                $dadosItem['referencia'], $dadosItem['valoricms'],
                $dadosItem['percmargem'], $dadosItem['percmargem'],
                $dadosItem['custogerencial'], $dadosItem['complemento'],
                $dadosItem['valorbasepis'], $dadosItem['valorpis'],
                $dadosItem['valorbasecofins'], $dadosItem['valorcofins'],
                $dadosItem['valorbasecbsibs'], $dadosItem['valorcbs'],
                $dadosItem['valoribsuf'], $dadosItem['valorcbsibs']
            ]);
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
        return $response->withStatus(500);
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
                WHERE pi.idpedido = ? AND pi.ativo = 'S' AND i.inativo = 'N'
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