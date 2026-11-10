<?php
/**
 * CLI Helper — Antigravity Agent & Chamados Integrator
 * Uso pelo Antigravity para ler, assumir e concluir chamados direto no PostgreSQL.
 */
require_once __DIR__ . '/../v1/bootstrap/app.php';

function limpaStr($str) {
    if ($str === null) return '';
    return preg_replace('/[\xF0-\xF7][\x80-\xBF]{3}/', '', (string)$str);
}

$pdo = getPDO();

$cmd = $argv[1] ?? 'help';
$id = isset($argv[2]) ? (int)$argv[2] : 0;

switch ($cmd) {
    case 'ver':
        if ($id <= 0) die("Uso: php scripts/antigravity.php ver <idchamado>\n");
        $stmt = $pdo->prepare("SELECT * FROM chamado WHERE idchamado = :id");
        $stmt->execute(['id' => $id]);
        $c = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$c) die("Chamado #$id nao encontrado.\n");

        echo "========================================================\n";
        echo "CHAMADO #{$c['idchamado']} [{$c['tipo_sistema']}] [Status: {$c['status']}]\n";
        echo "Modulo: {$c['modulo_nome']} ({$c['modulo_slug']})\n";
        echo "Titulo: {$c['titulo']}\n";
        echo "Criticidade: {$c['criticidade']} | Categoria: {$c['categoria']}\n";
        echo "Solicitante: {$c['solicitante']} em {$c['datahora']}\n";
        echo "--------------------------------------------------------\n";
        echo "MOTIVO / RELATO:\n{$c['motivo']}\n";
        if (!empty($c['diagnostico_ia'])) {
            echo "--------------------------------------------------------\n";
            echo "DOSSIE TECNICO (IA):\n{$c['diagnostico_ia']}\n";
        }
        echo "========================================================\n";
        break;

    case 'assumir':
        if ($id <= 0) die("Uso: php scripts/antigravity.php assumir <idchamado>\n");
        
        // Mudar status para 2 (Em Correção)
        $stmt = $pdo->prepare("
            UPDATE chamado 
            SET status = 2, atendente = 'ANTIGRAVITY', atualizado_em = NOW() 
            WHERE idchamado = :id
        ");
        $stmt->execute(['id' => $id]);

        // Registrar na timeline
        $idOp = (int)$pdo->query("SELECT COALESCE(MAX(idoperacao), 0) + 1 FROM chamado_operacao WHERE idchamado = $id")->fetchColumn();
        $stmtOp = $pdo->prepare("
            INSERT INTO chamado_operacao (idchamado, idoperacao, descricao, datahora, usuario, acao, tipo, detalhes)
            VALUES (:idchamado, :idop, 'Antigravity assumiu a resolucao tecnica no codigo', NOW(), 'ANTIGRAVITY', 2, 'mudanca_status', 'Iniciando analise de codigo, aplicacao do checklist do Plano Diretor e validacao local.')
        ");
        $stmtOp->execute(['idchamado' => $id, 'idop' => $idOp]);

        echo "[OK] Chamado #$id assumido pelo Antigravity! Status atualizado para 'Em Correcao' (2).\n";
        break;

    case 'concluir':
        if ($id <= 0) die("Uso: php scripts/antigravity.php concluir <idchamado> \"<solucao>\" [versao]\n");
        $solucao = $argv[3] ?? 'Resolvido pelo Antigravity conforme checklist do Plano Diretor.';
        $versao = $argv[4] ?? 'v3.5';

        $stmt = $pdo->prepare("
            UPDATE chamado 
            SET 
                status = 3, 
                solucao_tecnica = :solucao, 
                versao_entrega = :versao, 
                concluido_em = NOW(), 
                atualizado_em = NOW()
            WHERE idchamado = :id
        ");
        $stmt->execute([
            'id' => $id,
            'solucao' => limpaStr($solucao),
            'versao' => limpaStr($versao)
        ]);

        $idOp = (int)$pdo->query("SELECT COALESCE(MAX(idoperacao), 0) + 1 FROM chamado_operacao WHERE idchamado = $id")->fetchColumn();
        $stmtOp = $pdo->prepare("
            INSERT INTO chamado_operacao (idchamado, idoperacao, descricao, datahora, usuario, acao, tipo, detalhes)
            VALUES (:idchamado, :idop, 'Resolucao e Entrega Tecnica Concluida pelo Antigravity', NOW(), 'ANTIGRAVITY', 3, 'resolucao', :detalhes)
        ");
        $stmtOp->execute([
            'idchamado' => $id,
            'idop' => $idOp,
            'detalhes' => limpaStr($solucao)
        ]);

        echo "[OK] Chamado #$id marcado como CONCLUIDO (Status 3) pelo Antigravity!\n";
        echo "Solucao gravada na timeline do chamado.\n";
        break;

    default:
        echo "Comandos disponiveis:\n";
        echo "  php scripts/antigravity.php ver <id>\n";
        echo "  php scripts/antigravity.php assumir <id>\n";
        echo "  php scripts/antigravity.php concluir <id> \"<solucao>\" [versao]\n";
        break;
}
