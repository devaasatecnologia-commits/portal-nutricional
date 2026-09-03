<?php
/**
 * TESTE AUTOMATIZADO - MÓDULO DE MARKETING (VERSÃO CORRIGIDA)
 * ===========================================================
 * Executar: php test_marketing_v2.php
 */

header('Content-Type: text/plain; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configurações
$baseUrl = 'http://localhost:8080/v1';
$testResults = [
    'pass' => 0,
    'fail' => 0,
    'total' => 0
];

function printTest($name, $status, $message = '') {
    global $testResults;
    $testResults['total']++;
    if ($status === 'PASS') {
        $testResults['pass']++;
        echo "✅ PASS - {$name}";
    } else {
        $testResults['fail']++;
        echo "❌ FAIL - {$name}";
    }
    if ($message) {
        echo " - {$message}";
    }
    echo "\n";
}

function printSection($title) {
    echo "\n" . str_repeat("═", 70) . "\n";
    echo "📌 {$title}\n";
    echo str_repeat("═", 70) . "\n";
}

function printSummary() {
    global $testResults;
    echo "\n" . str_repeat("═", 70) . "\n";
    echo "📊 RESUMO DOS TESTES\n";
    echo str_repeat("═", 70) . "\n";
    echo "✅ PASS: {$testResults['pass']}\n";
    echo "❌ FAIL: {$testResults['fail']}\n";
    echo "📊 TOTAL: {$testResults['total']}\n";
    
    $score = $testResults['total'] > 0 ? round(($testResults['pass'] / $testResults['total']) * 100, 2) : 0;
    echo "\n🎯 SCORE: {$score}%\n";
    
    if ($score >= 90) {
        echo "🏆 SISTEMA EXCELENTE! Pronto para produção.\n";
    } elseif ($score >= 70) {
        echo "👍 SISTEMA BOM! Pequenos ajustes recomendados.\n";
    } else {
        echo "⚠️ SISTEMA COM PROBLEMAS! Necessita revisão.\n";
    }
}

function fazerRequisicao($url, $token = null, $method = 'GET', $data = null) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    $headers = ['Content-Type: application/json'];
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    return [
        'code' => $httpCode,
        'body' => $response,
        'error' => $error
    ];
}

// ============================================================================
// 1. FAZER LOGIN
// ============================================================================
printSection("1. AUTENTICACAO");

echo "Fazendo login...\n";

$loginData = [
    'user' => 'alan',
    'pass' => '252686'
];

$result = fazerRequisicao($baseUrl . '/auth/login', null, 'POST', $loginData);
$token = null;

if ($result['code'] === 200) {
    $data = json_decode($result['body'], true);
    if (isset($data['token'])) {
        $token = $data['token'];
        printTest("Login", "PASS", "Token obtido com sucesso");
    } else {
        printTest("Login", "FAIL", "Token nao encontrado na resposta");
        exit(1);
    }
} else {
    printTest("Login", "FAIL", "HTTP {$result['code']}: " . substr($result['body'], 0, 100));
    exit(1);
}

if (!$token) {
    printTest("Token", "FAIL", "Token invalido");
    exit(1);
}

// ============================================================================
// 2. TESTAR PING (HEALTH CHECK)
// ============================================================================
printSection("2. HEALTH CHECK");

echo "Verificando saúde do sistema...\n";
$result = fazerRequisicao('http://localhost:8080/ping');
if ($result['code'] === 200) {
    $ping = json_decode($result['body'], true);
    $status = isset($ping['status']) ? $ping['status'] : 'ok';
    printTest("Ping / Health Check", "PASS", "Status: {$status}");
} else {
    printTest("Ping / Health Check", "FAIL", "HTTP {$result['code']}");
}

// ============================================================================
// 3. TESTAR TIPOS DE META
// ============================================================================
printSection("3. TIPOS DE META");

echo "Listando tipos de meta...\n";
$result = fazerRequisicao($baseUrl . '/meta-builder/tipos', $token);

if ($result['code'] === 200) {
    $data = json_decode($result['body'], true);
    $tipos = array();
    if (isset($data['data']) && is_array($data['data'])) {
        $tipos = $data['data'];
    }
    printTest("Listar tipos de meta", "PASS", count($tipos) . " tipos encontrados");
    
    foreach ($tipos as $tipo) {
        $id = isset($tipo['id']) ? $tipo['id'] : '?';
        $nome = isset($tipo['nome']) ? $tipo['nome'] : '?';
        echo "   - ID: {$id} - {$nome}\n";
    }
} else {
    printTest("Listar tipos de meta", "FAIL", "HTTP {$result['code']}");
}

// ============================================================================
// 4. TESTAR INSTÂNCIAS DE META
// ============================================================================
printSection("4. INSTANCIAS DE META");

echo "Listando metas ativas...\n";
$result = fazerRequisicao($baseUrl . '/meta-builder/instancias/ativas', $token);

if ($result['code'] === 200) {
    $data = json_decode($result['body'], true);
    $metas = array();
    if (isset($data['data']) && is_array($data['data'])) {
        $metas = $data['data'];
    }
    printTest("Listar metas ativas", "PASS", count($metas) . " metas encontradas");
    
    foreach ($metas as $meta) {
        $id = isset($meta['id']) ? $meta['id'] : '?';
        $titulo = isset($meta['titulo']) ? $meta['titulo'] : '?';
        $status = isset($meta['status']) ? $meta['status'] : '?';
        echo "   - ID: {$id} - {$titulo} ({$status})\n";
    }
} else {
    printTest("Listar metas ativas", "FAIL", "HTTP {$result['code']}");
}

// ============================================================================
// 5. TESTAR DASHBOARD DE METAS
// ============================================================================
echo "\nCarregando dashboard de metas...\n";
$result = fazerRequisicao($baseUrl . '/meta-builder/dashboard', $token);

if ($result['code'] === 200) {
    $data = json_decode($result['body'], true);
    $metas = array();
    if (isset($data['data']) && is_array($data['data'])) {
        $metas = $data['data'];
    }
    printTest("Dashboard de metas", "PASS", count($metas) . " metas no dashboard");
} else {
    printTest("Dashboard de metas", "FAIL", "HTTP {$result['code']}");
}

// ============================================================================
// 6. TESTAR CLIENTES CRM
// ============================================================================
printSection("5. CLIENTES CRM");

echo "Listando clientes...\n";
$result = fazerRequisicao($baseUrl . '/marketing/clientes?limite=10', $token);

if ($result['code'] === 200) {
    $data = json_decode($result['body'], true);
    $clientes = array();
    if (isset($data['clientes']) && is_array($data['clientes'])) {
        $clientes = $data['clientes'];
    }
    printTest("Listar clientes", "PASS", count($clientes) . " clientes encontrados");
    
    foreach ($clientes as $cliente) {
        $id = isset($cliente['id']) ? $cliente['id'] : '?';
        $nome = isset($cliente['nome']) ? $cliente['nome'] : '?';
        $status = isset($cliente['status']) ? $cliente['status'] : '?';
        echo "   - ID: {$id} - {$nome} ({$status})\n";
    }
} else {
    printTest("Listar clientes", "FAIL", "HTTP {$result['code']}");
}

// ============================================================================
// 7. TESTAR DASHBOARD CRM
// ============================================================================
echo "\nCarregando dashboard CRM...\n";
$result = fazerRequisicao($baseUrl . '/marketing/crm-dashboard', $token);

if ($result['code'] === 200) {
    $data = json_decode($result['body'], true);
    $cards = array();
    if (isset($data['cards']) && is_array($data['cards'])) {
        $cards = $data['cards'];
    }
    $totalClientes = isset($cards['total_clientes']) ? $cards['total_clientes'] : 0;
    printTest("Dashboard CRM", "PASS", "Total clientes: {$totalClientes}");
} else {
    printTest("Dashboard CRM", "FAIL", "HTTP {$result['code']}");
}

// ============================================================================
// 8. TESTAR LEADS
// ============================================================================
printSection("6. LEADS");

echo "Listando leads...\n";
$result = fazerRequisicao($baseUrl . '/marketing/leads?limite=10', $token);

if ($result['code'] === 200) {
    $leads = json_decode($result['body'], true);
    if (!is_array($leads)) {
        $leads = array();
    }
    printTest("Listar leads", "PASS", count($leads) . " leads encontrados");
} else {
    printTest("Listar leads", "FAIL", "HTTP {$result['code']}");
}

// ============================================================================
// 9. TESTAR COMPROMISSOS
// ============================================================================
printSection("7. COMPROMISSOS");

echo "Listando compromissos...\n";
$result = fazerRequisicao($baseUrl . '/marketing/compromissos', $token);

if ($result['code'] === 200) {
    $data = json_decode($result['body'], true);
    $compromissos = array();
    if (isset($data['data']) && is_array($data['data'])) {
        $compromissos = $data['data'];
    }
    printTest("Listar compromissos", "PASS", count($compromissos) . " compromissos encontrados");
} else {
    printTest("Listar compromissos", "FAIL", "HTTP {$result['code']}");
}

// ============================================================================
// 10. TESTAR ESTATÍSTICAS DE COMPROMISSOS
// ============================================================================
echo "\nCarregando estatisticas de compromissos...\n";
$result = fazerRequisicao($baseUrl . '/marketing/compromissos/estatisticas', $token);

if ($result['code'] === 200) {
    $stats = json_decode($result['body'], true);
    $total = isset($stats['total']) ? $stats['total'] : 0;
    $concluidos = isset($stats['concluidos']) ? $stats['concluidos'] : 0;
    printTest("Estatisticas de compromissos", "PASS", "Total: {$total}, Concluidos: {$concluidos}");
} else {
    printTest("Estatisticas de compromissos", "FAIL", "HTTP {$result['code']}");
}

// ============================================================================
// 11. TESTAR KPIs
// ============================================================================
printSection("8. KPIs");

echo "Carregando KPIs do dashboard...\n";
$result = fazerRequisicao($baseUrl . '/marketing/kpis', $token);

if ($result['code'] === 200) {
    $kpis = json_decode($result['body'], true);
    $totalLeads = isset($kpis['total_leads']) ? $kpis['total_leads'] : 0;
    $vendas = isset($kpis['vendas']) ? $kpis['vendas'] : 0;
    printTest("KPIs", "PASS", "Leads: {$totalLeads}, Vendas: {$vendas}");
} else {
    printTest("KPIs", "FAIL", "HTTP {$result['code']}");
}

// ============================================================================
// 12. TESTAR DADOS DO GRÁFICO
// ============================================================================
echo "\nCarregando dados do grafico...\n";
$result = fazerRequisicao($baseUrl . '/marketing/dados-grafico?periodo=7dias', $token);

if ($result['code'] === 200) {
    $data = json_decode($result['body'], true);
    $dados = array();
    if (isset($data['dados']) && is_array($data['dados'])) {
        $dados = $data['dados'];
    }
    printTest("Dados do grafico", "PASS", count($dados) . " pontos de dados");
} else {
    printTest("Dados do grafico", "FAIL", "HTTP {$result['code']}");
}

// ============================================================================
// 13. TESTAR RESUMO GERAL
// ============================================================================
echo "\nCarregando resumo geral...\n";
$result = fazerRequisicao($baseUrl . '/marketing/resumo-geral', $token);

if ($result['code'] === 200) {
    $resumo = json_decode($result['body'], true);
    $crm = isset($resumo['crm']) ? $resumo['crm'] : array();
    $totalClientes = isset($crm['total_clientes']) ? $crm['total_clientes'] : 0;
    printTest("Resumo geral", "PASS", "Clientes: {$totalClientes}");
} else {
    printTest("Resumo geral", "FAIL", "HTTP {$result['code']}");
}

// ============================================================================
// 14. TESTAR VIEW CLIENTES UNIFICADA
// ============================================================================
printSection("9. VIEW CLIENTES UNIFICADA");

echo "Consultando clientes via view unificada...\n";
$result = fazerRequisicao($baseUrl . '/marketing/clientes/consulta?limite=10', $token);

if ($result['code'] === 200) {
    $data = json_decode($result['body'], true);
    $clientes = array();
    if (isset($data['clientes']) && is_array($data['clientes'])) {
        $clientes = $data['clientes'];
    }
    printTest("View clientes unificada", "PASS", count($clientes) . " clientes encontrados");
    
    foreach ($clientes as $cliente) {
        $idCrm = isset($cliente['id_crm']) ? $cliente['id_crm'] : '?';
        $idErp = isset($cliente['id_erp']) ? $cliente['id_erp'] : '?';
        $nome = isset($cliente['nome']) ? $cliente['nome'] : '?';
        $origem = isset($cliente['origem_dados']) ? $cliente['origem_dados'] : '?';
        echo "   - ID: {$idCrm} | ERP: {$idErp} | {$nome} ({$origem})\n";
    }
} else {
    printTest("View clientes unificada", "FAIL", "HTTP {$result['code']}");
}

// ============================================================================
// 15. TESTAR MÉTRICAS DE PROGRESSO
// ============================================================================
printSection("10. METAS - PROGRESSO");

echo "Carregando progresso das metas...\n";
$result = fazerRequisicao($baseUrl . '/marketing/metas-progresso', $token);

if ($result['code'] === 200) {
    $metas = json_decode($result['body'], true);
    if (!is_array($metas)) {
        $metas = array();
    }
    printTest("Metas com progresso", "PASS", count($metas) . " metas analisadas");
    
    foreach ($metas as $meta) {
        $titulo = isset($meta['titulo']) ? $meta['titulo'] : '?';
        $pctLeads = isset($meta['pct_leads']) ? $meta['pct_leads'] : 0;
        $pctFat = isset($meta['pct_faturamento']) ? $meta['pct_faturamento'] : 0;
        echo "   - {$titulo}: {$pctLeads}% leads | {$pctFat}% faturamento\n";
    }
} else {
    printTest("Metas com progresso", "FAIL", "HTTP {$result['code']}");
}

// ============================================================================
// 16. TESTAR COMPARATIVO MENSAL
// ============================================================================
echo "\nCarregando comparativo mensal...\n";
$result = fazerRequisicao($baseUrl . '/marketing/comparativo-mensal', $token);

if ($result['code'] === 200) {
    $comparativo = json_decode($result['body'], true);
    $leadsAtual = isset($comparativo['leads_atual']) ? $comparativo['leads_atual'] : 0;
    $leadsAnterior = isset($comparativo['leads_anterior']) ? $comparativo['leads_anterior'] : 0;
    printTest("Comparativo mensal", "PASS", "Leads: {$leadsAtual} vs {$leadsAnterior}");
} else {
    printTest("Comparativo mensal", "FAIL", "HTTP {$result['code']}");
}

// ============================================================================
// 17. TESTAR NOTIFICAÇÕES CRM
// ============================================================================
printSection("11. NOTIFICACOES");

echo "Listando notificacoes...\n";
$result = fazerRequisicao($baseUrl . '/crm/notificacoes?limite=10', $token);

if ($result['code'] === 200) {
    $data = json_decode($result['body'], true);
    $notificacoes = array();
    if (isset($data['notificacoes']) && is_array($data['notificacoes'])) {
        $notificacoes = $data['notificacoes'];
    }
    printTest("Listar notificacoes CRM", "PASS", count($notificacoes) . " notificacoes");
} else {
    printTest("Listar notificacoes CRM", "FAIL", "HTTP {$result['code']}");
}

// ============================================================================
// 18. TESTAR ALERTAS CRM
// ============================================================================
echo "\nGerando alertas CRM...\n";
$result = fazerRequisicao($baseUrl . '/crm/gerar-alertas', $token);

if ($result['code'] === 200) {
    $alertas = json_decode($result['body'], true);
    $msg = isset($alertas['message']) ? $alertas['message'] : 'Alertas gerados';
    printTest("Gerar alertas CRM", "PASS", $msg);
} else {
    printTest("Gerar alertas CRM", "FAIL", "HTTP {$result['code']}");
}

// ============================================================================
// 19. RESUMO FINAL
// ============================================================================
printSummary();

// ============================================================================
// 20. RECOMENDAÇÕES FINAIS
// ============================================================================
if ($testResults['fail'] > 0) {
    echo "\n" . str_repeat("═", 70) . "\n";
    echo "🔧 RECOMENDACOES:\n";
    echo str_repeat("═", 70) . "\n";
    echo "   • Verifique os endpoints que falharam\n";
    echo "   • Confirme se o servidor esta rodando\n";
    echo "   • Verifique as credenciais de login\n";
    echo "   • Consulte os logs de erro do PHP\n";
}