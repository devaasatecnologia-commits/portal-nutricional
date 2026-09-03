<?php
/**
 * INDEX.PHP - GATEWAY UNIFICADO NUTRICIONAL (DASHBOARD & API)
 * Versão Final Revisada: Login Fixo + Dashboard KPI + Telas Logísticas
 */

// 1. IMPORTAÇÃO DE NAMESPACES
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

session_start(); 

// 2. CARREGAMENTO DAS CONFIGURAÇÕES E BIBLIOTECAS
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/vendor/autoload.php'; 
require_once __DIR__ . '/conect.php';
require_once __DIR__ . '/uteis.php';
require_once __DIR__ . '/email_config.php';

// 3. DEFINIÇÃO DE CONSTANTES E DOMÍNIO
if (!defined('CHAVE_SECRETA'))  define('CHAVE_SECRETA', 'alansabe123456');
if (!defined('TOKEN_EMAIL'))    define('TOKEN_EMAIL', 'TOKEN_PADRAO_REPRE_2026');
if (!defined('TOKEN_GESTORES')) define('TOKEN_GESTORES', 'TOKEN_PADRAO_GEST_2026');
if (!defined('API_TOKEN'))      define('API_TOKEN', 'xoUM?va.JNG93v)@#i9FyH@B6n0}H4.yst%s8zV8M}xc+ZrFAz5:y6T07HxyYGE~');

if (!defined('DOMINIO')) {
    $protocolo = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https://" : "http://";
    define('DOMINIO', $protocolo . $_SERVER['HTTP_HOST']);
}

// 4. INSTANCIAÇÃO DO CLIENTE HTTP (GUZZLE)
$httpClient = new \GuzzleHttp\Client([
    'timeout'  => 90.0,
    'verify'   => false, 
    'headers'  => ['User-Agent' => 'NutricionalCron/2.0']
]);

// 5. LOGS DE SISTEMA
$logDir = __DIR__ . '/erros_log';
if (!file_exists($logDir)) @mkdir($logDir, 0755, true);

// 6. HEADERS DE SEGURANÇA E CORS
header("Access-Control-Allow-Origin: *"); 
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

date_default_timezone_set('America/Sao_Paulo');

// 7. CAPTURA DE DADOS E LÓGICA DE LOGIN
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$isCli = (php_sapi_name() === 'cli'); 
$strAcao = $isCli ? ($_SERVER['argv'][1] ?? '') : ($_GET['acao'] ?? ($input['acao'] ?? ''));

// --- LÓGICA DE LOGIN FIXO (Sincronizado com os campos 'user' e 'pass' do seu HTML) ---
if (!$isCli && isset($input['user']) && isset($input['pass'])) {
    if ($input['user'] === 'admin' && $input['pass'] === 'nutri2026') {
        $_SESSION['logado'] = true;
        $_SESSION['uid']    = 11258; // ID Master (Tiago) para contexto de dados
        $_SESSION['uname']  = 'Administrador';
        
        session_write_close();
        header("Location: " . DOMINIO . "/index.php?acao=home");
        exit;
    } else {
        $erro_login = "Usuário ou senha incorretos.";
    }
}

file_put_contents($logDir . '/cron_debug.log', "[" . date('Y-m-d H:i:s') . "] Acao: $strAcao | IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'CLI') . "\n", FILE_APPEND);
// 8. FUNÇÃO DE VALIDAÇÃO MESTRE 
function verificarAcesso($strAcao) {
    // Preparação do Log
    $logDir = __DIR__ . '/erros_log';
    $logFile = $logDir . '/auth_debug.log';
    if (!file_exists($logDir)) @mkdir($logDir, 0755, true);
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI';

    // Libera execução via linha de comando (CLI)
    if (php_sapi_name() === 'cli') return true;

    // Libera arquivos estáticos
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (preg_match('/\.(?:css|js|png|jpg|jpeg|gif|ico|svg|pdf)$/i', $uri)) return true; 

    // 1. LISTA DE AÇÕES PÚBLICAS
    $rotasPublicas = ['catalogo'];
    if (in_array($strAcao, $rotasPublicas)) return true;

    // 2. VALIDAÇÃO POR CHAVE DE API (VIA URL)
    // Correção: Agora pegamos especificamente o $_GET['key'] sem misturar com o t0k3n
    $chaveApiEnviada = $_GET['key'] ?? '';
    
    if (defined('API_TOKEN') && !empty($chaveApiEnviada) && $chaveApiEnviada === API_TOKEN) {
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] AUTORIZADO (URL Key) - Ação: $strAcao | IP: $ip\n", FILE_APPEND);
        return true;
    }

    // 3. VALIDAÇÃO POR CHAVE DE API (VIA HEADER BEARER)
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (defined('API_TOKEN') && strpos($authHeader, 'Bearer ') === 0) {
        if (substr($authHeader, 7) === API_TOKEN) {
            file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] AUTORIZADO (Header) - Ação: $strAcao | IP: $ip\n", FILE_APPEND);
            return true;
        }
    }

    // 4. VALIDAÇÃO POR SESSÃO (Para usuários no Navegador)
    if (isset($_SESSION['logado']) && $_SESSION['logado'] === true) {
        return true;
    }

    // SE CHEGOU ATÉ AQUI, FOI BLOQUEADO: REGISTRA NO LOG
    $motivo = "Chave enviada: " . (empty($chaveApiEnviada) ? "NENHUMA" : "INCORRETA ($chaveApiEnviada)");
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] BLOQUEADO - Ação: $strAcao | IP: $ip | Motivo: $motivo\n", FILE_APPEND);
    
    return false;
}

// 9. EXECUÇÃO DA VALIDAÇÃO
$isCli = (php_sapi_name() === 'cli');

if (!verificarAcesso($strAcao)) {
    // Se for uma tentativa de acesso via navegador (sem API Key e sem Sessão)
    if (!$isCli) {
        // Se a requisição for JSON (AJAX), retorna erro 401
        $isAjax = (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);
        
        if ($isAjax) {
            http_response_code(401);
            die(json_encode(["erro" => "Acesso negado ou Sessão expirada"]));
        }

        // Caso contrário, mostra a tela de login
        include __DIR__ . '/html/login.html';
        exit;
    }
    
    // Bloqueio genérico para outros tipos de acesso
    http_response_code(401);
    die("Acesso negado");
}
// 10. PROCESSAMENTO DE TEMPLATE
require_once __DIR__ . '/template.php';
$arrTagEstrutura = ['tituloArea' => '', 'conteudoArea' => '', 'url' => DOMINIO];

$strAcaoId = 0;
if (strpos($strAcao, '/') !== false) {
    $acaovetor = explode('/', $strAcao);
    $strAcao = $acaovetor[0];
    $strAcaoId = $acaovetor[1] ?? 0;
}

// 11. INÍCIO DOS CASES
switch ($strAcao) {
    case '': 
    case 'home':
        $arrTagEstrutura['tituloArea'] = 'Dashboard API | Nutricional';
        $logPath = __DIR__ . '/erros_log/cron_debug.log';
        $logContent = @file_get_contents($logPath);
        $hoje = date('Y-m-d');
        $acessosHoje = substr_count($logContent, "[{$hoje}");
        
        $temErro = (strpos($logContent, "ERROR") !== false && strpos($logContent, $hoje) !== false);
        $statusSistema = $temErro ? 'Atenção: Logs com Erro' : 'Sistema Online';
        $classeStatus = $temErro ? 'danger' : 'success';

        $objHome = new templateParser('html/home.html');
        $objHome->parseTemplate([
            'total_acessos' => $acessosHoje,
            'ultima_sinc'   => date('H:i'),
            'status_msg'    => $statusSistema,
            'status_classe' => $classeStatus
        ]); 
        $arrTagEstrutura['conteudoArea'] = $objHome->display();
        break;

    case 'docs':
        $arrTagEstrutura['tituloArea'] = 'API Documentation | Scalar Interface';
        $objDocs = new templateParser('html/docs.html');
        $objDocs->parseTemplate(['token_real' => API_TOKEN, 'url_base' => DOMINIO]);
        $arrTagEstrutura['conteudoArea'] = $objDocs->display();
        break;

    case 'logs':
        $arrTagEstrutura['tituloArea'] = 'Monitor de Logs | Nutricional';
        $logPath = __DIR__ . '/erros_log/cron_debug.log';
        $linhas = file_exists($logPath) ? array_reverse(file($logPath)) : [];
        $htmlLinhas = "";
        foreach (array_slice($linhas, 0, 50) as $linha) {
            $linhaLimpa = htmlspecialchars(trim($linha));
            if (empty($linhaLimpa)) continue;
            $corLinha = (strpos($linhaLimpa, 'ERROR') !== false || strpos($linhaLimpa, 'Erro') !== false) ? 'text-danger fw-bold' : 'text-muted';
            $data = substr($linhaLimpa, 0, 21); 
            $evento = substr($linhaLimpa, 21);
            $htmlLinhas .= "<tr><td class='ps-4'><span class='badge bg-light text-dark border'>$data</span></td><td><code class='text-break $corLinha'>$evento</code></td></tr>";
        }
        $objLogs = new templateParser('html/logs.html');
        $objLogs->parseTemplate(['lista_logs' => $htmlLinhas ?: "<tr><td colspan='2' class='text-center'>Sem logs.</td></tr>"]);
        $arrTagEstrutura['conteudoArea'] = $objLogs->display();
        break;

    case 'kpi_financeiro':
        $arrTagEstrutura['tituloArea'] = 'RECEBÍVEIS';
        $arrTagEstrutura['conteudoArea'] = file_get_contents(__DIR__ . '/html/kpi_financeiro.html');
        break;
   case 'kpi_marketing':
        $arrTagEstrutura['tituloArea'] = 'Painel Growth & CRM | Nutricional';
        $arrTagEstrutura['conteudoArea'] = file_get_contents(__DIR__ . '/html/kpi_marketing.html');
        break;
    case 'conferencia_xml':
        $arrTagEstrutura['tituloArea'] = 'Conferência de XML';
        $arrTagEstrutura['conteudoArea'] = file_get_contents(__DIR__ . '/html/conferencia_xml.html');
        break;

    case 'separacao':
        $arrTagEstrutura['tituloArea'] = 'Separação de Pedidos';
        $arrTagEstrutura['conteudoArea'] = file_get_contents(__DIR__ . '/html/separacao.html');
        break;

    case 'monitorlogistica':
        $arrTagEstrutura['tituloArea'] = 'Monitor Logística';
        $arrTagEstrutura['conteudoArea'] = file_get_contents(__DIR__ . '/html/monitorlogistica.html');
        break;

    case 'auditorialogistica':
        $arrTagEstrutura['tituloArea'] = 'Auditoria Logística';
        $arrTagEstrutura['conteudoArea'] = file_get_contents(__DIR__ . '/html/auditorialogistica.html');
        break;

    case 'carregamento':
        $arrTagEstrutura['tituloArea'] = 'Controle de Carregamento';
        $arrTagEstrutura['conteudoArea'] = file_get_contents(__DIR__ . '/html/carregamento.html');
        break;

    case 'catalogo':
        $arrTagEstrutura['tituloArea'] = 'Catálogo de Produtos | Nutricional';
        $objCat = new templateParser('html/catalogo.html');
        $objCat->parseTemplate(['url_base' => DOMINIO, 'pdf_visualiza' => 'catalogo_nutricional_2026_light.pdf', 'pdf_download' => 'catalogo_nutricional_2026.pdf']);
        $arrTagEstrutura['conteudoArea'] = $objCat->display();
        break;


    /* ==========================================================================
       1. CASES PARA API ESCONDIDOS
       ========================================================================== */
		
case 'get_resumo_embarque':
    header('Content-Type: application/json');
    $idembarque = $_GET['embarque'] ?? $input['embarque'] ?? '';
    try {
        $sql = "SELECT 
                    COUNT(DISTINCT pi.iditem) as total_itens,
                    COUNT(DISTINCT p.idpedido) as qt_pedido,
                    SUM(pi.qt * i.pesobruto) as totalpesobruto
                FROM pedido_item pi
                JOIN pedido p ON p.idpedido = pi.idpedido
                JOIN item i ON i.iditem = pi.iditem
                WHERE p.idembarque = ? AND pi.ativo = 'S'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$idembarque]);
        echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["erro" => $e->getMessage()]);
    }
    exit;
    break;


/* ==========================================================================
   1. CASES PARA SEPARAÇÃO (REVISADOS PARA MODO LIVE)
   ========================================================================== */

case 'get_embarques_pendentes':
    header('Content-Type: application/json; charset=utf-8');
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    try {
        $stmt = $pdo->prepare("SELECT DISTINCT 
                                    ep.idembarque, 
                                    ep.observacao as rota, 
                                    ep.placa,
                                    COALESCE(s.status_atual, 'PENDENTE') as status_logistico
                               FROM embarque_pedido ep
                               LEFT JOIN embarque_status_log s ON s.idembarque = ep.idembarque
                               WHERE ep.pex_conferido = 'N' 
                                 AND ep.idfilial IN (1,6)
                                 AND ep.data >= (CURRENT_DATE - INTERVAL '15 days')
                               ORDER BY ep.idembarque DESC");
        $stmt->execute();
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Exception $e) {
        echo json_encode(["erro" => $e->getMessage()]);
    }
    exit;
    break;
case 'get_itens_separacao':
    header('Content-Type: application/json; charset=utf-8');
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    
    $idembarque = $input['embarque'] ?? $_GET['embarque'] ?? '';
    $ordem = (isset($input['ordem']) && strtoupper($input['ordem']) === 'DESC') ? 'DESC' : 'ASC';

    if (empty($idembarque)) {
        echo json_encode([]);
        exit;
    }

    try {
        $sql = "SELECT
		COALESCE((SELECT STRING_AGG(idbarra, ',') FROM codigo_barra WHERE iditem = pi.iditem), 'SEM_BARRA') AS todos_codigos,
                    i.referencia,
                    pi.iditem AS cod_item,
                    i.descricao AS nome_item,
                    i.path_foto_master AS foto,
                    i.idsecao,
                    (ef.saldoatual - COALESCE(pil.total_separado, 0)) AS saldoitem,
                    SUM(pi.qt) AS quant_embarque,
                    COALESCE(pil.total_separado, 0) AS ja_separado,
                    COALESCE(pic.total_carregada, 0) AS ja_carregado
                FROM pedido_item pi
                JOIN pedido p ON p.idpedido = pi.idpedido
                JOIN item i ON i.iditem = pi.iditem
                LEFT JOIN (
                    SELECT iditem, SUM(qt_separada) AS total_separado
                    FROM pedido_item_logistica
                    WHERE idembarque = ?
                    GROUP BY iditem
                ) pil ON pil.iditem = pi.iditem
                LEFT JOIN (
                    SELECT iditem, SUM(qt_carregada) AS total_carregada
                    FROM pedido_item_carregamento
                    WHERE idembarque = ?
                    GROUP BY iditem
                ) pic ON pic.iditem = pi.iditem
                LEFT JOIN (
                    SELECT idfilial, iditem, saldoatual
                    FROM estoque_filial
                ) ef ON ef.idfilial = pi.idfilial AND ef.iditem = pi.iditem
                WHERE p.idembarque = ? 
                  AND pi.ativo = 'S'
                GROUP BY 
                    pi.iditem, i.referencia, i.descricao, i.path_foto_master, i.idsecao, ef.saldoatual, pil.total_separado, pic.total_carregada, pi.idfilial
                ORDER BY i.idsecao $ordem";
                
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$idembarque, $idembarque, $idembarque]);
        $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($itens as &$item) {
            // Extrai o primeiro código da lista para exibição visual no card
            $lista = explode(',', $item['todos_codigos']);
            $item['cod_barras'] = $lista[0];

            $item['quant_embarque'] = round((float)$item['quant_embarque'], 4);
            $item['ja_separado'] = round((float)$item['ja_separado'], 4);
            $item['saldoitem'] = round((float)$item['saldoitem'], 4);
            $item['ja_carregado'] = round((float)$item['ja_carregado'], 4);
            $item['saldo_restante'] = round($item['quant_embarque'] - $item['ja_separado'], 4);
            
            // Status para o front-end
            if ($item['ja_separado'] <= 0.0001) {
                $item['status_logistico'] = 0;
            } elseif ($item['ja_separado'] < ($item['quant_embarque'] - 0.01)) {
                $item['status_logistico'] = 1;
            } else {
                $item['status_logistico'] = 2;
            }
        }
        
        echo json_encode($itens);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["erro" => "Erro ao buscar itens: " . $e->getMessage()]);
    }
    exit;
    break;
case 'confirmar_item_separacao':
    ob_start();
    header('Content-Type: application/json; charset=utf-8');
    
    try {
        $iditem      = (int)($input['iditem'] ?? 0);
        $idembarque  = (int)($input['idembarque'] ?? 0);
        $qt_total_lida = round((float)($input['qtd'] ?? 0), 4);
        $idusuario   = (int)($input['idusuario'] ?? 0);

        if ($iditem <= 0 || $idembarque <= 0 || $qt_total_lida <= 0) {
            throw new Exception("Dados de entrada invalidos: Item $iditem, Emb $idembarque, Qtd $qt_total_lida");
        }

        $pdo->beginTransaction();

        // 1. Status - Use apenas ? para evitar conflitos de tipos de parametros
        $stmtStatus = $pdo->prepare("INSERT INTO embarque_status_log (idembarque, status_atual, data_inicio, idusuario)
                                     VALUES (?, 'SEPARACAO', NOW(), ?)
                                     ON CONFLICT (idembarque) 
                                     DO UPDATE SET status_atual = 'SEPARACAO', idusuario = EXCLUDED.idusuario 
                                     WHERE embarque_status_log.status_atual = 'PENDENTE'");
        $stmtStatus->execute([$idembarque, $idusuario]);

        // 2. Busca os pedidos - ATENÇÃO aos nomes das colunas aqui
        $stmt = $pdo->prepare("SELECT DISTINCT pi.idpedido, pi.iditempedido, pi.qt, 
                                      COALESCE((SELECT SUM(qt_separada) FROM pedido_item_logistica 
                                                WHERE idpedido = pi.idpedido 
                                                  AND iditempedido = pi.iditempedido 
                                                  AND iditem = pi.iditem 
                                                  AND idembarque = ?), 0) as ja_separado
                               FROM pedido_item pi
                               JOIN pedido p ON p.idpedido = pi.idpedido
                               WHERE p.idembarque = ? AND pi.iditem = ? AND pi.ativo = 'S'
                               ORDER BY pi.idpedido ASC");
        $stmt->execute([$idembarque, $idembarque, $iditem]);
        $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$pedidos) throw new Exception("Nenhum item pendente encontrado para o embarque $idembarque.");

        $resto = $qt_total_lida;
        foreach ($pedidos as $p) {
            if ($resto <= 0.0001) break;
            
            $falta = round((float)$p['qt'] - (float)$p['ja_separado'], 4);
            if ($falta <= 0.0001) continue;

            $baixar = min($resto, $falta);
            $nova_qtd_item = round((float)$p['ja_separado'] + $baixar, 4);
            $status_item = ($nova_qtd_item >= (float)$p['qt'] - 0.0001) ? 2 : 1;

            // 3. O INSERT - Verifique se os nomes das colunas batem com o seu banco
            $up = $pdo->prepare("INSERT INTO pedido_item_logistica 
                                 (idpedido, iditempedido, iditem, idembarque, qt_separada, status_separacao, id_separador, data_separacao)
                                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                                 ON CONFLICT (idpedido, iditempedido, iditem, idembarque) 
                                 DO UPDATE SET 
                                    qt_separada = EXCLUDED.qt_separada,
                                    status_separacao = EXCLUDED.status_separacao,
                                    id_separador = EXCLUDED.id_separador,
                                    data_separacao = NOW()");
            
            $up->execute([
                (int)$p['idpedido'], 
                (int)$p['iditempedido'], 
                $iditem, 
                $idembarque, 
                $nova_qtd_item, 
                $status_item, 
                $idusuario
            ]);
            
            $resto = round($resto - $baixar, 4);
        }

        $pdo->commit();
        ob_clean(); 
        echo json_encode(['sucesso' => true]);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        ob_clean();
        // Isso vai mudar o Erro 500 genérico por uma mensagem explicativa no Console
        http_response_code(500);
        echo "ERRO LOGISTICA: " . $e->getMessage();
        exit;
    }
    exit;
    break;
	
case 'estornar_ultima_separacao':
    header('Content-Type: application/json');
    $iditem = $input['iditem'] ?? '';
    $idembarque = $input['idembarque'] ?? '';

    try {
        $pdo->beginTransaction();

        // TRAVA DE SEGURANÇA: Verifica se o item já foi carregado no caminhão
        $check = $pdo->prepare("SELECT SUM(qt_carregada) FROM pedido_item_carregamento WHERE iditem = ? AND idembarque = ?");
        $check->execute([$iditem, $idembarque]);
        $ja_carregado = (float)$check->fetchColumn();

        if ($ja_carregado > 0) {
            throw new Exception("Não é possível estornar esse item pois já está carregado, estornar no carregamento primeiro.");
        }

        $sqlEstorno = "DELETE FROM pedido_item_logistica 
                       WHERE iditem = ? AND idembarque = ? AND iditempedido IN (
                           SELECT pi.iditempedido FROM pedido_item pi
                           JOIN pedido p ON p.idpedido = pi.idpedido
                           WHERE p.idembarque = ? AND pi.iditem = ?
                       )";
        $pdo->prepare($sqlEstorno)->execute([$iditem, $idembarque, $idembarque, $iditem]);

        $pdo->commit();
        echo json_encode(['sucesso' => true]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['sucesso' => false, 'erro' => $e->getMessage()]);
    }
    exit;
    break;

case 'finalizar_separacao_status':
    header('Content-Type: application/json');
    $idembarque = $input['idembarque'] ?? '';
    $idusuario = $input['idusuario'] ?? $_SESSION['idusuario'] ?? 0;

    if (empty($idembarque)) {
        echo json_encode(['sucesso' => false, 'erro' => 'ID do embarque não informado.']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // 1. GARANTE que o status mude para CONCLUIDO, existindo ou não o registro prévio
        $stmtStatus = $pdo->prepare("INSERT INTO embarque_status_log (idembarque, status_atual, data_fim, idusuario)
                                     VALUES (:emb, 'CONCLUIDO', NOW(), :user)
                                     ON CONFLICT (idembarque) 
                                     DO UPDATE SET 
                                        status_atual = 'CONCLUIDO',
                                        data_fim = NOW(),
                                        idusuario = EXCLUDED.idusuario");
        $stmtStatus->execute(['emb' => $idembarque, 'user' => $idusuario]);
        
        // 2. Marca na tabela oficial que a SEPARAÇÃO está pronta
        $pdo->prepare("UPDATE embarque_pedido 
                       SET pex_embarque_pronto = 'S' 
                       WHERE idembarque = ?")
            ->execute([$idembarque]);

        $pdo->commit();
        echo json_encode(['sucesso' => true]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['sucesso' => false, 'erro' => 'Erro ao finalizar separação: ' . $e->getMessage()]);
    }
    exit;
    break;
/* ==========================================================================
   2. CASES PARA CARREGAMENTO (NF GERADA + ITENS SEPARADOS)
   ========================================================================== */

case 'get_embarques_carregamento':
    header('Content-Type: application/json; charset=utf-8');
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    try {
        $stmt = $pdo->prepare("SELECT DISTINCT 
                                    ep.idembarque, 
                                    ep.observacao as rota, 
                                    ep.placa,
                                    COALESCE(s.status_atual, 'PENDENTE') as status_logistico
                               FROM embarque_pedido ep
                               LEFT JOIN embarque_status_log s ON s.idembarque = ep.idembarque
                               WHERE ep.pex_conferido = 'N' 
                                 AND ep.gerou_nf = 'S'
                                 AND s.status_atual IN ('SEPARACAO','CONCLUIDO')
                                 AND ep.idfilial IN (1,6)
                                 AND ep.data >= (CURRENT_DATE - INTERVAL '15 days')
                               ORDER BY ep.idembarque DESC");
        $stmt->execute();
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Exception $e) { 
        echo json_encode(["erro" => $e->getMessage()]); 
    }
    exit; 
    break;

case 'get_itens_carregamento':
    header('Content-Type: application/json; charset=utf-8');
    $idembarque = $input['embarque'] ?? $_GET['embarque'] ?? '';
    $ordem = (isset($input['ordem']) && strtoupper($input['ordem']) === 'DESC') ? 'DESC' : 'ASC';

    if (empty($idembarque)) { echo json_encode([]); exit; }

    try {
        $sql = "SELECT 
                    COALESCE((SELECT STRING_AGG(idbarra, ',') FROM codigo_barra WHERE iditem = pi.iditem), '') AS todos_codigos,
                    i.referencia, 
                    pi.iditem AS cod_item, 
                    i.descricao AS nome_item, 
                    i.path_foto_master AS foto,
                    i.idsecao,
                    SUM(pi.qt) AS quant_embarque,
                    COALESCE((SELECT SUM(qt_separada) FROM pedido_item_logistica WHERE idembarque = ? AND iditem = pi.iditem), 0) AS ja_separado,
                    COALESCE((SELECT SUM(qt_carregada) FROM pedido_item_carregamento WHERE idembarque = ? AND iditem = pi.iditem), 0) AS ja_carregado
                FROM pedido_item pi
                JOIN pedido p ON p.idpedido = pi.idpedido
                JOIN item i ON i.iditem = pi.iditem
                WHERE p.idembarque = ? AND pi.ativo = 'S'
                GROUP BY i.referencia, pi.iditem, i.descricao, i.path_foto_master, i.idsecao
                ORDER BY i.idsecao $ordem";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$idembarque, $idembarque, $idembarque]);
        $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($itens as &$item) {
            $item['pode_carregar'] = ((float)$item['ja_separado'] >= ((float)$item['quant_embarque'] - 0.01)) ? 1 : 0;
            // Mantemos o cod_barras original (primeiro da lista) para exibição na tela
            $cods = explode(',', $item['todos_codigos']);
            $item['cod_barras'] = $cods[0] ?? 'S/ COD';
        }

        echo json_encode($itens);
    } catch (Exception $e) { 
        http_response_code(500);
        echo "ERRO SQL: " . $e->getMessage();
    }
    exit; 
    break;
	
case 'confirmar_item_carregamento':
    ob_start(); 
    header('Content-Type: application/json; charset=utf-8');
    
    // Captura e conversão de tipos
    $iditem      = (int)($input['iditem'] ?? 0);
    $idembarque  = (int)($input['idembarque'] ?? 0);
    $qt_lida     = round((float)($input['qtd'] ?? 0), 4);
    $idusuario   = (int)($input['idusuario'] ?? 0);
    $base64Image = $input['imagem'] ?? null; // NOVO: Captura a string da imagem

    try {
        if ($iditem <= 0 || $idembarque <= 0 || $qt_lida <= 0) {
            throw new Exception("Dados inválidos para carga.");
        }

        // =========================================================
        // LÓGICA DE SALVAMENTO DA FOTO FÍSICA
        // =========================================================
        $caminhoRelativo = null;
        if (!empty($base64Image)) {
            // Limpa o prefixo do Base64 gerado pelo Canvas do JS
            $imgStr = str_replace('data:image/jpeg;base64,', '', $base64Image);
            $imgStr = str_replace(' ', '+', $imgStr);
            $imgData = base64_decode($imgStr);

            // Cria o diretório na raiz do portal se não existir
            $diretorio = __DIR__ . '/uploads/carregamento/';
            if (!is_dir($diretorio)) {
                mkdir($diretorio, 0775, true);
            }

            // Define o nome único e salva o arquivo
            $nomeArquivo = "carga_{$idembarque}_{$iditem}_" . time() . ".jpg";
            $caminhoFisico = $diretorio . $nomeArquivo;

            if (file_put_contents($caminhoFisico, $imgData)) {
                $caminhoRelativo = "uploads/carregamento/" . $nomeArquivo;
            }
        }

        $pdo->beginTransaction();
        
        // BUSCA O QUE FOI SEPARADO: Precisamos do idpedido e iditempedido exatos
        $stmt = $pdo->prepare("SELECT pi.idpedido, pi.iditempedido, pi.qt, 
                                      COALESCE(l.qt_separada, 0) as qt_separada, 
                                      COALESCE(c.qt_carregada, 0) as ja_carregado
                               FROM pedido_item pi
                               JOIN pedido p ON p.idpedido = pi.idpedido
                               LEFT JOIN pedido_item_logistica l ON l.idpedido = pi.idpedido 
                                    AND l.iditempedido = pi.iditempedido 
                                    AND l.idembarque = p.idembarque
                               LEFT JOIN pedido_item_carregamento c ON c.idpedido = pi.idpedido 
                                    AND c.iditempedido = pi.iditempedido 
                                    AND c.idembarque = p.idembarque
                               WHERE p.idembarque = ? AND pi.iditem = ? AND pi.ativo = 'S'
                               ORDER BY pi.idpedido ASC");
        $stmt->execute([$idembarque, $iditem]);
        $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$pedidos) throw new Exception("Item não encontrado no embarque.");

        $resto = $qt_lida;
        foreach ($pedidos as $p) {
            if ($resto <= 0.0001) break;

            // Só carregamos o que a logística separou
            $falta_no_caminhao = round((float)$p['qt_separada'] - (float)$p['ja_carregado'], 4);
            if ($falta_no_caminhao <= 0.0001) continue;

            $baixar = min($resto, $falta_no_caminhao);
            $novo_total_carregado = round((float)$p['ja_carregado'] + $baixar, 4);
            
            // O INSERT agora usa os 4 campos da Primary Key e insere/atualiza a foto
            $up = $pdo->prepare("INSERT INTO pedido_item_carregamento 
                                 (idpedido, iditempedido, iditem, idembarque, qt_carregada, id_conferente, data_carregamento, path_foto_conferencia)
                                 VALUES (?, ?, ?, ?, ?, ?, NOW(), ?) 
                                 ON CONFLICT (idpedido, iditempedido, iditem, idembarque) 
                                 DO UPDATE SET 
                                    qt_carregada = EXCLUDED.qt_carregada, 
                                    data_carregamento = NOW(),
                                    id_conferente = EXCLUDED.id_conferente,
                                    path_foto_conferencia = EXCLUDED.path_foto_conferencia");
            
            $up->execute([
                (int)$p['idpedido'], 
                (int)$p['iditempedido'], 
                $iditem, 
                $idembarque, 
                $novo_total_carregado, 
                $idusuario,
                $caminhoRelativo // <- Grava o caminho da foto no banco
            ]);

            $resto = round($resto - $baixar, 4);
        }

        $pdo->commit(); 
        ob_clean(); 
        echo json_encode(['sucesso' => true]);

    } catch (Exception $e) { 
        if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack(); 
        ob_clean(); 
        http_response_code(500);
        echo json_encode(['sucesso' => false, 'erro' => "ERRO CARGA: " . $e->getMessage()]);
    }
    exit; 
    break;

case 'estornar_item_carregamento':
    header('Content-Type: application/json; charset=utf-8');
    $iditem = $input['iditem'] ?? '';
    $idembarque = $input['idembarque'] ?? '';
    try {
        $pdo->beginTransaction();
        $sqlEstorno = "DELETE FROM pedido_item_carregamento 
                       WHERE iditem = ? AND idembarque = ? AND iditempedido IN (
                           SELECT pi.iditempedido FROM pedido_item pi
                           JOIN pedido p ON p.idpedido = pi.idpedido
                           WHERE p.idembarque = ? AND pi.iditem = ?
                       )";
        $pdo->prepare($sqlEstorno)->execute([$iditem, $idembarque, $idembarque, $iditem]);
        $pdo->commit();
        echo json_encode(['sucesso' => true]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['sucesso' => false, 'erro' => $e->getMessage()]);
    }
    exit;
    break;

case 'finalizar_embarque_caminhao':
    header('Content-Type: application/json; charset=utf-8');
    $idembarque = $input['idembarque'] ?? '';
    $idusuario = $input['idusuario'] ?? $_SESSION['idusuario'] ?? 0;
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO embarque_status_log (idembarque, status_atual, data_fim, idusuario)
                               VALUES (:emb, 'CARREGADO', NOW(), :user)
                               ON CONFLICT (idembarque) 
                               DO UPDATE SET 
                                  status_atual = 'CARREGADO', 
                                  data_fim = NOW(),
                                  idusuario = EXCLUDED.idusuario");
        $stmt->execute(['emb' => $idembarque, 'user' => $idusuario]);
		
		
		
		
		
        $pdo->prepare("UPDATE embarque_pedido SET pex_conferido = 'S',data_carregamento= NOW(), pex_embarque_carregamento = 'S' WHERE idembarque = ?")->execute([$idembarque]);
        $pdo->commit();
        echo json_encode(['sucesso' => true]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['sucesso' => false, 'erro' => $e->getMessage()]);
    }
    exit;
    break;
	

// MONITORAMENTO E RELATORIO DOS REGISTROS SEPARAÇÃO E CARREGAMENTO
case 'get_monitoramento_logistico':
    // Limpa buffers de saída para garantir um JSON limpo
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');

    try {
        $sql = "SELECT 
                    COALESCE(
                        (SELECT u.username FROM pedido_item_carregamento c4 JOIN usuario u ON u.idcliforemp = c4.id_conferente WHERE c4.idembarque = ep.idembarque ORDER BY c4.data_carregamento DESC LIMIT 1),
                        (SELECT u.username FROM pedido_item_logistica l4 JOIN usuario u ON u.idcliforemp = l4.id_separador WHERE l4.idembarque = ep.idembarque ORDER BY l4.data_separacao DESC LIMIT 1),
                        u_log.username, '---'
                    ) AS operador,
                    ep.idembarque, 
                    ep.observacao AS rota, 
                    ep.placa,
                    ep.totalpesobruto as peso,
                    (SELECT fantasia FROM cliforemp WHERE idcliforemp = ep.identregador) as motorista,
                    
                    CASE 
                        WHEN ep.pex_embarque_carregamento = 'S' OR ep.pex_conferido = 'S' THEN 'CARREGADO'
                        WHEN ep.pex_embarque_pronto = 'S' THEN 'CONCLUIDO'
                        WHEN (SELECT COUNT(*) FROM pedido_item_logistica WHERE idembarque = ep.idembarque) > 0 THEN 'SEPARACAO'
                        ELSE 'PENDENTE'
                    END as status_atual,

                    (SELECT COUNT(DISTINCT pi.iditem) FROM pedido_item pi JOIN pedido p ON p.idpedido = pi.idpedido WHERE p.idembarque = ep.idembarque AND pi.ativo = 'S') AS total_itens_unicos,
                    (SELECT COUNT(DISTINCT l2.iditem) FROM pedido_item_logistica l2 WHERE l2.idembarque = ep.idembarque AND l2.status_separacao = 2) AS itens_concluidos_sep,
                    (SELECT COUNT(DISTINCT c2.iditem) FROM pedido_item_carregamento c2 WHERE c2.idembarque = ep.idembarque AND (SELECT SUM(qt_carregada) FROM pedido_item_carregamento WHERE iditem = c2.iditem AND idembarque = ep.idembarque) >= (SELECT SUM(qt) FROM pedido_item pi JOIN pedido p ON p.idpedido = pi.idpedido WHERE p.idembarque = ep.idembarque AND pi.iditem = c2.iditem AND pi.ativo = 'S') - 0.01) AS itens_concluidos_car,

                    (SELECT i.descricao || '|' || i.path_foto_master || '|' || to_char(l3.data_separacao, 'HH24:MI:SS') || '|' || 
                            (SELECT SUM(qt_separada) FROM pedido_item_logistica WHERE iditem = l3.iditem AND idembarque = ep.idembarque) || '|' ||
                            (SELECT SUM(pi2.qt) FROM pedido_item pi2 JOIN pedido p2 ON p2.idpedido = pi2.idpedido WHERE pi2.iditem = l3.iditem AND p2.idembarque = ep.idembarque AND pi2.ativo = 'S') || '|' ||
                            u_sep.username
                     FROM pedido_item_logistica l3 JOIN item i ON i.iditem = l3.iditem JOIN usuario u_sep ON u_sep.idcliforemp = l3.id_separador
                     WHERE l3.idembarque = ep.idembarque ORDER BY l3.data_separacao DESC LIMIT 1) as last_sep_info,

                    (SELECT i.descricao || '|' || COALESCE(c3.path_foto_conferencia, i.path_foto_master) || '|' || to_char(c3.data_carregamento, 'HH24:MI:SS') || '|' || 
                            (SELECT SUM(qt_carregada) FROM pedido_item_carregamento WHERE iditem = c3.iditem AND idembarque = ep.idembarque) || '|' ||
                            (SELECT SUM(pi3.qt) FROM pedido_item pi3 JOIN pedido p3 ON p3.idpedido = pi3.idpedido WHERE pi3.iditem = c3.iditem AND p3.idembarque = ep.idembarque AND pi3.ativo = 'S') || '|' ||
                            u_car.username
                     FROM pedido_item_carregamento c3 JOIN item i ON i.iditem = c3.iditem JOIN usuario u_car ON u_car.idcliforemp = c3.id_conferente
                     WHERE c3.idembarque = ep.idembarque ORDER BY c3.data_carregamento DESC LIMIT 1) as last_car_info,

                    GREATEST(COALESCE(s.data_inicio, '1900-01-01'), COALESCE((SELECT MAX(data_separacao) FROM pedido_item_logistica WHERE idembarque = ep.idembarque), '1900-01-01')) AS ultima_atividade
                FROM embarque_pedido ep
                LEFT JOIN embarque_status_log s ON s.idembarque = ep.idembarque
                LEFT JOIN usuario u_log ON u_log.idcliforemp = s.idusuario
                WHERE ep.data >= (CURRENT_DATE - INTERVAL '30 days') AND ep.idfilial IN (1,6)
                GROUP BY 
                    ep.idembarque, ep.observacao, ep.placa, ep.identregador, ep.pex_embarque_carregamento, 
                    ep.pex_conferido, ep.pex_embarque_pronto, u_log.username, s.data_inicio
                ORDER BY ultima_atividade DESC, ep.idembarque DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode($dados);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["erro" => $e->getMessage()]);
    }
    exit;
    break;
	
	/* ==========================================================================
   CASE: DETALHES DA CONFERÊNCIA (LOG DE BIPS DO CARD)
   ========================================================================== */
case 'get_detalhes_conferencia':
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json');
    $id = intval($_GET['idembarque'] ?? 0);
    
    try {
        $sql = "SELECT i.descricao as produto, i.path_foto_master as foto, NULL as foto_capturada, l.qt_separada as qtd, u.username as operador, 
                       to_char(l.data_separacao, 'HH24:MI:SS') as hora, 'SEPARAÇÃO' as etapa
                FROM pedido_item_logistica l
                JOIN item i ON i.iditem = l.iditem
                JOIN usuario u ON u.idcliforemp = l.id_separador
                WHERE l.idembarque = ?
                
                UNION ALL
                
                SELECT i.descricao, i.path_foto_master, c.path_foto_conferencia as foto_capturada, c.qt_carregada, u.username, 
                       to_char(c.data_carregamento, 'HH24:MI:SS'), 'CARGA'
                FROM pedido_item_carregamento c
                JOIN item i ON i.iditem = c.iditem
                JOIN usuario u ON u.idcliforemp = c.id_conferente
                WHERE c.idembarque = ?
                
                ORDER BY hora DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id, $id]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Exception $e) {
        echo json_encode(["erro" => $e->getMessage()]);
    }
    exit;
    break;

/* ==========================================================================
   CASE: HISTÓRICO GERENCIAL (FILTRADO POR DATA)
   ========================================================================== */
case 'get_historico_gerencial':
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json');
    
    // Se não enviar data, pega os últimos 7 dias
    $data_inicio = $_GET['inicio'] ?? date('Y-m-d', strtotime('-7 days'));
    $data_fim = $_GET['fim'] ?? date('Y-m-d');
    $filtro_user = intval($_GET['usuario'] ?? 0);

    try {
        $sql = "SELECT 
                    ep.idembarque, ep.observacao as rota, ep.placa,
                    (SELECT fantasia FROM cliforemp WHERE idcliforemp = ep.identregador) as motorista,
                    u.username as operador_principal,
                    ep.pex_embarque_pronto, ep.pex_embarque_carregamento,
                    s.status_atual,
                    s.data_inicio as inicio_op,
                    s.data_fim as fim_op,
                    (SELECT COUNT(*) FROM pedido_item_logistica WHERE idembarque = ep.idembarque) as total_bips
                FROM embarque_pedido ep
                JOIN embarque_status_log s ON s.idembarque = ep.idembarque
                LEFT JOIN usuario u ON u.idcliforemp = s.idusuario
                WHERE s.data_inicio::date BETWEEN :ini AND :fim ";
        
        if ($filtro_user > 0) $sql .= " AND s.idusuario = :user";
        
        $sql .= " ORDER BY s.data_inicio DESC";
        
        $stmt = $pdo->prepare($sql);
        $params = [':ini' => $data_inicio, ':fim' => $data_fim];
        if ($filtro_user > 0) $params[':user'] = $filtro_user;
        
        $stmt->execute($params);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Exception $e) {
        echo json_encode(["erro" => $e->getMessage()]);
    }
    exit;
/* ==========================================================================
   CASE 1: DASHBOARD FINANCEIRO INTEGRAL (LIVE) - VERSÃO 2026
   ========================================================================== */
case 'get_dados_dashboard_financeiro':
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    
    $diasRecup = intval($input['dias_recup'] ?? 120);
    // CORREÇÃO MATEMÁTICA: Se <= 7, usa o número. Se > 7, usa 8 (pois >= 8 é igual a > 7)
    $corteAtraso = ($diasRecup > 0 && $diasRecup <= 7) ? $diasRecup : 8;

    $uid   = intval($input['idusuario'] ?? 0);
    $nivel = $input['nivel'] ?? 'filial';
    $fid   = intval($input['filtro_id'] ?? 0);

    try {
        $stUser = $pdo->prepare("SELECT username, dash_filiais, dash_gestores FROM usuario WHERE idcliforemp = :uid");
        $stUser->execute(['uid' => $uid]);
        $userRow = $stUser->fetch(PDO::FETCH_ASSOC);
        
        $nomeLogado = $userRow['username'] ?? 'USUÁRIO';
        
        $dashFiliais = !empty($userRow['dash_filiais']) ? explode(',', $userRow['dash_filiais']) : [];
        $dashGestores = !empty($userRow['dash_gestores']) ? explode(',', $userRow['dash_gestores']) : [];

        if (!empty($dashFiliais)) {
            $inFiliais = implode(',', array_map('intval', $dashFiliais));
            $sqlF = "SELECT DISTINCT idfilial, nome FROM filial WHERE inativo = 'N' AND idfilial IN ($inFiliais) ORDER BY idfilial";
        } else {
            $sqlF = "SELECT DISTINCT f.idfilial, f.nome FROM filial f JOIN vw_analise_receber_geral_cliente v ON v.idfilial = f.idfilial WHERE v.idvendrepre = $uid ORDER BY f.idfilial";
        }
        $stF = $pdo->query($sqlF);
        $listaFiliais = $stF->fetchAll(PDO::FETCH_ASSOC);

        if ($fid === 0 && count($listaFiliais) > 0) $fid = $listaFiliais[0]['idfilial'];

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

        $params = [];
        $joinExtra = ""; 
        $whereDrill = "";
        $whereCards = "";
        $campoFiltroTaxa = "vfe.idfilial";

        switch ($nivel) {
            case 'filial':
                $select = "f.idfilial as id, f.nome as nome";
                $joinExtra = "JOIN filial f ON f.idfilial = varg.idfilial";
                $groupBy = "f.idfilial, f.nome";
                $whereDrill = "varg.idfilial = $fid";
                $whereCards = "varg.idfilial = $fid";
                $campoFiltroTaxa = "vfe.idfilial";
                break;
            case 'gestor':
                $select = "varg.idsupervisor as id, varg.nomegestor as nome";
                $groupBy = "varg.idsupervisor, varg.nomegestor";
                $whereDrill = "varg.idfilial = $fid";
                $whereCards = "varg.idfilial = $fid";
                $campoFiltroTaxa = "vfe.idfilial";
                break;
            case 'representante':
                $select = "varg.idvendrepre as id, varg.nomerepresentante as nome";
                $groupBy = "varg.idvendrepre, varg.nomerepresentante";
                $whereDrill = "varg.idsupervisor = $fid";
                $whereCards = "varg.idsupervisor = $fid"; 
                $campoFiltroTaxa = "vfe.idgestor";
                break;
          case 'cliente':
                $select = "varg.idcliforemp as id, varg.cliente as nome, 
                           (SELECT vfe.dias_atraso FROM vw_financeiro_eventos_geral vfe 
                            WHERE vfe.idcliforemp = varg.idcliforemp ORDER BY vfe.ultimo_evento DESC NULLS LAST LIMIT 1) as dias_em_atraso,
                           (SELECT vfe.ult_evento_dias FROM vw_financeiro_eventos_geral vfe 
                            WHERE vfe.idcliforemp = varg.idcliforemp ORDER BY vfe.ultimo_evento DESC NULLS LAST LIMIT 1) as dias_ultimo_evento,
                           COALESCE((SELECT vfe.usuariocriador FROM vw_financeiro_eventos_geral vfe 
                            WHERE vfe.idcliforemp = varg.idcliforemp ORDER BY vfe.ultimo_evento DESC NULLS LAST LIMIT 1), 'SEM REGISTRO') as usuario_evento";
                $joinExtra = ""; 
                $groupBy = "varg.idcliforemp, varg.cliente";
                $whereDrill = "varg.idvendrepre = $fid AND varg.vencidos > 0";
                $whereCards = "varg.idvendrepre = $fid"; 
                $campoFiltroTaxa = "vfe.idrepresentante";
                break;
        }

        $sqlRec = "SELECT 
            -- Cenário 1 usando o corte inteligente (>= $corteAtraso)
            SUM(CASE WHEN vfe.ultimo_evento IS NULL AND vfe.dias_atraso >= $corteAtraso AND vfe.valorsaldo > 0 THEN 1 ELSE 0 END) as cenario_1,
            SUM(CASE WHEN vfe.ultimo_evento IS NOT NULL AND vfe.valorsaldo > 0 THEN 1 ELSE 0 END) as cenario_2,
            SUM(CASE WHEN vfe.ultimo_evento IS NOT NULL AND vfe.valorsaldo <= 0.01 THEN 1 ELSE 0 END) as cenario_3
        FROM vw_financeiro_eventos_geral vfe 
        WHERE $campoFiltroTaxa = $fid 
        AND vfe.vencimento >= (CURRENT_DATE - INTERVAL '$diasRecup days')
        AND ($travaEventos)";
        
        $stRec = $pdo->query($sqlRec);
        $rowRec = $stRec->fetch(PDO::FETCH_ASSOC);
        
        $c1 = (int)($rowRec['cenario_1'] ?? 0);
        $c2 = (int)($rowRec['cenario_2'] ?? 0);
        $c3 = (int)($rowRec['cenario_3'] ?? 0);
        
        $baseTotalAcoes = $c1 + $c2 + $c3;
        $taxaContexto = ($baseTotalAcoes > 0) ? round(($c3 * 100) / $baseTotalAcoes, 2) : 0;

        $sql = "SELECT $select, 
                       SUM(COALESCE(varg.vencidos,0))::float as vencidos, 
                       SUM(COALESCE(varg.total_receber,0))::float as total_receber,
                       SUM(COALESCE(varg.dias_60 + varg.mais_60_dias,0))::float as valor_iap
                FROM vw_analise_receber_geral_cliente varg
                $joinExtra
                WHERE ($travaHierarquia) AND ($whereDrill)
                GROUP BY $groupBy ORDER BY vencidos DESC";
        $stmt = $pdo->query($sql);
        $tabela = $stmt->fetchAll(PDO::FETCH_ASSOC);

     foreach ($tabela as &$r) {
 
            $r['iag'] = ($r['total_receber'] > 0) ? round(($r['vencidos'] * 100) / $r['total_receber'], 2) : 0;
       
            if ($r['iag'] > 10) {
                $r['performance'] = 'CRÍTICO';
            } elseif ($r['iag'] >= 5) {
                $r['performance'] = 'ATENÇÃO';
            } else {
                $r['performance'] = 'SAUDÁVEL';
            }
        }

        $sqlSum = "SELECT SUM(COALESCE(varg.total_receber,0))::float as total, 
                          SUM(COALESCE(varg.vencidos,0))::float as vencidos,
                          SUM(COALESCE(varg.dias_60 + varg.mais_60_dias,0))::float as valor_iap,
                          SUM(COALESCE(varg.dias_30,0))::float as d30, 
                          SUM(COALESCE(varg.dias_60,0))::float as d60, 
                          SUM(COALESCE(varg.mais_60_dias,0))::float as d90 
                   FROM vw_analise_receber_geral_cliente varg
                   WHERE ($travaHierarquia) AND ($whereCards)";
        $stSum = $pdo->query($sqlSum);

     echo json_encode([
            "config" => ["usuario" => $nomeLogado, "filiais" => $listaFiliais, "filial_padrao" => $fid],
            "resumo_filial" => $stSum->fetch(PDO::FETCH_ASSOC),
            "tabela" => $tabela,
            "taxa_recup" => $taxaContexto,
            "recup_detalhe" => [
                "pagos" => $c3,
                "total" => $baseTotalAcoes
            ]
        ]);

    } catch (Exception $e) { echo json_encode(["erro" => $e->getMessage()]); }
    exit;
    break;
	
/* ==========================================================================
   CASE 2: HISTÓRICO DINÂMICO (ULTIMAS 5 SEMANAS DO MESMO DIA)
   ========================================================================== */
case 'get_historico_kpi':
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    
    try {
        $uid  = intval($input['idusuario'] ?? 0);
        $tipo = $input['tipo'] ?? 'iag_calculado';
        $fid  = intval($input['filtro_id'] ?? 0); 
        $dia  = intval($input['dia_semana'] ?? date('w')); 

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
        
        $stmt = $pdo->query($sql);
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(array_reverse($resultados));

    } catch (Exception $e) {
        echo json_encode([]);
    }
    exit;
    break;
case 'get_lista_usuarios_historico':
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    
    $uid = intval($input['idusuario'] ?? 0);
    $fid = intval($input['idfilial'] ?? 0); 

    try {
        $stUser = $pdo->prepare("SELECT dash_filiais, dash_gestores FROM usuario WHERE idcliforemp = :uid");
        $stUser->execute(['uid' => $uid]);
        $userRow = $stUser->fetch(PDO::FETCH_ASSOC);

        $isMaster = (empty($userRow['dash_gestores']) && !empty($userRow['dash_filiais']));

        $whereLogado = "";
        if ($isMaster) {
            $whereLogado = "1=1"; 
        } elseif (!empty($userRow['dash_gestores'])) {
            $inG = implode(',', array_map('intval', explode(',', $userRow['dash_gestores'])));
            $whereLogado = "(u.idcliforemp IN ($inG) OR u.idcliforemp IN (SELECT idrepresentante FROM vw_gestor_repre WHERE idgestor IN ($inG)))";
        } else {
            $whereLogado = "u.idcliforemp = $uid"; 
        }

        $whereFilial = "";
        if ($fid > 0) {
            $whereFilial = " AND (
                (u.dash_filiais IS NOT NULL AND u.dash_filiais != '' AND ',' || REPLACE(u.dash_filiais, ' ', '') || ',' LIKE '%," . $fid . ",%')
                OR 
                ((u.dash_filiais IS NULL OR u.dash_filiais = '') AND EXISTS (SELECT 1 FROM kpi_financeiro_historico h WHERE h.idusuario = u.idcliforemp AND h.idfilial = $fid))
            )";
        } else {
            if ($isMaster) {
                $dashF = explode(',', $userRow['dash_filiais']);
                $inF = implode(',', array_map('intval', $dashF));
                $whereFilial = " AND (
                    (u.dash_filiais IS NOT NULL AND u.dash_filiais != '')
                    OR 
                    ((u.dash_filiais IS NULL OR u.dash_filiais = '') AND EXISTS (SELECT 1 FROM kpi_financeiro_historico h WHERE h.idusuario = u.idcliforemp AND h.idfilial IN ($inF)))
                )";
            }
        }

        $sql = "SELECT DISTINCT u.idcliforemp as id, u.username as nome 
                FROM usuario u
                WHERE u.idcliforemp IS NOT NULL 
                AND ($whereLogado) 
                $whereFilial
                ORDER BY u.username ASC";
                
        $stmt = $pdo->query($sql);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["erro" => $e->getMessage()]);
    }
    exit;
    break;

/* ==========================================================================
   CASE 3: AUDITORIA DE DETALHES (CORRIGIDO)
   ========================================================================== */
case 'get_detalhes_analise_kpi':
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');

    try {
        $tipo  = $input['tipo'] ?? '';
        $nivel = $input['nivel'] ?? 'filial';
        $fid   = (int)($input['filtro_id'] ?? 0);
        $uid   = (int)($input['idusuario'] ?? 0);

        $stUser = $pdo->prepare("SELECT dash_filiais, dash_gestores FROM usuario WHERE idcliforemp = :uid");
        $stUser->execute(['uid' => $uid]);
        $userRow = $stUser->fetch(PDO::FETCH_ASSOC);

        $dashFiliais = !empty($userRow['dash_filiais']) ? explode(',', $userRow['dash_filiais']) : [];
        $dashGestores = !empty($userRow['dash_gestores']) ? explode(',', $userRow['dash_gestores']) : [];

        // 1. PRIMEIRO CALCULAMOS A SEGURANÇA (TRAVAS)
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

        $sql = "";
        
        // 2. DEPOIS EXECUTAMOS AS CONSULTAS COM AS TRAVAS APLICADAS
        
    if ($tipo === 'titulos_cliente') {
            
            $sql = "SELECT 
                        documento, 
                        valorsaldo::float as valor,
                        to_char(vencimento, 'DD/MM/YYYY') as data_vencimento, -- Adicionado aqui
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
            
            $stmt = $pdo->query($sql);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            exit;

        } elseif ($tipo === 'iag' || $tipo === 'iap') {
            $campo = ($tipo === 'iag') 
                     ? "COALESCE(varg.vencidos, 0)" 
                     : "(COALESCE(varg.dias_60,0) + COALESCE(varg.mais_60_dias,0))";

            $sql = "SELECT varg.cliente as label, SUM($campo)::float as valor 
                    FROM vw_analise_receber_geral_cliente varg 
                    WHERE ($travaHierarquia) 
                    AND $whereFiltro 
                    AND $campo > 0
                    GROUP BY varg.cliente ORDER BY valor DESC LIMIT 10";

        } elseif ($tipo === 'recup') {
            $diasRecup = intval($input['dias_recup'] ?? 120);
            $corteAtraso = ($diasRecup > 0 && $diasRecup <= 7) ? $diasRecup : 8;
            
            $cenario = intval($input['cenario'] ?? 0);

            if ($cenario > 0) {
                $whereCenario = "";
                $campoValor = "vfe.valorsaldo"; 

                if ($cenario === 1) {
                    $whereCenario = "vfe.ultimo_evento IS NULL AND vfe.dias_atraso >= $corteAtraso AND vfe.valorsaldo > 0";
                } elseif ($cenario === 2) {
                    $whereCenario = "vfe.ultimo_evento IS NOT NULL AND vfe.valorsaldo > 0";
                } elseif ($cenario === 3) {
                    $whereCenario = "vfe.ultimo_evento IS NOT NULL AND vfe.valorsaldo <= 0.01";
                    // No cenário 3, usamos o totalrecebido conforme solicitado
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
                
                $stmt = $pdo->query($sql);
                echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
                exit;
            }

            $sql = "SELECT 
                SUM(CASE WHEN vfe.ultimo_evento IS NULL AND vfe.dias_atraso >= $corteAtraso AND vfe.valorsaldo > 0 THEN 1 ELSE 0 END) as c1,
                SUM(CASE WHEN vfe.ultimo_evento IS NOT NULL AND vfe.valorsaldo > 0 THEN 1 ELSE 0 END) as c2,
                SUM(CASE WHEN vfe.ultimo_evento IS NOT NULL AND vfe.valorsaldo <= 0.01 THEN 1 ELSE 0 END) as c3
            FROM vw_financeiro_eventos_geral vfe 
            WHERE ($travaEventos) 
            AND $campoFiltroTaxa 
            AND vfe.vencimento >= (CURRENT_DATE - INTERVAL '$diasRecup days')";
            
            $stmt = $pdo->query($sql);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            $labelDias = ($corteAtraso == 8) ? "+7" : ">=" . $corteAtraso;

            echo json_encode([
                ["label" => "1) Clientes $labelDias dias de atraso sem evento", "valor" => (int)$row['c1'], "is_qtd" => true, "cenario_id" => 1],
                ["label" => "2) Com apontamento, não pago", "valor" => (int)$row['c2'], "is_qtd" => true, "cenario_id" => 2],
                ["label" => "3) Com apontamento, pago (Recuperados)", "valor" => (int)$row['c3'], "is_qtd" => true, "cenario_id" => 3]
            ]);
            exit;
        }

        if (!$sql) throw new Exception("Tipo de auditoria inválido.");

        $stmt = $pdo->query($sql);
        $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($dados ?: []);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["erro" => $e->getMessage()]);
    }
    exit;
    break;
	
	
	//CONFERENCIA DE XML
case 'get_filiais':
        header('Content-Type: application/json');
        // Lista as filiais 1 e 6 com ID visível para facilitar a identificação
        $stmt = $pdo->query("SELECT idempresa, idfilial, razao || ' | ' || idfilial as razao FROM filial WHERE idfilial IN (1,6) ORDER BY idfilial");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;

    case 'get_fornecedores':
        header('Content-Type: application/json');
        $idFilial = filter_input(INPUT_GET, 'idfilial', FILTER_SANITIZE_NUMBER_INT);
        if (!$idFilial) { echo json_encode([]); exit; }

        // Busca fornecedores ativos que possuem OCs abertas na filial selecionada
        $sql = "SELECT DISTINCT c.idcliforemp as idfornecedor, c.razao || ' | '|| COALESCE(c.fantasia, '') as razao, c.cnpj 
                FROM cliforemp c
                JOIN fornecedor f ON f.idcliforemp = c.idcliforemp
                WHERE c.tipocliforemp = 1 
                  AND representante = 'N' 
                  AND c.inativo = 'N'
                  AND EXISTS (
                      SELECT 1 FROM oc 
                      WHERE oc.idcliforemp = f.idcliforemp 
                        AND oc.status = 1 
                        AND oc.idfilial = :idfilial
                  )
                ORDER BY razao";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['idfilial' => $idFilial]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;

    case 'get_ordens_compra':
        header('Content-Type: application/json');
        $idForn = filter_input(INPUT_GET, 'idfornecedor', FILTER_SANITIZE_NUMBER_INT);
        $idFilial = filter_input(INPUT_GET, 'idfilial', FILTER_SANITIZE_NUMBER_INT);

        // Filtra OCs pela Filial e Fornecedor dentro de uma janela de 60 dias
        $sql = "SELECT idoc, TO_CHAR(data, 'YYYY-MM-DD') as data_iso, valortotal as valor_num,
                idoc || ' | ' || TO_CHAR(data, 'DD/MM/YYYY') || ' | R$ ' || TO_CHAR(valortotal, 'FM999G999G990D99') as descricao_select
                FROM oc 
                WHERE status = 1 
                  AND idcliforemp = :id 
                  AND idfilial = :idf
                  AND data BETWEEN current_date - INTERVAL '30 days' AND current_date + INTERVAL '30 days'
                ORDER BY idoc DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $idForn, 'idf' => $idFilial]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;

 case 'api_consulta_oc':
    header('Content-Type: application/json');
    $idOrdem = filter_input(INPUT_GET, 'ordemcompra', FILTER_SANITIZE_NUMBER_INT);
    
    try {
        // --- NOVO: Verificação de conferência anterior ---
        $sqlCheck = "SELECT COUNT(*) FROM oc_operacao 
                     WHERE idoc = :id 
                     AND (descricao LIKE '%Conferência Digital%' 
                          OR descricao LIKE '%Conferência em lote finalizada via Portal%')";
        $stmtCheck = $pdo->prepare($sqlCheck);
        $stmtCheck->execute(['id' => $idOrdem]);
        $jaConferida = ($stmtCheck->fetchColumn() > 0);
        // -------------------------------------------------

        $sql = "SELECT 
		oc.idoc,
		f.cnpj, 
                item.iditem, 
                item.fator_conversao, 
                item.referencia as cprod,
                (select distinct classificacao from classificacaofiscal where idclassificacaofiscal = item.idclassfiscal) as NCM,
                (select distinct classificacao from cest c2 where idcest = item.idcest) as CEST,
                item.descricao as xprod,
                (SELECT cb.idbarra FROM codigo_barra cb WHERE cb.iditem = oc_item.iditem AND cb.principal = 'S' LIMIT 1) as ean_unidade,
                (SELECT cb.idbarra FROM codigo_barra cb WHERE cb.iditem = oc_item.iditem AND cb.principal = 'N' AND cb.gerado_auto = 'N' LIMIT 1) as ean_caixa,      
                oc_item.qt as qCom,
                oc_item.valor as cuncom,
                oc_item.valortotal as vprod,
                case 
                    when item.fator_conversao is not null 
                         and item.fator_conversao <> 0 
                    then (SELECT DESCRICAO FROM UNIDADE UN WHERE IDUNIDADE = ITEM.IDUNIDADEREFERENCIA) 
                    else unidade.descricao 
                end as ucom,
                (select peso from produtos where idproduto = item.iditem) as peso
                FROM oc 
                JOIN oc_item ON oc_item.idoc = oc.idoc
                JOIN cliforemp f ON f.idcliforemp = oc.idcliforemp 
                JOIN item ON item.iditem = oc_item.iditem
                JOIN unidade ON unidade.idunidade = oc_item.idunidade
                WHERE oc.idoc = :id";
            
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $idOrdem]);
        $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Injeta a informação da auditoria no primeiro item para o JavaScript ler
        if (!empty($itens)) {
            foreach($itens as &$item) {
                $item['ja_conferida'] = $jaConferida;
            }
        }

        echo json_encode($itens);
    } catch (Exception $e) { echo json_encode(['erro' => $e->getMessage()]); }
    exit;
	
		case 'buscar_notas_crm':
    header('Content-Type: application/json');

    $cnpjLimpo = preg_replace('/[^0-9]/', '', $_GET['cnpj'] ?? '');
    $valorBruto = $_GET['valor_oc'] ?? '0';
    $dataOC = $_GET['data_oc'] ?? date('Y-m-d');

    // --- VALIDAÇÃO DE DATA (Trava de 15 dias) ---
    $dataHoje = new DateTime();
    $dataDaOrdem = new DateTime($dataOC);
    $intervalo = $dataHoje->diff($dataDaOrdem);
    
    if ($dataDaOrdem > $dataHoje && $intervalo->days > 15) {
        echo json_encode([
            'aviso' => 'Aviso: Não existem notas emitidas ainda para esta OC.',
            'detalhe' => 'Data da Ordem de Compra muito distante (mais de 15 dias à frente).'
        ]);
        exit;
    }

    // Lógica de conversão de valor
    $valorLimpo = (strpos($valorBruto, ',') !== false) ? str_replace(['.', ','], ['', '.'], $valorBruto) : $valorBruto;
    
    $valorOC = (float)$valorLimpo;
    $vMin = $valorOC * 0.1;
    $vMax = $valorOC * 3.0;

    try {
        $sql = "SELECT numeronf, chave, valortotal as valor, razaoemitente as razao, 
                TO_CHAR(dataemissao, 'DD/MM/YYYY') as emissao, xmlanexado
                FROM crm_processo_xml 
                WHERE documentoemitente = :cnpj
                AND statusmanifesto = 2
                AND gerounf ='N'
                AND dataemissao BETWEEN CAST(:data_oc AS DATE) - INTERVAL '30 days' AND CAST(:data_oc AS DATE) + INTERVAL '30 days'
                AND CAST(valortotal AS NUMERIC) BETWEEN :vMin AND :vMax
                ORDER BY dataemissao DESC LIMIT 8";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':cnpj', $cnpjLimpo);
        $stmt->bindValue(':data_oc', $dataOC);
        $stmt->bindValue(':vMin', $vMin);
        $stmt->bindValue(':vMax', $vMax);
        
        $stmt->execute();
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // ✅ AJUSTE AQUI: Se o array estiver vazio, manda o aviso
        if (empty($resultados)) {
            echo json_encode([
                'aviso' => 'Nenhuma nota fiscal disponível para este fornecedor com valores próximos a R$ ' . number_format($valorOC, 2, ',', '.')
            ]);
        } else {
            echo json_encode($resultados);
        }

    } catch (Exception $e) {
        echo json_encode(['erro' => $e->getMessage()]);
    }
    exit;
case 'api_get_itens_xml':
    // 1. LIMPA O BUFFER (O MAIS IMPORTANTE)
    // Isso remove qualquer espaço em branco enviado por config.php ou conect.php
    while (ob_get_level()) ob_end_clean();
    
    $chave = preg_replace('/[^0-9]/', '', $_GET['chave'] ?? '');
    
    if (!isset($pdo)) {
        header('HTTP/1.1 500 Internal Server Error');
        exit;
    }

    $stmt = $pdo->prepare("SELECT anexo FROM crm_processo_xml WHERE chave = :chave LIMIT 1");
    $stmt->execute(['chave' => $chave]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($res && $res['anexo']) {
        $xml = $res['anexo'];
        
        // 2. LÊ O OID DO POSTGRES (Resource)
        if (is_resource($xml)) {
            $xml = stream_get_contents($xml);
        }

        // 3. ENTREGA O XML PURO E MATA O SCRIPT
        header('Content-Type: application/xml; charset=utf-8');
        // O trim garante que o primeiro caractere enviado seja o "<" do XML
        echo trim($xml);
        exit; 
    } else {
        header('HTTP/1.1 404 Not Found');
        exit;
    }
    break;
	
	case 'api_deletar_item_oc':
        header('Content-Type: application/json');
        $dados = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        
        if (empty($dados['idoc']) || empty($dados['iditem'])) {
            echo json_encode(['sucesso' => false, 'erro' => 'ID da OC ou do Item não fornecido.']);
            exit;
        }

        try {
            $pdo->beginTransaction();

            // 1. Deleta o item específico
            $stmt = $pdo->prepare("DELETE FROM oc_item WHERE idoc = :idoc AND iditem = :iditem");
            $stmt->execute(['idoc' => $dados['idoc'], 'iditem' => $dados['iditem']]);

            // 2. Pega a nova soma exata dos itens restantes
            $stmtSoma = $pdo->prepare("SELECT COALESCE(SUM(valortotal), 0) as total FROM oc_item WHERE idoc = :idoc");
            $stmtSoma->execute(['idoc' => $dados['idoc']]);
            $novoTotalItens = (float)$stmtSoma->fetch(PDO::FETCH_ASSOC)['total'];

            // 3. Atualiza os 3 campos principais do cabeçalho da OC
            $stmtUp = $pdo->prepare("UPDATE oc SET valortotal = :v, valortotalsaldo = :v, valortotalitens = :v WHERE idoc = :idoc");
            $stmtUp->execute(['v' => $novoTotalItens, 'idoc' => $dados['idoc']]);

            // 4. Registra no Log
            $pdo->prepare("INSERT INTO oc_operacao (idoperacao, idoc, acao, descricao, datahora, usuario) 
                           VALUES (NEXTVAL('gi_oc_operacao'), :idoc, 7, :desc, NOW(), 'SISTEMA')")
                ->execute([
                    'idoc' => $dados['idoc'],
                    'desc' => "Item ID {$dados['iditem']} removido na conferência digital. Necessário recalcular impostos no ERP."
                ]);

            $pdo->commit();
            // Retornamos um aviso junto com o sucesso
            echo json_encode(['sucesso' => true, 'aviso' => 'recalcular_erp']);
        } catch (Exception $e) {
            if($pdo->inTransaction()) $pdo->rollBack();
            echo json_encode(['sucesso' => false, 'erro' => $e->getMessage()]);
        }
        exit;
	
	case 'api_atualizar_conferencia_lote':
        header('Content-Type: application/json');
        
        $idoc = $_POST['idoc'] ?? null;
        $itens = isset($_POST['itens']) ? json_decode($_POST['itens'], true) : [];

        if (!$idoc || empty($itens)) {
            echo json_encode(['sucesso' => false, 'erro' => 'Dados incompletos para atualização.']);
            exit;
        }

        try {
            $pdo->beginTransaction();

            // 1. Atualiza linha a linha (Apenas quantidade e total da linha)
            foreach ($itens as $item) {
                $iditem = $item['iditem'];
                $qConf  = floatval($item['quantidade']);

                $stmtPreco = $pdo->prepare("SELECT valor FROM oc_item WHERE idoc = :idoc AND iditem = :iditem");
                $stmtPreco->execute(['idoc' => $idoc, 'iditem' => $iditem]);
                $vUnit = floatval($stmtPreco->fetch(PDO::FETCH_ASSOC)['valor'] ?? 0);

                $stmtUp = $pdo->prepare("UPDATE oc_item SET qt = :q, qtsaldo = :q, valortotal = ROUND(CAST(:q * valor AS NUMERIC), 2) 
                                        WHERE idoc = :idoc AND iditem = :iditem");
                $stmtUp->execute(['q' => $qConf, 'idoc' => $idoc, 'iditem' => $iditem]);
            }

            // 2. Busca a nova soma de todos os itens da OC
            $stmtSoma = $pdo->prepare("SELECT COALESCE(SUM(valortotal), 0) as total FROM oc_item WHERE idoc = :idoc");
            $stmtSoma->execute(['idoc' => $idoc]);
            $novoTotalItensCompleto = (float)$stmtSoma->fetch(PDO::FETCH_ASSOC)['total'];

            // 3. Atualiza os totais principais do cabeçalho
            $stmtHead = $pdo->prepare("UPDATE oc SET valortotal = :v, valortotalsaldo = :v, valortotalitens = :v WHERE idoc = :idoc");
            $stmtHead->execute(['v' => $novoTotalItensCompleto, 'idoc' => $idoc]);

            // 4. Log
            $pdo->prepare("INSERT INTO oc_operacao (idoperacao, idoc, acao, descricao, datahora, usuario) 
                           VALUES (NEXTVAL('gi_oc_operacao'), :idoc, 7, 'Conferência em lote finalizada. Necessário recalcular impostos no ERP.', NOW(), 'SISTEMA')")
                ->execute(['idoc' => $idoc]);

            $pdo->commit();
            // Retornamos um aviso junto com o sucesso
            echo json_encode(['sucesso' => true, 'aviso' => 'recalcular_erp']);
        } catch (Exception $e) {
            if($pdo->inTransaction()) $pdo->rollBack();
            echo json_encode(['sucesso' => false, 'erro' => $e->getMessage()]);
        }
        exit;
	
    case 'api_importar_conferencia':
    header('Content-Type: application/json');
    $dados = json_decode(file_get_contents('php://input'), true);

    try {
        $pdo->beginTransaction();
        
        $stmtCheck = $pdo->prepare("SELECT idempresa, idfilial FROM oc WHERE idoc = :id");
        $stmtCheck->execute(['id' => $dados['idoc']]);
        $ocInfo = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        $valorTotalGeral = 0;
        $descAlteracoes = [];

        foreach ($dados['itens'] as $item) {
            // Alterado para floatval para aceitar pesos decimais (ex: 1.5kg)
            $qConf = floatval($item['qtd_conferida']); 
            $vUnit = floatval($item['valor_unit']);
            
            // Atualiza item, saldo e recalcula o total da linha
            $stmtUp = $pdo->prepare("UPDATE oc_item SET qt = :q, qtsaldo = :q, valortotal = ROUND(CAST(:q * valor AS NUMERIC), 2) 
                                    WHERE idoc = :idoc AND iditem = :iditem");
            $stmtUp->execute(['q' => $qConf, 'idoc' => $dados['idoc'], 'iditem' => $item['iditem']]);
            
            $valorTotalGeral += ($qConf * $vUnit);

            // Log de alteração de quantidade ou preço
            if (abs($qConf - floatval($item['qtd_original'])) > 0.001) {
                $descAlteracoes[] = "Item {$item['iditem']}: Qtd de {$item['qtd_original']} para {$qConf}";
            }
        }

        // Atualiza cabeçalho com o novo valor total calculado
        $obs = " | Conf. Digital " . date('d/m H:i');
        $pdo->prepare("UPDATE oc SET valortotal = :v, valortotalsaldo = :v, obs = COALESCE(obs, '') || :obs WHERE idoc = :id")
            ->execute(['v' => $valorTotalGeral, 'obs' => $obs, 'id' => $dados['idoc']]);

        // Grava Log de Auditoria
        $pdo->prepare("INSERT INTO oc_operacao (idoperacao, idoc, idempresa, idfilial, acao, descricao, datahora, usuario, estacaologistica) 
                       VALUES (NEXTVAL('gi_oc_operacao'), :idoc, :ide, :idf, 7, :desc, NOW(), 'SISTEMA', 'PORTAL')")
            ->execute([
                'idoc' => $dados['idoc'], 
                'ide'  => $ocInfo['idempresa'], 
                'idf'  => $ocInfo['idfilial'], 
                'desc' => "Conferência Finalizada. " . (empty($descAlteracoes) ? 'Sem divergências.' : implode(" | ", $descAlteracoes))
            ]);

        $pdo->commit();
        echo json_encode(['sucesso' => true]);
    } catch (Exception $e) { 
        if($pdo->inTransaction()) $pdo->rollBack(); 
        echo json_encode(['erro' => $e->getMessage()]); 
    }
    exit;
		
		
case 'enviar_email_divergencia':
    header('Content-Type: application/json');
    
    // Recebe os dados via FormData (POST + FILES)
    $idoc = $_POST['idoc'] ?? 'N/A';
    $fornecedor = $_POST['fornecedor'] ?? 'Não informado';

    try {
        $mail = new PHPMailer(true);
        
        // Configurações do Servidor
        $mail->isSMTP();
        $mail->Host       = EMAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = EMAIL_USERNAME;
        $mail->Password   = EMAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = EMAIL_PORT;
        $mail->CharSet    = 'UTF-8';
        $mail->Timeout    = 30;

        // --- Configurações de SSL EXIGIDAS pelo seu servidor ---
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];

        // Remetente e Destinatários
        $mail->setFrom(EMAIL_USERNAME, 'Portal Nutricional');
        $mail->addAddress('alan@nutricionalbr.com');
        $mail->addAddress('robson@nutricionalbr.com');
        $mail->addAddress('faturamento@nutricionalbr.com');

        // Conteúdo
        $mail->isHTML(true);
        $mail->Subject = "DIVERGÊNCIA CRÍTICA OC: $idoc - $fornecedor";

        $msg = "<h2>Relatório de Auditoria de Carga</h2>";
        $msg .= "<p>Foi detectada uma divergência na conferência da <b>OC #$idoc</b> do fornecedor <b>$fornecedor</b>.</p>";
        $msg .= "<p>O arquivo PDF detalhado com as diferenças de quantidade e preços segue em anexo para análise imediata.</p>";
        $msg .= "<br><hr><small>Enviado automaticamente pelo Portal Nutricional em " . date('d/m/Y H:i') . "</small>";
        
        $mail->Body = $msg;

        // Anexo do PDF vindo do FormData
        if (isset($_FILES['pdf']) && $_FILES['pdf']['error'] === UPLOAD_ERR_OK) {
            $mail->addAttachment($_FILES['pdf']['tmp_name'], "Divergencia_OC_{$idoc}.pdf");
        }

        $mail->send();
        echo json_encode(['sucesso' => true]);

    } catch (Exception $e) { 
        echo json_encode(['erro' => "Falha no envio: {$mail->ErrorInfo}"]); 
    }
    exit;


//==========================================
// --- PAINEL ADMIN: MARKETING GROWTH ---
//==========================================

case 'get_dashboard_marketing':
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    try {
        $dados = [];
        
        // 1. KPIs Globais Unificados
        $stLeads = $pdo->query("SELECT 
            COUNT(*) as total_leads,
            COUNT(CASE WHEN status = 'Fechado' THEN 1 END) as vendas,
            COALESCE(SUM(valor_fechamento), 0)::float as faturamento
            FROM mkt_leads_controle");
        $resLeads = $stLeads->fetch(PDO::FETCH_ASSOC);

        $stInv = $pdo->query("SELECT COALESCE(SUM(investimento_dia), 0)::float as total_investido FROM mkt_alimentacao_diaria");
        $resInv = $stInv->fetch(PDO::FETCH_ASSOC);
        
        $investimento = (float)$resInv['total_investido'];
        $leadsCount = (int)$resLeads['total_leads'];
        
        $dados['kpis'] = [
            'leads' => $leadsCount,
            'cpl' => $leadsCount > 0 ? $investimento / $leadsCount : 0,
            'roas' => $investimento > 0 ? $resLeads['faturamento'] / $investimento : 0,
            'taxa_conversao' => $leadsCount > 0 ? ($resLeads['vendas'] / $leadsCount * 100) : 0,
            'faturamento' => $resLeads['faturamento']
        ];

        // 2. Gráfico Semanal e Mensal (CORRIGIDO: Removido o MIN() do GROUP BY)
        $stSem = $pdo->query("SELECT TO_CHAR(data_registro, '\"Sem\" IW - TMMon') as label, COUNT(*) as leads, SUM(valor_fechamento)::float as vendas 
                              FROM mkt_leads_controle GROUP BY 1 ORDER BY MIN(data_registro) ASC");
        $dados['grafico_semanal'] = $stSem->fetchAll(PDO::FETCH_ASSOC);

        $stMes = $pdo->query("SELECT TO_CHAR(data_registro, 'TMMonth/YYYY') as label, COUNT(*) as leads, SUM(valor_fechamento)::float as vendas 
                              FROM mkt_leads_controle GROUP BY 1 ORDER BY MIN(data_registro) ASC");
        $dados['grafico_mensal'] = $stMes->fetchAll(PDO::FETCH_ASSOC);

        // 3. CRM: Últimos Leads
        $stCRM = $pdo->query("SELECT * FROM mkt_leads_controle ORDER BY data_registro DESC, id DESC LIMIT 15");
        $dados['crm'] = $stCRM->fetchAll(PDO::FETCH_ASSOC);

        // 4. Desafios Ativos
        $stM = $pdo->query("SELECT m.*, 
            COALESCE((SELECT COUNT(*) FROM mkt_leads_controle WHERE id_meta = m.id), 0) as leads_realizados,
            COALESCE((SELECT SUM(valor_fechamento) FROM mkt_leads_controle WHERE id_meta = m.id), 0) as fat_realizado
            FROM mkt_metas m WHERE status = 'Ativa' ORDER BY data_fim ASC");
        $dados['metas'] = $stM->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode($dados);
    } catch (Exception $e) {
        http_response_code(500);
        // Proteção anti-tela branca: Converte os acentos do banco para UTF-8 antes de gerar o JSON
        $erro_msg = mb_convert_encoding($e->getMessage(), 'UTF-8', 'auto');
        echo json_encode(['error' => "Erro SQL: " . $erro_msg]);
    }
    exit;

case 'mkt_salvar_lead_crm':
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json');
    $d = json_decode(file_get_contents('php://input'), true);
    $st = $pdo->prepare("INSERT INTO mkt_leads_controle (data_registro, empresa, telefone, cnpj, cidade, produto_interesse, status, origem, qualificado, termometro, valor_fechamento, id_meta, gestor) 
                         VALUES (:data, :emp, :tel, :cnpj, :cid, :prod, :status, :orig, :qual, :term, :val, :meta, :gestor)");
    $st->execute([
        'data' => $d['data'], 'emp' => $d['empresa'], 'tel' => $d['telefone'], 'cnpj' => $d['cnpj'], 'cid' => $d['cidade'],
        'prod' => $d['produto'], 'status' => $d['status'], 'orig' => $d['origem'], 'qual' => ($d['qualificado'] == 'true'),
        'term' => $d['termometro'], 'val' => (float)$d['valor'], 'meta' => (int)$d['id_meta'], 'gestor' => $d['gestor']
    ]);
    echo json_encode(['status' => 'success']);
    exit;
	
	
case 'mkt_alimentar_diario':
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    try {
        $d = json_decode(file_get_contents('php://input'), true);
        
        // UPSERT COM ID_META: Atualiza se for a mesma data no mesmo desafio
        $sql = "INSERT INTO mkt_alimentacao_diaria (data_registro, leads_recebidos, vendas_fechadas, valor_faturado, investimento_dia, idusuario_mkt, id_meta) 
                VALUES (:dt, :ld, :vf, :val, :inv, :uid, :meta)
                ON CONFLICT (data_registro, id_meta) DO UPDATE SET 
                leads_recebidos = mkt_alimentacao_diaria.leads_recebidos + EXCLUDED.leads_recebidos,
                vendas_fechadas = mkt_alimentacao_diaria.vendas_fechadas + EXCLUDED.vendas_fechadas,
                valor_faturado = mkt_alimentacao_diaria.valor_faturado + EXCLUDED.valor_faturado,
                investimento_dia = mkt_alimentacao_diaria.investimento_dia + EXCLUDED.investimento_dia";
        
        $st = $pdo->prepare($sql);
        $st->execute([
            'dt' => $d['data'], 
            'ld' => (int)$d['leads'], 
            'vf' => (int)$d['vendas'],
            'val' => (float)$d['valor'], 
            'inv' => (float)$d['investimento'], 
            'uid' => (int)$d['uid'],
            'meta' => (int)$d['id_meta'] // NOVO: Amarração com o desafio
        ]);
        
        echo json_encode(['status' => 'success']);
    } catch (Exception $e) {
        http_response_code(500);
        $erro_msg = mb_convert_encoding($e->getMessage(), 'UTF-8', 'auto');
        echo json_encode(['error' => "Erro SQL: " . $erro_msg]);
    }
    exit;


// =============================================
// GATILHOS DE DISPARO (CHAMADOS PELO CPANEL)
// =============================================

case 'r3G1sTr4H1sT0r1c0':
    header('Content-Type: text/plain');
    echo "=== INICIANDO GATILHO HISTÓRICO KPI FINANCEIRO ===\n";
    
    try {
        // 1. Geramos o token temporário (Seguindo sua lógica de criptografia)
        $codigoMascarado = Uteis::encrypt('DASH_FIN_HIST_TOTAL', CHAVE_SECRETA);
        
        // 2. Montamos o link completo (Adicionamos o &key= do seu config para o Guzzle passar pelo verificarAcesso)
  $linkCompleto = DOMINIO."/index.php?acao=PR0C3SS4_H1ST0R1_DASH&t0k3n=".urlencode($codigoMascarado)."&key=".urlencode(API_TOKEN);
        
        // 3. Salvamos para auditoria
        if (!Uteis::salvarLink($linkCompleto, 'historico_kpi_global')) {
            throw new Exception("Erro ao salvar o link de histórico no banco.");
        }
        
        echo "Link Seguro Gerado: $linkCompleto\n";
        echo "Disparando requisicao interna via Guzzle...\n";
        
        // 4. Disparo via $httpClient (Guzzle) configurado no topo
        $response = $httpClient->get($linkCompleto, ['verify' => false]);
        $body = $response->getBody()->getContents();

        echo "=== RESPOSTA DO PROCESSO ===\n$body\n";
        echo "✅ Gatilho concluido com sucesso!\n";
        
    } catch (Exception $e) {
        file_put_contents(__DIR__.'/erros_log/cron_errors.log', date('[Y-m-d H:i:s]')." ERRO r3G1sTr4H1sT0r1c0: ".$e->getMessage()."\n", FILE_APPEND);
        echo "❌ ERRO CRÍTICO: " . $e->getMessage();
    }
    exit;

/* ==========================================================================
   PROCESSO REAL DE GRAVAÇÃO (COM NÍVEL DE VISÃO E REFERÊNCIA)
   ========================================================================== */
case 'PR0C3SS4_H1ST0R1_DASH':
    header('Content-Type: text/plain; charset=utf-8');
    
    $tokenEnviado = $_GET['t0k3n'] ?? '';
    if (Uteis::decrypt($tokenEnviado, CHAVE_SECRETA) !== 'DASH_FIN_HIST_TOTAL') {
        die("Acesso negado: Token interno inválido.");
    }

    try {
        // 1. Buscamos as permissões de cada usuário ativo
        // dash_filiais: 0,1,6 | dash_gestores: 15520,13878
        $stmtUsers = $pdo->query("SELECT idcliforemp as uid, dash_filiais, dash_gestores FROM usuario WHERE inativo = 'N'");
        $usuariosPermissoes = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);
        
        $processados = 0;

        // 2. SQL Dinâmico: Ele usa as variáveis enviadas pelo loop (as listas de IDs)
        $sql = "INSERT INTO kpi_financeiro_historico 
                (data_registro, idusuario, idfilial, nivel_visao, id_referencia, vencidos, valor_iap, total_receber, iag_calculado, taxa_recuperacao, iap_calculado, qtd_trabalhados, qtd_recuperados)
                
                SELECT 
                    CURRENT_DATE, 
                    :uid_int,
                    varg.idfilial,
                    -- Nível de visão dinâmico: Prioriza Filial, depois Gestor, depois Representante
                    CASE 
                        WHEN :tem_filial = 1 THEN 'filial'
                        WHEN :tem_gestor = 1 THEN 'gestor'
                        ELSE 'representante'
                    END as nivel_visao,
                    -- ID de Referência dinâmico
                    CASE 
                        WHEN :tem_filial = 1 THEN varg.idfilial
                        WHEN :tem_gestor = 1 THEN varg.idsupervisor
                        ELSE varg.idvendrepre
                    END as id_referencia,
                    SUM(COALESCE(varg.vencidos,0))::float,
                    SUM(COALESCE(varg.dias_60 + varg.mais_60_dias, 0))::float, 
                    SUM(COALESCE(varg.total_receber,0))::float,
                    ROUND(SUM(COALESCE(varg.vencidos,0)) * 100 / NULLIF(SUM(COALESCE(varg.total_receber,0)), 0), 2),
                    
                    /* Taxa de Recuperação com Filtros Dinâmicos */
                    COALESCE((
                        SELECT ROUND(
                            SUM(CASE WHEN vfe.ultimo_evento IS NOT NULL AND vfe.valorsaldo <= 0.01 THEN 1 ELSE 0 END) * 100.0 / 
                            NULLIF(
                                SUM(CASE WHEN vfe.ultimo_evento IS NULL AND vfe.dias_atraso >= 8 AND vfe.valorsaldo > 0 THEN 1 ELSE 0 END) +
                                SUM(CASE WHEN vfe.ultimo_evento IS NOT NULL AND vfe.valorsaldo > 0 THEN 1 ELSE 0 END) +
                                SUM(CASE WHEN vfe.ultimo_evento IS NOT NULL AND vfe.valorsaldo <= 0.01 THEN 1 ELSE 0 END)
                            , 0)
                        , 2)
                        FROM vw_financeiro_eventos_geral vfe 
                        WHERE vfe.idfilial = varg.idfilial 
                        AND vfe.vencimento >= (CURRENT_DATE - INTERVAL '120 days')
                        AND (
                            -- Regras de Segurança da Subquery
                            (:tem_filial = 1 AND vfe.idfilial IN (SELECT unnest(string_to_array(:list_filiais, ','))::int)) OR
                            (:tem_gestor = 1 AND vfe.idgestor IN (SELECT unnest(string_to_array(:list_gestores, ','))::int)) OR
                            (:tem_filial = 0 AND :tem_gestor = 0 AND vfe.idrepresentante = :uid_int)
                        )
                    ), 0),

                    ROUND(SUM(COALESCE(varg.dias_60 + varg.mais_60_dias, 0)) * 100 / NULLIF(SUM(COALESCE(varg.total_receber,0)), 0), 2),

                    /* Quantidade Trabalhados (Dinâmico) */
                    COALESCE((
                        SELECT COUNT(*)
                        FROM vw_financeiro_eventos_geral vfe 
                        WHERE vfe.idfilial = varg.idfilial 
                        AND vfe.vencimento >= (CURRENT_DATE - INTERVAL '120 days')
                        AND (
                            (:tem_filial = 1 AND vfe.idfilial IN (SELECT unnest(string_to_array(:list_filiais, ','))::int)) OR
                            (:tem_gestor = 1 AND vfe.idgestor IN (SELECT unnest(string_to_array(:list_gestores, ','))::int)) OR
                            (:tem_filial = 0 AND :tem_gestor = 0 AND vfe.idrepresentante = :uid_int)
                        )
                    ), 0),

                    /* Quantidade Recuperados (Dinâmico) */
                    COALESCE((
                        SELECT SUM(CASE WHEN vfe.ultimo_evento IS NOT NULL AND vfe.valorsaldo <= 0.01 THEN 1 ELSE 0 END)
                        FROM vw_financeiro_eventos_geral vfe 
                        WHERE vfe.idfilial = varg.idfilial 
                        AND vfe.vencimento >= (CURRENT_DATE - INTERVAL '120 days')
                        AND (
                            (:tem_filial = 1 AND vfe.idfilial IN (SELECT unnest(string_to_array(:list_filiais, ','))::int)) OR
                            (:tem_gestor = 1 AND vfe.idgestor IN (SELECT unnest(string_to_array(:list_gestores, ','))::int)) OR
                            (:tem_filial = 0 AND :tem_gestor = 0 AND vfe.idrepresentante = :uid_int)
                        )
                    ), 0)
                    
                FROM vw_analise_receber_geral_cliente varg
                WHERE (
                    -- Filtro principal dinâmico
                    (:tem_filial = 1 AND varg.idfilial IN (SELECT unnest(string_to_array(:list_filiais, ','))::int))
                    OR 
                    (:tem_gestor = 1 AND varg.idsupervisor IN (SELECT unnest(string_to_array(:list_gestores, ','))::int))
                    OR 
                    (:tem_filial = 0 AND :tem_gestor = 0 AND varg.idvendrepre = :uid_int)
                )
                GROUP BY varg.idfilial, 
                         CASE 
                            WHEN :tem_filial = 1 THEN varg.idfilial
                            WHEN :tem_gestor = 1 THEN varg.idsupervisor
                            ELSE varg.idvendrepre
                         END
                
                ON CONFLICT (data_registro, idusuario, idfilial) DO UPDATE SET
                    nivel_visao = EXCLUDED.nivel_visao,
                    id_referencia = EXCLUDED.id_referencia,
                    vencidos = EXCLUDED.vencidos,
                    valor_iap = EXCLUDED.valor_iap,
                    total_receber = EXCLUDED.total_receber,
                    iag_calculado = EXCLUDED.iag_calculado,
                    taxa_recuperacao = EXCLUDED.taxa_recuperacao,
                    iap_calculado = EXCLUDED.iap_calculado,
                    qtd_trabalhados = EXCLUDED.qtd_trabalhados,
                    qtd_recuperados = EXCLUDED.qtd_recuperados";

        $st = $pdo->prepare($sql);
        $pdo->beginTransaction();

        foreach ($usuariosPermissoes as $u) {
            $temFilial = !empty($u['dash_filiais']) ? 1 : 0;
            $temGestor = !empty($u['dash_gestores']) ? 1 : 0;

            $st->execute([
                'uid_int'        => (int)$u['uid'],
                'tem_filial'     => $temFilial,
                'tem_gestor'     => $temGestor,
                'list_filiais'   => (string)$u['dash_filiais'],
                'list_gestores'  => (string)$u['dash_gestores']
            ]);
            $processados++;
        }

        $pdo->commit();
        echo "✅ Histórico processado dinamicamente para $processados usuários.";

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo "❌ Erro: " . $e->getMessage();
    }
    exit;
	
case 'G3R4l1nK':
    // A trava de segurança ?key=... já foi validada no topo do index.php
    header('Content-Type: text/plain');
    echo "=== INICIANDO GATILHO REPRESENTANTES ===\n";
    
    try {
        // Geramos o token temporário para o processo real
        $codigoMascarado = Uteis::encrypt(TOKEN_EMAIL, CHAVE_SECRETA);
      $linkCompleto = DOMINIO."/index.php?acao=3M411&t0k3n=".urlencode($codigoMascarado)."&key=".urlencode(API_TOKEN);
        
        // Salvamos o link para auditoria interna
        if (!Uteis::salvarLink($linkCompleto, 'representantes')) {
            throw new Exception("Erro ao salvar o link dos representantes no banco.");
        }
        
        echo "Link Seguro Gerado: $linkCompleto\n";
        echo "Disparando requisicao interna via Guzzle...\n";
        
        // Utilizamos o $httpClient já instanciado no topo (Eficiência)
        $responseRep = $httpClient->get($linkCompleto);
        $bodyRep = $responseRep->getBody()->getContents();

        echo "=== RESPOSTA DO PROCESSO ===\n$bodyRep\n";
        echo "✅ Gatilho concluido com sucesso!\n";
        
    } catch (Exception $e) {
        // Log de erro centralizado
        file_put_contents(
            __DIR__.'/erros_log/cron_errors.log', 
            date('[Y-m-d H:i:s]')." ERRO G3R4l1nK: ".$e->getMessage()."\n", 
            FILE_APPEND
        );
        echo "❌ ERRO CRÍTICO: " . $e->getMessage();
    }
    exit;

case 'v3r1f1c4Ult1m0D14':
    // A validação de segurança (?key=CHAVE_MESTRA) já foi feita no topo pela função verificarAcesso()
    header('Content-Type: text/plain');
    
    echo "=== INICIANDO VERIFICAÇÃO DE CALENDÁRIO GESTORES ===\n";
    echo "Data Atual: " . date('d/m/Y H:i:s') . "\n";

    try {
        // Utilizamos a inteligência centralizada na classe Uteis
        if (Uteis::isUltimoDiaUtil()) {
            echo "✅ HOJE É O ÚLTIMO DIA ÚTIL DO MÊS.\n";
            echo "Gerando token de acesso e disparando processo real...\n";
            
            // 1. Gera o token criptografado para o processo de e-mails
            $codigoMascaradoGest = Uteis::encrypt(TOKEN_GESTORES, CHAVE_SECRETA);
        $linkCompletoGest = DOMINIO."/index.php?acao=3M411g3St0&t0k3n=".urlencode($codigoMascaradoGest)."&key=".urlencode(API_TOKEN);
		
            // 2. Dispara a requisição interna usando o cliente HTTP configurado no topo
            // O User-Agent 'CronJob' garante que o processo de destino saiba que é uma automação
            $responseGest = $httpClient->get($linkCompletoGest);
            $bodyGest = $responseGest->getBody()->getContents();
            
            echo "=== RESPOSTA DO PROCESSAMENTO ===\n";
            echo $bodyGest . "\n";
            echo "=================================\n";
            echo "✅ Processo de gestores finalizado com sucesso!\n";
            
        } else {
            // Se não for o último dia útil, o script apenas loga e encerra sem gastar processamento
            echo "⏭️ Hoje NÃO é o último dia útil do mês.\n";
            echo "Dia da semana: " . date('l') . "\n";
            echo "O processo de e-mails para gestores não será disparado hoje.\n";
        }
        
    } catch (Exception $e) {
        // Log de erro específico para falhas no gatilho
        $logDir = __DIR__ . '/erros_log';
        if (!file_exists($logDir)) mkdir($logDir, 0755, true);
        
        file_put_contents(
            $logDir . '/cron_errors.log', 
            date('[Y-m-d H:i:s]') . " ERRO NO GATILHO v3r1f1c4Ult1m0D14: " . $e->getMessage() . "\n", 
            FILE_APPEND
        );
        
        echo "❌ ERRO CRÍTICO NO DISPARO: " . $e->getMessage() . "\n";
    }
    
    exit;
	
	
	
case '3M411g3St0':
    // Configura headers para texto puro se for chamado via Gatilho (Guzzle/CronJob)
    if (strpos($_SERVER['HTTP_USER_AGENT'] ?? '', 'CronJob') !== false) {
        header('Content-Type: text/plain');
    }

    $isCli = (php_sapi_name() === 'cli');
    
    // ✅ DEFINIÇÃO DOS PATHS PARA LOGS PADRONIZADOS
    $logDir = __DIR__ . '/erros_log';
    $logFileGestores = $logDir . '/cron_gestores.log';
    $logFileErrors = $logDir . '/cron_errors.log';
    
    // ✅ GARANTE QUE A PASTA DE LOGS EXISTE
    if (!file_exists($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    
    if (!isset($arrTagEstrutura)) {
        $arrTagEstrutura = [];
    }

    try {
        if (!isset($pdo)) {
            throw new Exception("ERRO CRÍTICO: Conexão com banco (\$pdo) não disponível no escopo global.");
        }
        
        if ($isCli) echo "✅ Banco detectado (Escopo Global)\n";
        
        // ✅ OBTÉM TOKEN
        $t0k3n = $isCli ? ($_SERVER['argv'][2] ?? '') : (filter_input(INPUT_GET, 't0k3n') ?? '');
        
        if (empty($t0k3n)) {
            $msg = $isCli ? "Token não fornecido para execução CLI" : "Acesso negado: Token não fornecido.";
            if ($isCli) die($msg);
            
            $arrTagEstrutura['conteudoArea'] = "<h2 style='color:red;'>{$msg}</h2>";
            break; 
        }
        
        // ✅ VALIDAÇÃO DO TOKEN (Segurança Criptografada)
        $codigoDesmascarado = Uteis::decrypt(urldecode($t0k3n), CHAVE_SECRETA);
        
        if (!hash_equals((string)$codigoDesmascarado, (string)TOKEN_GESTORES)) {
            throw new Exception("Acesso Negado: Token inválido ou expirado.");
        }
        
        if ($isCli) {
            echo "✅ Token válido - Iniciando processamento...\n";
        } else {
            echo "✅ Token válido - Iniciando processamento...<br>";
        }

        // ✅ LÓGICA DE DATA CENTRALIZADA (Classe Uteis)
        $isUltimoDiaUtil = $isCli ? Uteis::isUltimoDiaUtil() : true;
        
        if (!$isUltimoDiaUtil) {
            $logMessage = "[" . date('Y-m-d H:i:s') . "] PROCESSO GESTORES BLOQUEADO - Não é último dia útil\n";
            file_put_contents($logFileGestores, $logMessage, FILE_APPEND);
            
            if ($isCli) {
                echo "Processo gestores não executado: hoje não é o último dia útil do mês\n";
                exit(0);
            }
        } else {
            $modoExecucao = $isCli ? "Último dia útil (Cron)" : "Acesso Web (Bypass de Data)";
            $logMessage = "[" . date('Y-m-d H:i:s') . "] PROCESSO GESTORES EXECUTADO - {$modoExecucao}\n";
            file_put_contents($logFileGestores, $logMessage, FILE_APPEND);
            
            if ($isCli) echo "✅ Condição de execução atendida - Continuando...\n";
        }

        $gestoresArray = [
            13878 => [
                'emails' => ['alan@nutricionalbr.com','a.eleodoro@nutricionalbr.com','robson@nutricionalbr.com','tiago@nutricionalbr.com','financeiro@nutricionalbr.com'], 
                'nome' => 'ADRIANO ROGERIO ELEODORO'
            ],
            15520 => [
                'emails' => ['alan@nutricionalbr.com','michel@nutricionalbr.com','robson@nutricionalbr.com','tiago@nutricionalbr.com','financeiro@nutricionalbr.com'],
                'nome' => 'MICHEL PLATINI DE SOUZA AGUIAR'
            ],
            11371 => [
                'emails' => ['alan@nutricionalbr.com','tales@nutricionalbr.com','robson@nutricionalbr.com','financeiro@nutricionalbr.com'],
                'nome' => 'TALES FERNANDO DE JESUS BINDE'
            ]
        ];

        if ($isCli) echo "✅ Array de gestores carregado: " . count($gestoresArray) . " gestores\n";

        $listaIds = '(' . implode(',', array_keys($gestoresArray)) . ')';
        
        // CONSULTA PRINCIPAL
        $sql = "SELECT
                    user_id_param.out_id as idsupervisor,
                    MAX(varg.nomegestor) AS nomegestor,
                    SUM(varg.vencidos) AS vencidos,
                    SUM(varg.dias_30) AS dias_30,
                    SUM(varg.dias_60) AS dias_60,
                    SUM(varg.mais_60_dias) AS mais_60_dias,
                    SUM(varg.a_vencer) AS a_vencer,
                    SUM(varg.prox_30_dias) AS prox_30_dias,
                    SUM(varg.total_inadimplencia) AS valor_inadimplencia,
                    ROUND(SUM(varg.vencidos) * 100.0 / NULLIF(SUM(varg.total_receber), 0), 2) AS percentual_geral,
                    SUM(varg.total_cliente) AS total_clientes,
                    SUM(varg.total_titulos) AS total_titulos,
                    SUM(varg.total_clientes_com_vencidos) AS total_clientes_vencidos,
                    SUM(varg.total_titulos_vencidos) AS total_titulos_vencidos,
                    ROUND(SUM(varg.dias_30) * 100.0 / NULLIF(SUM(varg.vencidos), 0), 2) AS percentual_30,
                    ROUND(SUM(varg.dias_60) * 100.0 / NULLIF(SUM(varg.vencidos), 0), 2) AS percentual_60,
                    ROUND(SUM(varg.mais_60_dias) * 100.0 / NULLIF(SUM(varg.vencidos), 0), 2) AS percentual_mais_60 
                FROM vw_analise_receber_geral varg 
                CROSS JOIN LATERAL pkg_ema.retorna_lista(REPLACE(REPLACE('" . $listaIds . "','(',''),')',''),',') AS user_id_param(out_id)
                WHERE (
                    (user_id_param.out_id = 11258 AND varg.idvendrepre IN (SELECT DISTINCT idrepresentante FROM vw_gestor_repre WHERE idfilial = 1 AND idgestor NOT IN (5297,11371)) and varg.idfilial = 1 )
                    OR (user_id_param.out_id = 11371 AND varg.idsupervisor = 11371 and varg.idfilial = 6)  
                    OR (user_id_param.out_id = 15520 AND varg.idsupervisor = 15520)
                    OR (user_id_param.out_id = 13878 AND varg.idsupervisor = 13878)
                    OR (user_id_param.out_id = 5297  AND varg.idvendrepre IN (SELECT idrepresentante FROM vw_gestor_repre WHERE inativo = 'N'))
                )
                GROUP BY user_id_param.out_id
                ORDER BY nomegestor";

        file_put_contents($logFileGestores, date('[Y-m-d H:i:s]') . " QUERY PRINCIPAL EXECUTADA\n", FILE_APPEND);

        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $dadosGestores = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($dadosGestores)) {
            throw new Exception("Nenhum dado encontrado para os gestores.");
        }

        // Configuração do PHPMailer
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = EMAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = EMAIL_USERNAME;
        $mail->Password = EMAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = EMAIL_PORT;
        $mail->setFrom(EMAIL_USERNAME, 'Nutricional Distribuidora - Relatório de Inadimplência');
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];

        if (!function_exists('getFiltroGestor')) {
            function getFiltroGestor($idGestor) {
                switch ($idGestor) {
                    case 11258: return ['condicao' => "varg.idvendrepre IN (SELECT DISTINCT idrepresentante FROM vw_gestor_repre WHERE idfilial = 1 AND idgestor NOT IN (5297,11371)) AND varg.idfilial = 1", 'tipo' => 'representantes_filial'];
                    case 11371: return ['condicao' => "varg.idsupervisor = 11371 AND varg.idfilial = 6", 'tipo' => 'supervisor_filial'];
                    case 15520: return ['condicao' => "varg.idsupervisor = 15520", 'tipo' => 'supervisor'];
                    case 13878: return ['condicao' => "varg.idsupervisor = 13878", 'tipo' => 'supervisor'];
                    case 5297:  return ['condicao' => "varg.idvendrepre IN (SELECT idrepresentante FROM vw_gestor_repre WHERE inativo = 'N')", 'tipo' => 'todos_ativos'];
                    default:    return ['condicao' => "varg.idsupervisor = {$idGestor}", 'tipo' => 'supervisor'];
                }
            }
        }

        $enviados = 0; $falhas = 0;

        foreach ($dadosGestores as $gestor) {
            $idGestor = $gestor['idsupervisor'];
            if (!isset($gestoresArray[$idGestor])) continue;
            
            $emailsValidos = [];
            foreach ($gestoresArray[$idGestor]['emails'] as $email) {
                if (filter_var(trim($email), FILTER_VALIDATE_EMAIL)) $emailsValidos[] = trim($email);
            }
            
            if (empty($emailsValidos)) { $falhas++; continue; }

            $filtroGestor = getFiltroGestor($idGestor);
            
            // Busca Representantes e Gera Relatórios
   $sqlRep = "SELECT DISTINCT
            idvendrepre, 
            nomerepresentante as \"Nome do Representante\", 
            valor_carteira as \"Valor Total\", 
            vencidos as \"Vencidos\", 
            percentual_inadimplencia as \"Percentual\",
            total_titulos as \"Total Titulos\",
            total_cliente as \"Total Clientes\",
            dias_30 as \"30 Dias\",
            perc_30_dias as \"Perc. 30 Dias\",
            dias_60 as \"60 Dias\",
            perc_60_dias as \"Perc. 60 Dias\",
            mais_60_dias as \"Mais 60 Dias\",
            perc_mais_60_dias as \"Perc. Mais 60 Dias\"
        FROM vw_analise_receber_geral varg 
        WHERE {$filtroGestor['condicao']}";
            
			$stmtRep = $pdo->prepare($sqlRep);
            $stmtRep->execute();
            $dadosRep = $stmtRep->fetchAll(PDO::FETCH_ASSOC);

            $arquivoExcelRepresentantes = Uteis::gerarExcelRepresentantes($idGestor, $dadosRep);
            
         $sqlCli = "SELECT DISTINCT
            rec.idfilial, 
            cfe.fantasia as \"Nome Fantasia\", 
            rec.documento, 
            rec.vencimento as \"Vencimento\", 
            rec.valorsaldo as \"Valor Saldo\", 
            rec.valor as \"Valor\", 
            rec.dataemissao as \"Data Emissão\", 
            vendrepre.fantasia AS nome_representante,
            vfe.dias_atraso as \"Dias em Atraso\",
            vfe.ult_evento_dias as \"Dias do Último Evento\",
            vfe.usuariocriador as \"Usuário\"
        FROM receber rec 
        JOIN cliforemp cfe ON cfe.idcliforemp = rec.idcliforemp 
        JOIN cliforemp vendrepre ON vendrepre.idcliforemp = rec.idvendrepre 
        LEFT JOIN vw_financeiro_eventos vfe ON vfe.idcliforemp = rec.idcliforemp
        WHERE rec.excluido = 'N' 
          AND rec.vencimento <= CURRENT_DATE - 3 
          AND rec.status IN (2, 3) 
          -- Correção: Filtrar usando IN evita a duplicação causada pelo JOIN
          AND rec.idvendrepre IN (
              SELECT varg.idvendrepre 
              FROM vw_analise_receber_geral varg 
              WHERE {$filtroGestor['condicao']}
          )
        ORDER BY nome_representante, \"Nome Fantasia\"";
		   $stmtCli = $pdo->prepare($sqlCli);
            $stmtCli->execute();
            $dadosCli = $stmtCli->fetchAll(PDO::FETCH_ASSOC);
            $arquivoExcelClientes = Uteis::gerarExcelClientesDetalhado($dadosCli);

            try {
                $mail->clearAddresses(); $mail->clearAttachments();
                foreach ($emailsValidos as $email) $mail->addAddress($email);
                
                if (file_exists($arquivoExcelRepresentantes)) $mail->addAttachment($arquivoExcelRepresentantes, "resumo_representantes.xlsx");
                if (file_exists($arquivoExcelClientes)) $mail->addAttachment($arquivoExcelClientes, "detalhado_clientes.xlsx");

                $mail->Subject = 'Relatório de Inadimplência - ' . date('d/m/Y');
                $mail->Body = Uteis::construirEmailGestor($gestor);
                
                if ($mail->send()) {
                    $enviados++;
                    if ($isCli) echo "✅ Enviado: {$gestor['nomegestor']}\n";
                }
                
                // ✅ LIMPEZA CRÍTICA: Apaga arquivos para não lotar o servidor
                if (file_exists($arquivoExcelRepresentantes)) @unlink($arquivoExcelRepresentantes);
                if (file_exists($arquivoExcelClientes)) @unlink($arquivoExcelClientes);

            } catch (Exception $e) {
                $falhas++;
                file_put_contents($logFileErrors, date('[Y-m-d H:i:s]') . " Erro Gestor {$idGestor}: " . $e->getMessage() . "\n", FILE_APPEND);
            }
            sleep(1);
        }

        $resumoFinal = date('[Y-m-d H:i:s]') . " FINALIZADO: Enviados: $enviados, Falhas: $falhas\n";
        file_put_contents($logFileGestores, $resumoFinal, FILE_APPEND);

        if ($isCli) { echo "\nProcessamento concluído: $enviados enviados.\n"; exit; }
        else { $arrTagEstrutura['conteudoArea'] = "<div style='padding:20px;'><h3>Concluído</h3><p>Enviados: $enviados</p></div>"; }

    } catch (Exception $e) {
        file_put_contents($logFileErrors, date('[Y-m-d H:i:s]') . " ERRO CRÍTICO: " . $e->getMessage() . "\n", FILE_APPEND);
        if ($isCli) die("ERRO: " . $e->getMessage());
        $arrTagEstrutura['conteudoArea'] = "<div style='color:red;'>Erro: " . $e->getMessage() . "</div>";
    }
    break;
	


case '3M411':
// Configura headers para texto puro se for chamado via Gatilho (Guzzle/CronJob)
if (strpos($_SERVER['HTTP_USER_AGENT'] ?? '', 'CronJob') !== false) {
header('Content-Type: text/plain');
}

// Verifica o contexto de execução (CLI ou web)
$isCli = (php_sapi_name() === 'cli');

// Log de controle para execução via CRON
if ($isCli) {
    $logMessage = "[" . date('Y-m-d H:i:s') . "] CRON REPRESENTANTES EXECUTADO - Dia: " . date('d/m/Y') . "\n";
    file_put_contents('/home/nutribr/cron_representantes.log', $logMessage, FILE_APPEND);
}

// Obtém o token (via CLI ou GET)
$t0k3n = $isCli ? ($_SERVER['argv'][2] ?? '') : (filter_input(INPUT_GET, 't0k3n') ?? '');

try {
    // Validação obrigatória do Token para segurança
    Uteis::validarToken($t0k3n, TOKEN_EMAIL, 'representantes');

    echo "✅ Token validado - Processo continuando\n";
    echo "✅ Banco conectado\n";

    // Consulta SQL para obter os dados dos pedidos alterados
    $sql = "SELECT DISTINCT 
                pedido.idvendrepre,
                COALESCE(vend.fantasia, 'Representante não encontrado') AS \"Repre\",
                vend.email AS \"emailRepre\",
                COALESCE(cli.fantasia, 'Cliente não informado') AS \"cliente\", 
                l.datahora AS \"datapedido\",
                l.idpedido,
                case when pp.nomecliente = '' then 'Pedido Digitado Internamente' else REPLACE(REPLACE(REPLACE(pp.nomecliente, 'MERCOS', ''), '[', ''), ']', '') end AS \"numeroPedidoMercos\",
                COALESCE(item.descricao, 'Produto não encontrado') AS \"produto\",
                l.quant_old AS \"qt_anterior\", 
                l.quant_new AS \"qt_nova\",
                CASE 
                    WHEN l.quant_new = 0 THEN 'Item Excluído' 
                    ELSE 'Item Editado' 
                END AS \"motivo\"
            FROM PEDIDO_ITEM_LOG l
            LEFT JOIN item ON (l.iditemold = item.iditem)
            LEFT JOIN pedido ON (l.idpedido = pedido.idpedido) 
            LEFT JOIN palmtop_pedido pp ON (pp.idpedidopda = pedido.idpedidopda)
            LEFT JOIN cliforemp cli ON cli.idcliforemp = pedido.idcliforemp 
            LEFT JOIN cliforemp vend ON vend.idcliforemp = pedido.idvendrepre 
            WHERE l.motivo LIKE '%u item%'
            AND l.quant_old <> l.quant_new 
            AND l.datahora >= CURRENT_DATE - INTERVAL '1 days'
            AND pedido.status IN (5)
            AND item.iditem NOT IN (2181, 1552, 3058)
            AND item.tipo IN (0, 2, 11) 
            AND pedido.idvendrepre NOT IN (10119)
            AND pedido.idfilial IN (1, 6)
            ORDER BY \"Repre\", \"cliente\", \"produto\" ASC";

    echo "Executando consulta...\n";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($resultados)) {
        $msg = "Nenhum resultado encontrado para processar.";
        if ($isCli) {
            echo $msg . "\n";
            file_put_contents(__DIR__ . '/erros_log/cron_representantes.log', "[" . date('Y-m-d H:i:s') . "] Nenhum pedido alterado encontrado\n", FILE_APPEND);
            exit(0);
        }
        $arrTagEstrutura['conteudoArea'] = $msg;
        break;
    }

    echo "✅ Consulta: " . count($resultados) . " registros encontrados\n";

    // Agrupa por representante
    $representantes = [];
    foreach ($resultados as $row) {
        $repId = $row['idvendrepre'];
        if (!isset($representantes[$repId])) {
            $representantes[$repId] = [
                'nome' => $row['Repre'],
                'email' => $row['emailRepre'],
                'pedidos' => []
            ];
        }
        $representantes[$repId]['pedidos'][] = $row;
    }

    echo "✅ Processados " . count($representantes) . " representantes\n";

    // Configuração do PHPMailer com tratamento robusto
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = EMAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = EMAIL_USERNAME;
        $mail->Password = EMAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = EMAIL_PORT;
        $mail->setFrom(EMAIL_USERNAME, 'Nutritional - Sistema de Pedidos');
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];
        $mail->Timeout = 30;
        $mail->Encoding = 'base64';

    } catch (Exception $e) {
        $errorMsg = "Erro na configuração do PHPMailer: " . $e->getMessage();
        if ($isCli) die($errorMsg);
        $arrTagEstrutura['conteudoArea'] = $errorMsg;
        break;
    }

    $mail->SMTPKeepAlive = true;
    // E-mails enviados e resumo
    $enviados = 0;
    $falhas = 0;
    $resumo = [];
    $logErros = [];

    echo "📧 Iniciando envio de emails...\n";

    // Envia e-mail para cada representante
    foreach ($representantes as $rep) {
        if (empty($rep['pedidos'])) continue;
        
        // Higienização completa do e-mail do banco de dados
        $emailBruto = strtolower($rep['email']);
        $emailBruto = str_replace(',', ';', $emailBruto);
        $arrayEmails = explode(';', $emailBruto);
        $emailRepLimpo = trim($arrayEmails[0]);
        
        // Valida o e-mail antes de enviar
        if (!filter_var($emailRepLimpo, FILTER_VALIDATE_EMAIL)) {
            $errorMsg = "E-mail inválido/vazio para {$rep['nome']}: '{$rep['email']}' (Processado como: '{$emailRepLimpo}')";
            $logErros[] = $errorMsg;
            echo "❌ $errorMsg\n";
            continue; // Pula este representante e vai para o próximo
        }

        $corpoEmail = Uteis::construirCorpoEmail($rep['pedidos'], $rep['nome']);
        $assunto = "Relatório de Alterações - {$rep['nome']} - " . date('d/m/Y');
        
        // Configura cópias
        $cc = ['alan@nutricionalbr.com']; 
        $bcc = [];

        try {
            $mail->clearAllRecipients();
            $mail->clearAttachments();
            if (Uteis::enviarEmail($mail, $emailRepLimpo, $rep['nome'], $assunto, $corpoEmail, $cc, $bcc)) {
                $enviados++;
                $resumo[] = [
                    'representante' => $rep['nome'],
                    'email' => $emailRepLimpo,
                    'total_pedidos' => count($rep['pedidos'])
                ];
                echo "✅ E-mail enviado para {$rep['nome']} ({$emailRepLimpo})\n";
            } else {
                throw new Exception("Função de envio retornou False para {$emailRepLimpo}");
            }
        } catch (Exception $e) {
            $falhas++;
            $errorMsg = "❌ Falha ao enviar para {$rep['nome']} ({$emailRepLimpo}): " . $e->getMessage();
            $logErros[] = $errorMsg;
            echo $errorMsg . "\n";
        }
   
        // Pequena pausa entre envios (2 segundo) para evitar spam
        sleep(2);
    }

    // Fecha a conexão SMTP principal após o término do loop
    $mail->smtpClose();

    // Cria e-mail consolidado com relatório completo para a gerência
    $corpoConsolidado = '<div style="font-family: Arial, sans-serif; max-width: 1000px; margin: 0 auto;">';
    $corpoConsolidado .= '<h1 style="color: #0066cc;">Relatório Consolidado de Alterações</h1>';
    $corpoConsolidado .= '<p>Total de representantes: ' . count($representantes) . '</p>';
    $corpoConsolidado .= '<p>E-mails enviados com sucesso: ' . $enviados . '</p>';
    $corpoConsolidado .= '<p>Falhas no envio: ' . $falhas . '</p>';
    $corpoConsolidado .= '<p>Data: ' . date('d/m/Y H:i:s') . '</p>';
    
    if (!empty($logErros)) {
        $corpoConsolidado .= '<div style="background-color: #ffeeee; padding: 15px; border-radius: 5px; margin-bottom: 20px;">';
        $corpoConsolidado .= '<h2 style="color: #cc0000; margin-top: 0;">Erros Ocorridos</h2><ul>';
        foreach ($logErros as $erro) {
            $corpoConsolidado .= '<li style="margin-bottom: 5px;">' . htmlspecialchars($erro) . '</li>';
        }
        $corpoConsolidado .= '</ul></div>';
    }
    $corpoConsolidado .= '</div>';

    // Envia e-mail consolidado de controle (Reabre conexão automaticamente ao enviar)
    try {
        $mail->clearAllRecipients();
        $mail->addAddress('alan@nutricionalbr.com', 'Alan');
        $mail->Subject = 'Relatório Consolidado de Alterações - ' . date('d/m/Y');
        $mail->Body = $corpoConsolidado;
        
        if ($mail->send()) {
            echo "📋 Cópia consolidada enviada para alan@nutricionalbr.com\n";
        }
    } catch (Exception $e) {
        echo "❌ Falha ao enviar e-mail consolidado: " . $e->getMessage() . "\n";
    }
    
    // Garante o fechamento da conexão após o consolidado
    $mail->smtpClose();

    echo "🎉 PROCESSAMENTO CONCLUÍDO!\n";
    echo "📊 Resumo: $enviados enviados, $falhas falhas\n";
    
    // Finaliza a execução dependendo do contexto
    if ($isCli) exit(0);
    exit(0);

} catch (Exception $e) {
    $errorMsg = "❌ ERRO CRÍTICO: " . $e->getMessage();
    if ($isCli) {
        echo $errorMsg . "\n";
        exit(1);
    }
    $arrTagEstrutura['conteudoArea'] = $errorMsg;
}

if ($isCli) exit(1);
break;




    /////////////////*******************************////////////
    /////////////////*********DEFAULT***************/////////////
    /////////////////******************************/////////////
    ////////////////********************************////////////
case 'logout':
    // 1. Limpa todas as variáveis de sessão
    $_SESSION = array();

    // 2. Se desejar matar o cookie de sessão no navegador (mais seguro)
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    // 3. Destrói a sessão no servidor
    session_destroy();

    // 4. Redireciona para o login com um aviso de sucesso
    header("Location: " . DOMINIO . "/?logout_success=1");
    exit;
    break;
    default:
        if (strpos($_SERVER['HTTP_USER_AGENT'] ?? '', 'CronJob') !== false || php_sapi_name() === 'cli') {
            header('Content-Type: text/plain');
            die("ERRO: Ação '{$strAcao}' não reconhecida.");
        }
        $arrTagEstrutura['conteudoArea'] = "
            <script>
                Swal.fire({
                    title: 'Obrigado!',
                    text: 'Ação não reconhecida.',
                    icon: 'info',
                    confirmButtonText: 'Ok'
                }).then(() => {
                    window.location.href = 'https://nutricionalbr.com';
                });
            </script>
        ";
        break;
}

// INSERE CONTEÚDO NA ESTRUTURA
$objTemplateEstrutura = new templateParser('html/estrutura.html');
$objTemplateEstrutura->parseTemplate($arrTagEstrutura);
echo $objTemplateEstrutura->display();
?>