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
require_once __DIR__ . '/email_listas.php';

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
        // ================================================================
        // REDIRECIONA PARA O PORTAL NOVO
        // ================================================================
        // O dashboard legado (html/home.html) foi descontinuado.
        // Toda a visualização principal agora é feita no portal novo (/portal/).
        //
        // Preserva crons: se for requisição CLI ou via CronJob (User-Agent),
        // NÃO redireciona — deixa o fluxo seguir (para casos onde algum cron
        // chamar ?acao=home por engano, não quebra).
        // ================================================================
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $isCronJob = (strpos($userAgent, 'CronJob') !== false) || (php_sapi_name() === 'cli');
        
        if (!$isCronJob) {
            header('Location: /portal/', true, 302);
            exit;
        }
        
        // Se for cron, apenas exibe um texto simples (não carrega HTML legado)
        header('Content-Type: text/plain; charset=utf-8');
        echo "OK: acao=home executada via cron (sem dashboard legado)\n";
        exit;
        break;

   

    case 'catalogo':
        $arrTagEstrutura['tituloArea'] = 'Catálogo de Produtos | Nutricional';
        $objCat = new templateParser('html/catalogo.html');
        $objCat->parseTemplate(['url_base' => DOMINIO, 'pdf_visualiza' => 'catalogo_nutricional_2026_light.pdf', 'pdf_download' => 'catalogo_nutricional_2026.pdf']);
        $arrTagEstrutura['conteudoArea'] = $objCat->display();
        break;



// =============================================
// GATILHOS DE DISPARO (CHAMADOS PELO CPANEL)
// =============================================

case 'G3R4r3Pr3S3Nt4Nt3s':
    // ============================================================
    // BLOQUEIA ACESSO DIRETO - APENAS CRON OU CLI
    // ============================================================
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $isCronJob = (strpos($userAgent, 'CronJob') !== false) || (php_sapi_name() === 'cli');
    
   // if (!$isCronJob) {
      //  header('HTTP/1.0 403 Forbidden');
   //     die("Acesso negado. Este endpoint é exclusivo para execução via cron.");
  //  }
  
	
// ============================================================
    // CONTROLE DE EXECUÇÃO - APENAS 1 VEZ POR MÊS
    // ============================================================
  $lockFile = __DIR__ . '/erros_log/gatilho_representantes.lock';
    $mesAtual = date('Y-m');
    
    if (file_exists($lockFile)) {
        $lockContent = file_get_contents($lockFile);
        if (trim($lockContent) === $mesAtual) {
            echo "⏭️ Gatilho já executado neste mês ($mesAtual). Aguarde o próximo mês.\n";
            exit(0);
        }
    }
    
    // Cria o arquivo de lock
    file_put_contents($lockFile, $mesAtual);
    // ============================================================
    
    header('Content-Type: text/plain');
    echo "=== INICIANDO GATILHO REPRESENTANTES (MENSAL) ===\n";
    
    try {
        // Gera token para o processo real
        $codigoMascarado = Uteis::encrypt(TOKEN_EMAIL, CHAVE_SECRETA);
        $linkCompleto = DOMINIO . "/index.php?acao=3M411r3Pr3S3nt4Nt3s&t0k3n=" . urlencode($codigoMascarado) . "&key=" . urlencode(API_TOKEN);
        
        // Salva link para auditoria
        if (!Uteis::salvarLink($linkCompleto, 'representantes_mensal')) {
            throw new Exception("Erro ao salvar link dos representantes mensal.");
        }
        
        echo "Link Seguro Gerado: $linkCompleto\n";
        echo "Disparando requisição interna via Guzzle...\n";
        
        $response = $httpClient->get($linkCompleto);
        $body = $response->getBody()->getContents();
        
        echo "=== RESPOSTA DO PROCESSO ===\n$body\n";
        echo "✅ Gatilho concluído com sucesso!\n";
        
    } catch (Exception $e) {
        file_put_contents(
            __DIR__ . '/erros_log/cron_errors.log',
            date('[Y-m-d H:i:s]') . " ERRO G3R4r3Pr3S3Nt4Nt3s: " . $e->getMessage() . "\n",
            FILE_APPEND
        );
        echo "❌ ERRO CRÍTICO: " . $e->getMessage();
    }
    exit;

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

                    /* Base usada no cálculo da taxa de recuperação */
                    COALESCE((
                        SELECT
                            SUM(CASE WHEN vfe.ultimo_evento IS NULL AND vfe.dias_atraso >= 8 AND vfe.valorsaldo > 0 THEN 1 ELSE 0 END) +
                            SUM(CASE WHEN vfe.ultimo_evento IS NOT NULL AND vfe.valorsaldo > 0 THEN 1 ELSE 0 END) +
                            SUM(CASE WHEN vfe.ultimo_evento IS NOT NULL AND vfe.valorsaldo <= 0.01 THEN 1 ELSE 0 END)
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

    
    $emailsGestores = getEmails('gestores_destino');
    
    // Configura headers para texto puro se for chamado via Gatilho (Guzzle/CronJob)
    if (strpos($_SERVER['HTTP_USER_AGENT'] ?? '', 'CronJob') !== false) {
        header('Content-Type: text/plain');
    }

    $isCli = (php_sapi_name() === 'cli');
    
    // DEFINIÇÃO DOS PATHS PARA LOGS PADRONIZADOS
    $logDir = __DIR__ . '/erros_log';
    $logFileGestores = $logDir . '/cron_gestores.log';
    $logFileErrors = $logDir . '/cron_errors.log';
    
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
        
        // OBTÉM TOKEN
        $t0k3n = $isCli ? ($_SERVER['argv'][2] ?? '') : (filter_input(INPUT_GET, 't0k3n') ?? '');
        
        if (empty($t0k3n)) {
            $msg = $isCli ? "Token não fornecido para execução CLI" : "Acesso negado: Token não fornecido.";
            if ($isCli) die($msg);
            $arrTagEstrutura['conteudoArea'] = "<h2 style='color:red;'>{$msg}</h2>";
            break; 
        }
        
        // VALIDAÇÃO DO TOKEN
        $codigoDesmascarado = Uteis::decrypt(urldecode($t0k3n), CHAVE_SECRETA);
        if (!hash_equals((string)$codigoDesmascarado, (string)TOKEN_GESTORES)) {
            throw new Exception("Acesso Negado: Token inválido ou expirado.");
        }
        
        if ($isCli) {
            echo "✅ Token válido - Iniciando processamento...\n";
        } else {
            echo "✅ Token válido - Iniciando processamento...<br>";
        }

        // LÓGICA DE DATA CENTRALIZADA
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

        // ============================================================
        // CONFIGURA OS GESTORES DINAMICAMENTE A PARTIR DA LISTA
        // ============================================================
        $gestoresArray = [];
        $listaIds = [];
        
        foreach ($emailsGestores as $email) {
            // Busca o ID e nome do gestor pelo e-mail
            $sql = "SELECT u.idcliforemp, c.fantasia 
                    FROM usuario u
                    JOIN cliforemp c ON c.idcliforemp = u.idcliforemp
                    WHERE u.email = :email AND u.inativo = 'N'";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['email' => $email]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($row) {
                $id = $row['idcliforemp'];
                $nome = $row['fantasia'] ?: 'Gestor';
                
                $gestoresArray[$id] = [
                    'emails' => [$email],
                    'nome' => $nome
                ];
                $listaIds[] = $id;
            }
        }
        
        // Se não encontrou nenhum gestor, usa o fallback
        if (empty($gestoresArray)) {
            $gestoresArray = [
                11258 => [
                    'emails' => ['alan@nutricionalbr.com'],
                    'nome' => 'Administrador'
                ]
            ];
            $listaIds = [11258];
        }

        if ($isCli) echo "✅ Gestores carregados: " . count($gestoresArray) . " gestores\n";

        $listaIds = '(' . implode(',', array_keys($gestoresArray)) . ')';
        
        // CONSULTA PRINCIPAL - GESTORES
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
                    ROUND(SUM(varg.mais_60_dias) * 100.0 / NULLIF(SUM(varg.vencidos), 0), 2) AS percentual_mais_60,
                    avg(varg.prazo_medio) as prazo_medio
                FROM vw_analise_receber_geral varg 
                CROSS JOIN LATERAL pkg_ema.retorna_lista(REPLACE(REPLACE('" . $listaIds . "','(',''),')',''),',') AS user_id_param(out_id)
                WHERE (
                    (user_id_param.out_id = 11258 AND varg.idvendrepre IN (SELECT DISTINCT idrepresentante FROM vw_gestor_repre WHERE idfilial = 1 AND idgestor NOT IN (5297,11371)) and varg.idfilial = 1)
                    OR (user_id_param.out_id = 11371 AND varg.idsupervisor = 11371 and varg.idfilial = 6)
                    OR (user_id_param.out_id = 15520 AND varg.idsupervisor = 15520)
                    OR (user_id_param.out_id = 13878 AND varg.idsupervisor = 13878)
                    OR (user_id_param.out_id = 5297 AND varg.idvendrepre IN (SELECT idrepresentante FROM vw_gestor_repre WHERE inativo = 'N'))
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
            
            // BUSCA REPRESENTANTES
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
                            perc_mais_60_dias as \"Perc. Mais 60 Dias\",
                            prazo_medio as \"Prazo Médio\"
                        FROM vw_analise_receber_geral varg 
                        WHERE ({$filtroGestor['condicao']})
                        ORDER BY nomerepresentante";
            
            $stmtRep = $pdo->prepare($sqlRep);
            $stmtRep->execute();
            $dadosRep = $stmtRep->fetchAll(PDO::FETCH_ASSOC);

            $arquivoExcelRepresentantes = Uteis::gerarExcelRepresentantes($idGestor, $dadosRep);
            
            // BUSCA OS CLIENTES DETALHADOS (COM PRAZO_MEDIO)
            $sqlCli = "SELECT DISTINCT
                            varg.nomerepresentante AS nome_representante,
                            vfe.nomefantasia AS \"Nome Fantasia\",
                            vfe.documento,
                            TO_CHAR(vfe.vencimento, 'DD/MM/YYYY') as \"Vencimento\",
                            vfe.valorsaldo::float as \"Valor Saldo\",
                            vfe.valor as \"Valor Total\",
                            vfe.dataemissao as \"Data Emissão\",
                            vfe.dias_atraso as \"Dias em Atraso\",
                            vfe.ult_evento_dias as \"Dias do Último Evento\",
                            vfe.usuario as \"Usuário\",
                            COALESCE(
                                (select sum(dados.prazo)as prazoVENDA from (SELECT DISTINCT 
                                    cli.fantasia AS cliente,
                                    SUM(pedido.valortotalitens) * c.prazomedio / COALESCE(total.valortotalp, 1) AS prazo
                                    FROM pedido 
                                    JOIN condicaopagto c ON c.idcondicao = pedido.idcondicao 
                                    JOIN cliforemp cli ON cli.idcliforemp = pedido.idcliforemp 
                                    LEFT JOIN (
                                        SELECT 
                                            ped.idcliforemp,
                                            SUM(ped.valortotalitens) AS valortotalp
                                        FROM pedido ped 
                                        WHERE ped.status = 5 
                                          AND ped.idtransacao IN (1, 4, 17)
                                          AND ped.data < (CURRENT_DATE - INTERVAL '3 days')
                                        GROUP BY ped.idcliforemp
                                    ) total ON total.idcliforemp = cli.idcliforemp
                                    WHERE pedido.data < (CURRENT_DATE - INTERVAL '3 days')
                                      AND pedido.status = 5
                                      AND pedido.idtransacao IN (1, 4, 17)
                                      AND cli.idcliforemp = vfe.idcliforemp
                                    GROUP BY 
                                        cli.fantasia,
                                        c.prazomedio,
                                        total.valortotalp) dados), 0
                            ) as prazo_medio_cliente
                        FROM vw_financeiro_eventos vfe
                        JOIN vw_analise_receber_geral varg ON varg.idvendrepre = vfe.idrepresentante
                        WHERE {$filtroGestor['condicao']}
                          AND vfe.vencimento < (CURRENT_DATE - INTERVAL '3 days')
                          AND vfe.valorsaldo > 0
                        ORDER BY nome_representante, \"Nome Fantasia\" ASC";
            
            $stmtCli = $pdo->prepare($sqlCli);
            $stmtCli->execute();
            $dadosCli = $stmtCli->fetchAll(PDO::FETCH_ASSOC);
            $arquivoExcelClientes = Uteis::gerarExcelClientesDetalhado($dadosCli);

            try {
                $mail->clearAddresses(); 
                $mail->clearAttachments();
                $mail->clearCCs();
                $mail->clearBCCs();
                
                foreach ($emailsValidos as $email) {
                    $mail->addAddress($email);
                }
                
                // ADICIONA OS GESTORES DA LISTA EM CÓPIA (CC)
                // Como os gestores já são os destinatários, não precisamos de CC extra
                
                if (file_exists($arquivoExcelRepresentantes)) {
                    $mail->addAttachment($arquivoExcelRepresentantes, "resumo_representantes.xlsx");
                }
                if (file_exists($arquivoExcelClientes)) {
                    $mail->addAttachment($arquivoExcelClientes, "detalhado_clientes.xlsx");
                }

                $mail->Subject = 'Relatório de Inadimplência - ' . date('d/m/Y');
                $mail->Body = Uteis::construirEmailGestor($gestor);
                
                if ($mail->send()) {
                    $enviados++;
                    if ($isCli) echo "✅ Enviado: {$gestor['nomegestor']}\n";
                }
                
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
	
case '3M411r3Pr3S3nt4Nt3s':

    
    $emailsCC = getEmails('representantes_cc');
    $emailsConsolidado = getEmails('consolidado_representantes');
    
    // ============================================================
    // CONTROLE DE EXECUÇÃO - EVITA PROCESSAMENTO DUPLICADO
    // ============================================================
    $lockFile = __DIR__ . '/erros_log/processo_representantes.lock';
    $mesAtual = date('Y-m');
    
    if (file_exists($lockFile)) {
        $lockContent = file_get_contents($lockFile);
        if (trim($lockContent) === $mesAtual) {
            $msg = "⏭️ Processo já executado neste mês ($mesAtual).\n";
            if ($isCli) { echo $msg; exit(0); }
            echo $msg;
            break;
        }
    }
    file_put_contents($lockFile, $mesAtual);
    
    // Configura headers para texto puro se for chamado via Gatilho (Guzzle/CronJob)
    if (strpos($_SERVER['HTTP_USER_AGENT'] ?? '', 'CronJob') !== false) {
        header('Content-Type: text/plain');
    }

    $isCli = (php_sapi_name() === 'cli');
    
    // DEFINIÇÃO DOS PATHS PARA LOGS
    $logDir = __DIR__ . '/erros_log';
    $logFileRepresentantes = $logDir . '/cron_representantes_mensal.log';
    $logFileErrors = $logDir . '/cron_errors.log';
    $logFileDebug = $logDir . '/debug_ids.log';
    
    if (!file_exists($logDir)) {
        @mkdir($logDir, 0755, true);
    }

    // Garante que o diretório temp existe
    $tempDir = __DIR__ . '/temp';
    if (!is_dir($tempDir)) {
        @mkdir($tempDir, 0755, true);
    }

    try {
        if (!isset($pdo)) {
            throw new Exception("ERRO CRÍTICO: Conexão com banco (\$pdo) não disponível.");
        }
        
        if ($isCli) echo "✅ Banco detectado\n";
        
        // OBTÉM TOKEN
        $t0k3n = $isCli ? ($_SERVER['argv'][2] ?? '') : (filter_input(INPUT_GET, 't0k3n') ?? '');
        
        if (empty($t0k3n)) {
            $msg = $isCli ? "Token não fornecido" : "Acesso negado: Token não fornecido.";
            if ($isCli) die($msg);
            break;
        }
        
        // VALIDAÇÃO DO TOKEN (Usando TOKEN_EMAIL que já existe)
        $codigoDesmascarado = Uteis::decrypt(urldecode($t0k3n), CHAVE_SECRETA);
        if (!hash_equals((string)$codigoDesmascarado, (string)TOKEN_EMAIL)) {
            throw new Exception("Acesso Negado: Token inválido.");
        }
        
        if ($isCli) echo "✅ Token válido - Iniciando processamento para Representantes\n";

        // ============================================================
        // 1. BUSCAR TODOS OS REPRESENTANTES ATIVOS (COM EMAIL)
        // ============================================================
        $sqlReps = "SELECT DISTINCT 
                        c.idcliforemp,
                        c.fantasia as nome,
                        c.email,
                        COALESCE(c.email, '') as email_rep
                    FROM cliforemp c
                    JOIN fornecedor ON (fornecedor.idcliforemp = c.idcliforemp)
                    WHERE fornecedor.representante = 'S'
                      AND c.inativo = 'N'
                      AND c.email IS NOT NULL 
                      AND c.email != ''
                      AND c.idcliforemp NOT IN (10119)
                    ORDER BY c.fantasia";
        
        $stmtReps = $pdo->query($sqlReps);
        $representantes = $stmtReps->fetchAll(PDO::FETCH_ASSOC);

        if (empty($representantes)) {
            $msg = "Nenhum representante ativo com e-mail encontrado.";
            if ($isCli) { echo $msg . "\n"; exit(0); }
            break;
        }

        echo "✅ " . count($representantes) . " representantes encontrados\n";

        // ============================================================
        // 2. CONFIGURAÇÃO DO PHPMailer
        // ============================================================
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = EMAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = EMAIL_USERNAME;
        $mail->Password = EMAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = EMAIL_PORT;
        $mail->setFrom(EMAIL_USERNAME, 'Nutricional Distribuidora - Relatório Mensal');
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
        $mail->SMTPKeepAlive = true;

        // ============================================================
        // 3. LOOP PARA CADA REPRESENTANTE - VERSÃO CORRIGIDA
        // ============================================================
        $enviados = 0;
        $falhas = 0;
        $logErros = [];
        $resumoConsolidado = [];

        foreach ($representantes as $rep) {
            // ============================================================
            // CORREÇÃO 1: FORÇA CAST PARA INTEGER
            // ============================================================
            $idRep = (int)$rep['idcliforemp'];
            $nomeRep = $rep['nome'];
            
            // ============================================================
            // CORREÇÃO 2: LOG DE DEBUG
            // ============================================================
            file_put_contents(
                $logFileDebug,
                date('Y-m-d H:i:s') . " - Processando ID: $idRep - Nome: $nomeRep\n",
                FILE_APPEND
            );
            
            echo "📌 Processando: ID $idRep - $nomeRep\n";
            
            // Higienização do e-mail
            $emailBruto = strtolower($rep['email_rep']);
            $emailBruto = str_replace(',', ';', $emailBruto);
            $arrayEmails = explode(';', $emailBruto);
            $emailRepLimpo = trim($arrayEmails[0]);
            
            if (!filter_var($emailRepLimpo, FILTER_VALIDATE_EMAIL)) {
                $logErros[] = "E-mail inválido para {$nomeRep}: '{$emailRepLimpo}'";
                echo "❌ {$logErros[count($logErros)-1]}\n";
                continue;
            }

            // ============================================================
            // 3a. CONSULTA DOS DADOS DO REPRESENTANTE
            // ============================================================
            $sqlDadosRep = "SELECT 
                                varg.idvendrepre,
                                varg.nomerepresentante,
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
                                ROUND(SUM(varg.mais_60_dias) * 100.0 / NULLIF(SUM(varg.vencidos), 0), 2) AS percentual_mais_60,
                                AVG(varg.prazo_medio) as prazo_medio
                            FROM vw_analise_receber_geral varg
                            WHERE varg.idvendrepre = :idRep
                            GROUP BY varg.idvendrepre, varg.nomerepresentante";

            $stmtDados = $pdo->prepare($sqlDadosRep);
            $stmtDados->execute(['idRep' => $idRep]);
            $dadosRep = $stmtDados->fetch(PDO::FETCH_ASSOC);

            if (empty($dadosRep) || $dadosRep['vencidos'] == 0) {
                echo "⏭️ Representante {$nomeRep} sem inadimplência - Pulando\n";
                continue;
            }

            // ============================================================
            // 3b. CONSULTA DOS CLIENTES DETALHADOS
            // ============================================================
            $sqlClientes = "SELECT DISTINCT
                                varg.nomerepresentante AS nome_representante,
                                vfe.nomefantasia AS \"Nome Fantasia\",
                                vfe.documento,
                                TO_CHAR(vfe.vencimento, 'DD/MM/YYYY') AS \"Vencimento\",
                                vfe.valorsaldo::float AS \"Valor Saldo\",
                                vfe.valor AS \"Valor Total\",
                                vfe.dataemissao AS \"Data Emissão\",
                                vfe.dias_atraso AS \"Dias em Atraso\",
                                vfe.ult_evento_dias AS \"Dias do Último Evento\",
                                vfe.usuario AS \"Usuário\",
                                COALESCE(
                                    (SELECT CAST(COALESCE(SUM(dados.prazo), 0.00) AS NUMERIC(10,2)) AS prazoVENDA FROM (
                                        SELECT DISTINCT 
                                            cli.fantasia AS cliente,
                                            SUM(pedido.valortotalitens) * c.prazomedio / COALESCE(total.valortotalp, 1) AS prazo
                                        FROM pedido 
                                        JOIN condicaopagto c ON c.idcondicao = pedido.idcondicao 
                                        JOIN cliforemp cli ON cli.idcliforemp = pedido.idcliforemp 
                                        LEFT JOIN (
                                            SELECT 
                                                ped.idcliforemp,
                                                SUM(ped.valortotalitens) AS valortotalp
                                            FROM pedido ped 
                                            WHERE ped.status = 5 
                                              AND ped.idtransacao IN (1, 4, 17)
                                              AND ped.data < (CURRENT_DATE - INTERVAL '3 days')
                                            GROUP BY ped.idcliforemp
                                        ) total ON total.idcliforemp = cli.idcliforemp
                                        WHERE pedido.data < (CURRENT_DATE - INTERVAL '3 days')
                                          AND pedido.status = 5
                                          AND pedido.idtransacao IN (1, 4, 17)
                                          AND cli.idcliforemp = vfe.idcliforemp
                                        GROUP BY 
                                            cli.fantasia,
                                            c.prazomedio,
                                            total.valortotalp
                                    ) dados
                                ), 0) AS \"Prazo medio cliente\"
                            FROM vw_financeiro_eventos vfe
                            JOIN vw_analise_receber_geral varg ON varg.idvendrepre = vfe.idrepresentante
                            WHERE varg.idvendrepre = :idRep
                              AND vfe.vencimento < (CURRENT_DATE - INTERVAL '3 days')
                              AND vfe.valorsaldo > 0
                            ORDER BY varg.nomerepresentante, vfe.nomefantasia ASC";

            $stmtCli = $pdo->prepare($sqlClientes);
            $stmtCli->execute(['idRep' => $idRep]);
            $dadosClientes = $stmtCli->fetchAll(PDO::FETCH_ASSOC);

            // ============================================================
            // 3c. GERAR EXCEL DO REPRESENTANTE
            // ============================================================
            try {
                echo "📊 Gerando Excel para ID $idRep...\n";
                $arquivoExcelRep = Uteis::gerarExcelRepresentanteMensal($idRep, $dadosRep, $dadosClientes);
                
                if (!$arquivoExcelRep || !file_exists($arquivoExcelRep)) {
                    throw new Exception("Arquivo Excel não foi gerado para ID $idRep");
                }
                
                echo "✅ Excel gerado: " . basename($arquivoExcelRep) . "\n";
                
            } catch (Exception $e) {
                $falhas++;
                $logErros[] = "Erro ao gerar Excel para {$nomeRep} (ID: $idRep): " . $e->getMessage();
                echo "❌ {$logErros[count($logErros)-1]}\n";
                continue;
            }

            // ============================================================
            // 3d. CONSTRUIR E-MAIL E ENVIAR - VERSÃO CORRIGIDA
            // ============================================================
            try {
                // ============================================================
                // CORREÇÃO CRÍTICA: LIMPEZA COMPLETA DO PHPMailer
                // ============================================================
                $mail->clearAllRecipients();
                $mail->clearCCs();
                $mail->clearBCCs();
                $mail->clearAttachments();  // <--- ESSA LINHA É CRUCIAL!
                $mail->clearReplyTos();
                $mail->clearCustomHeaders();
                
                // Força a reinicialização do corpo do e-mail
                $mail->Body = '';
                $mail->AltBody = '';
                
                $mail->addAddress($emailRepLimpo, $nomeRep);
                
                // ADICIONA AS CÓPIAS (CC)
                foreach ($emailsCC as $emailCC) {
                    if (filter_var($emailCC, FILTER_VALIDATE_EMAIL)) {
                        $mail->addCC($emailCC);
                    }
                }
                
                // ============================================================
                // CORREÇÃO: VERIFICA O ARQUIVO CORRETO
                // ============================================================
                // Força a verificação do arquivo correto baseado no ID
                $arquivoEsperado = __DIR__ . "/temp/relatorio_representante_{$idRep}_" . date('Y-m-d') . ".xlsx";
                
                if (file_exists($arquivoEsperado)) {
                    $arquivoExcelRep = $arquivoEsperado;
                }
                
                if (file_exists($arquivoExcelRep)) {
                    $nomeAnexo = "relatorio_representante_{$idRep}_" . date('Y-m-d') . ".xlsx";
                    $mail->addAttachment($arquivoExcelRep, $nomeAnexo);
                    echo "📎 Anexo adicionado: {$nomeAnexo}\n";
                } else {
                    throw new Exception("Arquivo não encontrado: " . basename($arquivoExcelRep));
                }

                $mail->Subject = '📊 Relatório Mensal de Inadimplência - ' . date('m/Y') . ' - ' . $nomeRep;
                $mail->Body = Uteis::construirEmailRepresentanteMensal($dadosRep, $dadosClientes);
                
                if ($mail->send()) {
                    $enviados++;
                    echo "✅ E-mail enviado para {$nomeRep} ({$emailRepLimpo})\n";
                    
                    $resumoConsolidado[] = [
                        'id' => $idRep,
                        'nome' => $nomeRep,
                        'email' => $emailRepLimpo,
                        'vencidos' => $dadosRep['vencidos'],
                        'percentual' => $dadosRep['percentual_geral'],
                        'clientes' => $dadosRep['total_clientes_vencidos'],
                        'titulos' => $dadosRep['total_titulos_vencidos'],
                        'prazo_medio' => $dadosRep['prazo_medio']
                    ];
                }
                
                // LIMPA O ARQUIVO TEMPORÁRIO
                if (file_exists($arquivoExcelRep)) {
                    @unlink($arquivoExcelRep);
                    echo "🗑️ Arquivo temporário removido: " . basename($arquivoExcelRep) . "\n";
                }

            } catch (Exception $e) {
                $falhas++;
                $logErros[] = "Erro ao enviar para {$nomeRep} (ID: $idRep): " . $e->getMessage();
                echo "❌ {$logErros[count($logErros)-1]}\n";
                if (file_exists($arquivoExcelRep)) @unlink($arquivoExcelRep);
            }

            sleep(1);
        }

        $mail->smtpClose();

        // ============================================================
        // 4. RELATÓRIO CONSOLIDADO PARA A GERÊNCIA
        // ============================================================
        if (!empty($resumoConsolidado)) {
            $corpoConsolidado = Uteis::construirEmailConsolidado($resumoConsolidado, $enviados, $falhas, $logErros);
            
            try {
                $mailConsolidado = new PHPMailer(true);
                $mailConsolidado->isSMTP();
                $mailConsolidado->Host = EMAIL_HOST;
                $mailConsolidado->SMTPAuth = true;
                $mailConsolidado->Username = EMAIL_USERNAME;
                $mailConsolidado->Password = EMAIL_PASSWORD;
                $mailConsolidado->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                $mailConsolidado->Port = EMAIL_PORT;
                $mailConsolidado->setFrom(EMAIL_USERNAME, 'Nutricional Distribuidora - Relatório Consolidado');
                $mailConsolidado->isHTML(true);
                $mailConsolidado->CharSet = 'UTF-8';
                $mailConsolidado->SMTPOptions = [
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    ]
                ];
                
                // ADICIONA OS DESTINATÁRIOS DA LISTA CONFIGURADA
                foreach ($emailsConsolidado as $emailDestino) {
                    if (filter_var($emailDestino, FILTER_VALIDATE_EMAIL)) {
                        $mailConsolidado->addAddress($emailDestino);
                    }
                }
                
                $mailConsolidado->Subject = '📊 RELATÓRIO CONSOLIDADO - Representantes - ' . date('m/Y');
                $mailConsolidado->Body = $corpoConsolidado;
                $mailConsolidado->send();
                echo "📋 Cópia consolidada enviada para a gerência\n";
            } catch (Exception $e) {
                echo "❌ Falha ao enviar consolidado: " . $e->getMessage() . "\n";
                file_put_contents($logFileErrors, date('[Y-m-d H:i:s]') . " Erro consolidado: " . $e->getMessage() . "\n", FILE_APPEND);
            }
        } else {
            echo "⚠️ Nenhum e-mail enviado para gerar consolidado.\n";
        }

        // LOG FINAL
        $logFinal = "[" . date('Y-m-d H:i:s') . "] FINALIZADO: Enviados: $enviados, Falhas: $falhas\n";
        file_put_contents($logFileRepresentantes, $logFinal, FILE_APPEND);
        
        // LOG DE RESULTADO DETALHADO
        file_put_contents(
            $logFileDebug,
            date('Y-m-d H:i:s') . " - RESULTADO FINAL: Enviados: $enviados, Falhas: $falhas\n\n",
            FILE_APPEND
        );

        echo "\n🎉 PROCESSAMENTO CONCLUÍDO!\n";
        echo "📊 Resumo: $enviados enviados, $falhas falhas\n";

        if ($isCli) exit(0);
        break;

    } catch (Exception $e) {
        $errorMsg = "❌ ERRO CRÍTICO: " . $e->getMessage();
        file_put_contents($logFileErrors, date('[Y-m-d H:i:s]') . " ERRO CRÍTICO: " . $e->getMessage() . "\n", FILE_APPEND);
        if ($isCli) {
            echo $errorMsg . "\n";
            exit(1);
        }
        $arrTagEstrutura['conteudoArea'] = $errorMsg;
    }
    break;
	
case 'enviar_pedidos_aguardando':
    // ============================================================
    // CONFIGURAÇÃO PARA EXECUÇÃO VIA CRON
    // ============================================================
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $isCronJob = (strpos($userAgent, 'CronJob') !== false) || (php_sapi_name() === 'cli');
    
    if (!$isCronJob) {
        header('HTTP/1.0 403 Forbidden');
        die("Acesso negado. Este endpoint é exclusivo para execução via cron.");
    }
    
    // ============================================================
    // CARREGA AS LISTAS DE E-MAILS
    // ============================================================
    require_once __DIR__ . '/email_listas.php';
    $emailsCC = getEmails('pedidos_aguardando_cc');
    $emailsConsolidado = getEmails('pedidos_aguardando_consolidado');
    
    header('Content-Type: text/plain');
    echo "=== INICIANDO ENVIO DE PEDIDOS AGUARDANDO APROVAÇÃO ===\n";
    echo "Data: " . date('d/m/Y H:i:s') . "\n";
    
    try {
        if (!isset($pdo)) {
            throw new Exception("ERRO CRÍTICO: Conexão com banco (\$pdo) não disponível.");
        }
        
        // ============================================================
        // 1. BUSCAR TODOS OS REPRESENTANTES COM PEDIDOS AGUARDANDO
        // ============================================================
        $sqlReps = "SELECT DISTINCT 
                        vend.idcliforemp,
                        vend.fantasia as nome,
                        vend.email
                    FROM pedido p
                    JOIN cliforemp vend ON vend.idcliforemp = p.idvendrepre
                    join fornecedor on fornecedor.idcliforemp = vend.idcliforemp
                    WHERE p.situacao = 2 
                    AND p.status = 1
                      AND fornecedor.representante = 'S'
                      AND vend.inativo = 'N'
                      AND vend.email IS NOT NULL 
                      AND vend.email != ''
                    ORDER BY vend.fantasia";
        
        $stmtReps = $pdo->query($sqlReps);
        $representantes = $stmtReps->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($representantes)) {
            echo "⏭️ Nenhum representante com pedidos aguardando aprovação.\n";
            
            // ============================================================
            // ENVIA E-MAIL DE AVISO - SEM PEDIDOS
            // ============================================================
            $corpoAviso = Uteis::construirAvisoSemPedidos($emailsConsolidado);
            
            try {
                $mailAviso = new PHPMailer(true);
                $mailAviso->isSMTP();
                $mailAviso->Host = EMAIL_HOST;
                $mailAviso->SMTPAuth = true;
                $mailAviso->Username = EMAIL_USERNAME;
                $mailAviso->Password = EMAIL_PASSWORD;
                $mailAviso->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                $mailAviso->Port = EMAIL_PORT;
                $mailAviso->setFrom(EMAIL_USERNAME, 'Nutricional Distribuidora - Pedidos');
                $mailAviso->isHTML(true);
                $mailAviso->CharSet = 'UTF-8';
                $mailAviso->SMTPOptions = [
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    ]
                ];
                
                foreach ($emailsConsolidado as $emailDestino) {
                    if (filter_var($emailDestino, FILTER_VALIDATE_EMAIL)) {
                        $mailAviso->addAddress($emailDestino);
                    }
                }
                
                $mailAviso->Subject = '📋 AVISO - Nenhum pedido aguardando aprovação - ' . date('d/m/Y');
                $mailAviso->Body = $corpoAviso;
                $mailAviso->send();
                echo "📋 Aviso enviado para a gerência: Nenhum pedido pendente\n";
            } catch (Exception $e) {
                echo "❌ Falha ao enviar aviso: " . $e->getMessage() . "\n";
            }
            
            exit(0);
        }
        
        echo "✅ " . count($representantes) . " representantes com pedidos pendentes\n";
        
        // ============================================================
        // 2. CONFIGURAÇÃO DO PHPMailer
        // ============================================================
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = EMAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = EMAIL_USERNAME;
        $mail->Password = EMAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = EMAIL_PORT;
        $mail->setFrom(EMAIL_USERNAME, 'Nutricional Distribuidora - Pedidos');
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
        $mail->SMTPKeepAlive = true;
        
        // ============================================================
        // 3. LOOP PARA CADA REPRESENTANTE
        // ============================================================
        $enviados = 0;
        $falhas = 0;
        $logErros = [];
        $resumoConsolidado = [];
        
        foreach ($representantes as $rep) {
            $idRep = (int)$rep['idcliforemp'];
            $nomeRep = $rep['nome'];
            $emailRep = $rep['email'];
            
            // Higienização do e-mail
            if (empty($emailRep)) {
                continue;
            }
            
            $emailBruto = strtolower($emailRep);
            $emailBruto = str_replace(',', ';', $emailBruto);
            $arrayEmails = explode(';', $emailBruto);
            $emailRepLimpo = trim($arrayEmails[0]);
            
            if (!filter_var($emailRepLimpo, FILTER_VALIDATE_EMAIL)) {
                $logErros[] = "E-mail inválido para {$nomeRep}: '{$emailRepLimpo}'";
                echo "❌ E-mail inválido para {$nomeRep}\n";
                continue;
            }
            
            // ============================================================
            // 3a. CONSULTAR PEDIDOS COM SOMA DAS QUANTIDADES
            // ============================================================
            $sqlPedidos = "SELECT 
                                p.idpedido,
                                p.data,
                                p.valortotalitens,
                                c.descricao as condicaopagto,
                                mp.descricao as metodopagto,
                                cli.fantasia as cliente,
                                cli.idcliforemp as idcliente,
                                COALESCE(SUM(pi.qt), 0) as total_quantidade
                            FROM pedido p
                            JOIN cliforemp cli ON cli.idcliforemp = p.idcliforemp
                            JOIN cliforemp vend ON vend.idcliforemp = p.idvendrepre
                            JOIN pedido_item pi ON pi.idpedido = p.idpedido
                            JOIN condicaopagto c ON c.idcondicao = p.idcondicao
                            JOIN metodopagto mp ON mp.idmetodo = p.idmetodo
                            WHERE p.situacao = 2 
                            AND p.status = 1
                              AND p.idvendrepre = :idRep
                            GROUP BY p.idpedido, p.data, p.valortotalitens, c.descricao, mp.descricao, cli.fantasia, cli.idcliforemp
                            ORDER BY cli.fantasia, p.idpedido";
            
            $stmtPedidos = $pdo->prepare($sqlPedidos);
            $stmtPedidos->execute(['idRep' => $idRep]);
            $pedidos = $stmtPedidos->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($pedidos)) {
                echo "⏭️ {$nomeRep} - Nenhum pedido pendente\n";
                continue;
            }
            
            // ============================================================
            // 3b. GERAR CORPO DO E-MAIL RESUMIDO
            // ============================================================
            $corpoEmail = Uteis::construirEmailPedidosAguardandoResumido($pedidos, $nomeRep);
            
            // ============================================================
            // 3c. ENVIAR E-MAIL
            // ============================================================
            try {
                $mail->clearAllRecipients();
                $mail->clearCCs();
                $mail->clearBCCs();
                $mail->clearAttachments();
                $mail->addAddress($emailRepLimpo, $nomeRep);
                
                // Adiciona cópias (CC) da lista configurada
                foreach ($emailsCC as $emailCC) {
                    if (filter_var($emailCC, FILTER_VALIDATE_EMAIL)) {
                        $mail->addCC($emailCC);
                    }
                }
                
                $mail->Subject = '📋 Pedidos Aguardando Aprovação - ' . date('d/m/Y') . ' - ' . $nomeRep;
                $mail->Body = $corpoEmail;
                
                if ($mail->send()) {
                    $enviados++;
                    echo "✅ E-mail enviado para {$nomeRep} ({$emailRepLimpo})\n";
                    
                    $resumoConsolidado[] = [
                        'id' => $idRep,
                        'nome' => $nomeRep,
                        'email' => $emailRepLimpo,
                        'total_pedidos' => count(array_unique(array_column($pedidos, 'idpedido'))),
                        'total_clientes' => count(array_unique(array_column($pedidos, 'idcliente'))),
                        'total_quantidade' => array_sum(array_column($pedidos, 'total_quantidade')),
                        'valor_total' => array_sum(array_column($pedidos, 'valortotalitens'))
                    ];
                }
                
            } catch (Exception $e) {
                $falhas++;
                $logErros[] = "Erro ao enviar para {$nomeRep}: " . $e->getMessage();
                echo "❌ Erro ao enviar para {$nomeRep}\n";
            }
            
            sleep(1);
        }
        
        $mail->smtpClose();
        
        // ============================================================
        // 4. RELATÓRIO CONSOLIDADO
        // ============================================================
        if (!empty($resumoConsolidado)) {
            $corpoConsolidado = Uteis::construirConsolidadoPedidosResumido($resumoConsolidado, $enviados, $falhas, $logErros);
            
            try {
                $mailConsolidado = new PHPMailer(true);
                $mailConsolidado->isSMTP();
                $mailConsolidado->Host = EMAIL_HOST;
                $mailConsolidado->SMTPAuth = true;
                $mailConsolidado->Username = EMAIL_USERNAME;
                $mailConsolidado->Password = EMAIL_PASSWORD;
                $mailConsolidado->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                $mailConsolidado->Port = EMAIL_PORT;
                $mailConsolidado->setFrom(EMAIL_USERNAME, 'Nutricional Distribuidora - Pedidos');
                $mailConsolidado->isHTML(true);
                $mailConsolidado->CharSet = 'UTF-8';
                $mailConsolidado->SMTPOptions = [
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    ]
                ];
                
                foreach ($emailsConsolidado as $emailDestino) {
                    if (filter_var($emailDestino, FILTER_VALIDATE_EMAIL)) {
                        $mailConsolidado->addAddress($emailDestino);
                    }
                }
                
                $mailConsolidado->Subject = '📊 CONSOLIDADO - Pedidos Aguardando Aprovação - ' . date('d/m/Y');
                $mailConsolidado->Body = $corpoConsolidado;
                $mailConsolidado->send();
                echo "📋 Cópia consolidada enviada para a gerência\n";
            } catch (Exception $e) {
                echo "❌ Falha ao enviar consolidado: " . $e->getMessage() . "\n";
            }
        }
        
        echo "\n🎉 PROCESSAMENTO CONCLUÍDO!\n";
        echo "📊 Resumo: $enviados enviados, $falhas falhas\n";
        
        exit(0);
        
    } catch (Exception $e) {
        echo "❌ ERRO CRÍTICO: " . $e->getMessage() . "\n";
        exit(1);
    }
    break;
/* ==========================================================================
   CASE: RELATÓRIO DE CLIENTES ISENTOS DE INSCRIÇÃO ESTADUAL
   Execução exclusiva via CRON/CLI
   1 e-mail GERAL (gestores) + N e-mails POR FILIAL
   ========================================================================== */
case 'G3R4r3l4t0r10Cl13nt3s1s3nt0s':
    // ============================================================
    // CONTROLE DE EXECUÇÃO - APENAS VIA CRON/CLI
    // ============================================================
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $isCronJob = (strpos($userAgent, 'CronJob') !== false) || (php_sapi_name() === 'cli');

    if (!$isCronJob) {
        header('HTTP/1.0 403 Forbidden');
        die("Acesso negado. Este endpoint é exclusivo para execução via cron.");
    }

    // Ajustes de ambiente para relatório pesado
    ini_set('memory_limit', '512M');
    ini_set('max_execution_time', 300);

    header('Content-Type: text/plain; charset=utf-8');
    echo "=== INICIANDO RELATÓRIO DE CLIENTES ISENTOS ===\n";
    echo "Data: " . date('d/m/Y H:i:s') . "\n\n";

    try {
        if (!isset($pdo)) {
            throw new Exception("ERRO CRÍTICO: Conexão com banco (\$pdo) não disponível.");
        }

        // ============================================================
        // 1. CARREGAR CONFIGURAÇÃO
        // ============================================================
        require_once __DIR__ . '/email_listas.php';
        $config = getEmails('clientes_isento');

        if (empty($config) || !is_array($config)) {
            throw new Exception("Configuração 'clientes_isento' inválida em email_listas.php.");
        }

        $destinosGeral   = $config['geral']   ?? [];
        $destinosFiliais = $config['filiais'] ?? [];

        echo "📧 Configuração carregada:\n";
        echo "   • Geral: " . count($destinosGeral) . " destinatário(s)\n";
        echo "   • Filiais: " . count($destinosFiliais) . " filial(is)\n\n";

        $enviados = 0;
        $falhas   = 0;

        // ============================================================
        // 2. FUNÇÃO AUXILIAR - Envia um e-mail (geral ou por filial)
        // ============================================================
        $enviarRelatorio = function($clientes, $destinos, $tituloContexto, $idFilial = null) use (
            &$enviados, &$falhas
        ) {
            if (empty($clientes)) {
                echo "⏭️ Sem clientes para: $tituloContexto\n\n";
                return;
            }

            if (empty($destinos)) {
                echo "⚠️ Sem destinatários para: $tituloContexto\n\n";
                return;
            }

            echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            echo "📨 $tituloContexto\n";
            echo "📧 Destinatários: " . implode(', ', $destinos) . "\n";
            echo "👥 Total de clientes: " . count($clientes) . "\n";
            echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

            try {
                // 2a. GERAR EXCEL
                echo "📊 Gerando planilha Excel...\n";
                $arquivoExcel = Uteis::gerarExcelClientesIsentos($clientes);

                if (!$arquivoExcel || !file_exists($arquivoExcel)) {
                    throw new Exception("Falha ao gerar Excel.");
                }

                $tamanhoKB = round(filesize($arquivoExcel) / 1024, 2);
                echo "✅ Excel gerado ({$tamanhoKB} KB)\n";

                // 2b. MONTAR E-MAIL
                echo "📨 Enviando e-mail...\n";

                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->Host       = EMAIL_HOST;
                $mail->SMTPAuth   = true;
                $mail->Username   = EMAIL_USERNAME;
                $mail->Password   = EMAIL_PASSWORD;
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                $mail->Port       = EMAIL_PORT;
                $mail->CharSet    = 'UTF-8';
                $mail->setFrom(EMAIL_USERNAME, 'Nutricional Distribuidora - Relatório');
                $mail->isHTML(true);
                $mail->SMTPOptions = [
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    ]
                ];

                foreach ($destinos as $email) {
                    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $mail->addAddress($email);
                    } else {
                        echo "⚠️ E-mail inválido ignorado: $email\n";
                    }
                }

                // Assunto e anexo por contexto
                if ($idFilial) {
                    $mail->Subject = '📋 Relatório de Clientes Isentos - Filial ' . $idFilial . ' - ' . date('d/m/Y');
                    $nomeAnexo = "clientes_isentos_filial{$idFilial}_" . date('Y-m-d') . ".xlsx";
                } else {
                    $mail->Subject = '📋 Relatório GERAL de Clientes Isentos - ' . date('d/m/Y');
                    $nomeAnexo = "clientes_isentos_GERAL_" . date('Y-m-d') . ".xlsx";
                }

                $mail->Body = Uteis::construirEmailClientesIsentos($clientes, $idFilial);

                if (!file_exists($arquivoExcel)) {
                    $nomeAnexo = str_replace('.xlsx', '.csv', $nomeAnexo);
                }
                $mail->addAttachment($arquivoExcel, $nomeAnexo);
                echo "📎 Anexo: $nomeAnexo\n";

                if ($mail->send()) {
                    echo "✅ E-mail enviado com sucesso!\n\n";
                    $enviados++;
                } else {
                    throw new Exception("Falha no envio: " . $mail->ErrorInfo);
                }

                @unlink($arquivoExcel);

            } catch (Exception $e) {
                $falhas++;
                echo "❌ ERRO: " . $e->getMessage() . "\n\n";

                $logDir = __DIR__ . '/erros_log';
                if (!file_exists($logDir)) @mkdir($logDir, 0755, true);
                file_put_contents(
                    $logDir . '/cron_clientes_isentos.log',
                    date('[Y-m-d H:i:s]') . " [$tituloContexto] ERRO: " . $e->getMessage() . "\n",
                    FILE_APPEND
                );
            }

            sleep(2); // Pausa entre envios
        };

        // ============================================================
        // 3. ENVIAR E-MAIL GERAL (se configurado)
        // ============================================================
        if (!empty($destinosGeral)) {
            $sqlGeral = "SELECT DISTINCT
                            cli.idcliforemp AS cd_cliente,
                            cli.fantasia,
                            cli.razao,
                            cli.cnpj,
                            cli.cpf,
                            cli.ie,
                            cli.idvendedor,
                            (SELECT vend.fantasia FROM cliforemp vend WHERE vend.idcliforemp = cli.idvendedor) AS representante,
                            (SELECT descricao FROM cidade WHERE cidade.idcidade = cli.idcidade) AS cidade,
                            cli.uf,
                            cli.email,
                            cli.fone
                        FROM cliforemp cli 
                        WHERE (cli.ie LIKE '%ISENTO%' OR cli.ie = '' OR cli.ie IS NULL)
                          AND cli.tipocliforemp = 0 
                          AND cli.idfilial IN (1, 6)
                          AND cli.inativo = 'N' 
                        ORDER BY cli.uf ASC, cli.fantasia ASC";

            echo "🔍 Buscando TODOS os clientes (visão geral)...\n";
            $stmt = $pdo->prepare($sqlGeral);
            $stmt->execute();
            $clientesGeral = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo "✅ " . count($clientesGeral) . " clientes encontrados (geral)\n\n";

            $enviarRelatorio(
                $clientesGeral,
                $destinosGeral,
                "RELATÓRIO GERAL (Gestores)",
                null
            );
        } else {
            echo "⏭️ Nenhum destinatário na chave 'geral' - pulando envio geral\n\n";
        }

        // ============================================================
        // 4. ENVIAR E-MAILS POR FILIAL (se configurado)
        // ============================================================
        if (!empty($destinosFiliais) && is_array($destinosFiliais)) {
            foreach ($destinosFiliais as $idFilial => $destinos) {
                $idFilial = (int)$idFilial;

                if (empty($destinos)) {
                    echo "⚠️ Filial $idFilial sem destinatários - pulando\n\n";
                    continue;
                }

                $sqlFilial = "SELECT DISTINCT
                                cli.idcliforemp AS cd_cliente,
                                cli.fantasia,
                                cli.razao,
                                cli.cnpj,
                                cli.cpf,
                                cli.ie,
                                cli.idvendedor,
                                (SELECT vend.fantasia FROM cliforemp vend WHERE vend.idcliforemp = cli.idvendedor) AS representante,
                                (SELECT descricao FROM cidade WHERE cidade.idcidade = cli.idcidade) AS cidade,
                                cli.uf,
                                cli.email,
                                cli.fone
                            FROM cliforemp cli 
                            WHERE (cli.ie LIKE '%ISENTO%' OR cli.ie = '' OR cli.ie IS NULL)
                              AND cli.tipocliforemp = 0 
                              AND cli.idfilial = :idfilial
                              AND cli.inativo = 'N' 
                            ORDER BY cli.uf ASC, cli.fantasia ASC";

                echo "🔍 Buscando clientes da filial $idFilial...\n";
                $stmt = $pdo->prepare($sqlFilial);
                $stmt->execute(['idfilial' => $idFilial]);
                $clientesFilial = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo "✅ " . count($clientesFilial) . " clientes encontrados (filial $idFilial)\n\n";

                $enviarRelatorio(
                    $clientesFilial,
                    $destinos,
                    "RELATÓRIO FILIAL $idFilial",
                    $idFilial
                );
            }
        } else {
            echo "⏭️ Nenhuma filial configurada - pulando envios por filial\n\n";
        }

        // ============================================================
        // 5. RESUMO FINAL
        // ============================================================
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "🎉 PROCESSAMENTO CONCLUÍDO\n";
        echo "📧 E-mails enviados: $enviados\n";
        echo "❌ Falhas: $falhas\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

        exit(0);

    } catch (Exception $e) {
        $errorMsg = "❌ ERRO CRÍTICO: " . $e->getMessage();
        echo $errorMsg . "\n";

        $logDir = __DIR__ . '/erros_log';
        if (!file_exists($logDir)) @mkdir($logDir, 0755, true);
        file_put_contents(
            $logDir . '/cron_clientes_isentos.log',
            date('[Y-m-d H:i:s]') . " ERRO CRÍTICO: " . $e->getMessage() . "\n",
            FILE_APPEND
        );

        exit(1);
    }
    break;


case '3M411':

    
    $emailsCC = getEmails('alteracoes_cc');
    $emailsConsolidado = getEmails('alteracoes_consolidado');
    
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
                continue;
            }

            $corpoEmail = Uteis::construirCorpoEmail($rep['pedidos'], $rep['nome']);
            $assunto = "Relatório de Alterações - {$rep['nome']} - " . date('d/m/Y');
            
            try {
                $mail->clearAllRecipients();
                $mail->clearCCs();
                $mail->clearBCCs();
                $mail->clearAttachments();
                $mail->addAddress($emailRepLimpo, $rep['nome']);
                
                // ============================================================
                // ADICIONA AS CÓPIAS (CC) DA LISTA CONFIGURADA
                // ============================================================
                foreach ($emailsCC as $emailCC) {
                    if (filter_var($emailCC, FILTER_VALIDATE_EMAIL)) {
                        $mail->addCC($emailCC);
                    }
                }
                
                if (Uteis::enviarEmail($mail, $emailRepLimpo, $rep['nome'], $assunto, $corpoEmail, [], [])) {
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
       
            // Pequena pausa entre envios
            sleep(2);
        }

        // Fecha a conexão SMTP principal após o término do loop
        $mail->smtpClose();

        // ============================================================
        // E-MAIL CONSOLIDADO
        // ============================================================
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

        // ============================================================
        // ENVIA CONSOLIDADO PARA OS DESTINATÁRIOS CONFIGURADOS
        // ============================================================
        if (!empty($emailsConsolidado)) {
            try {
                $mail->clearAllRecipients();
                $mail->clearCCs();
                $mail->clearBCCs();
                $mail->clearAttachments();
                
                foreach ($emailsConsolidado as $emailDestino) {
                    if (filter_var($emailDestino, FILTER_VALIDATE_EMAIL)) {
                        $mail->addAddress($emailDestino);
                    }
                }
                
                $mail->Subject = 'Relatório Consolidado de Alterações - ' . date('d/m/Y');
                $mail->Body = $corpoConsolidado;
                
                if ($mail->send()) {
                    echo "📋 Cópia consolidada enviada para a gerência\n";
                }
            } catch (Exception $e) {
                echo "❌ Falha ao enviar e-mail consolidado: " . $e->getMessage() . "\n";
            }
        }
        
        // Garante o fechamento da conexão após o consolidado
        $mail->smtpClose();

        echo "🎉 PROCESSAMENTO CONCLUÍDO!\n";
        echo "📊 Resumo: $enviados enviados, $falhas falhas\n";
        
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
                    window.location.href = 'https://api.nutricionalbr.com/portal/';
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