<?php
// Carregar configurações corretamente
require_once __DIR__ . '/uteis.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// Verificar se a função getPDO existe
if (!function_exists('getPDO')) {
    die('ERRO: Função getPDO não encontrada. Verifique o arquivo uteis.php');
}

$pdo = getPDO();

if (!$pdo) {
    die('ERRO: Não foi possível conectar ao banco de dados');
}

echo "✅ Conexão com banco estabelecida!\n\n";

// Definir constante CHAVE_SECRETA se não existir
if (!defined('CHAVE_SECRETA')) {
    define('CHAVE_SECRETA', 'sua_chave_secreta_aqui');
}

$jwtSecret = CHAVE_SECRETA;

// Solicitar token do usuário
echo "Cole o token JWT (ou pressione Enter para usar o token do localStorage):\n";
$token = trim(fgets(STDIN));

if (empty($token)) {
    echo "Nenhum token fornecido. Saindo...\n";
    exit(0);
}

echo "\nTestando logout para token: " . substr($token, 0, 50) . "...\n\n";

try {
    // Decodificar token
    $decoded = JWT::decode($token, new Key($jwtSecret, 'HS256'));
    $jti = $decoded->jti ?? null;
    $idusuario = $decoded->idusuario ?? 0;
    $uid = $decoded->uid ?? 0;
    $exp = $decoded->exp ?? (time() + 3600);
    $username = $decoded->username ?? 'unknown';
    
    echo "📋 Informações do token:\n";
    echo "  - JTI: {$jti}\n";
    echo "  - ID Usuário: {$idusuario}\n";
    echo "  - UID: {$uid}\n";
    echo "  - Username: {$username}\n";
    echo "  - Expira em: " . date('Y-m-d H:i:s', $exp) . "\n\n";
    
    if (!$jti) {
        die("❌ ERRO: Token não contém JTI\n");
    }
    
    $tokenHash = hash('sha256', $token);
    echo "🔑 Token Hash: {$tokenHash}\n\n";
    
    // Verificar se a tabela existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'token_blacklist'");
    $tableExists = $stmt->rowCount() > 0;
    
    if (!$tableExists) {
        echo "📦 Criando tabela token_blacklist...\n";
        
        $sql = "
            CREATE TABLE IF NOT EXISTS token_blacklist (
                id INT AUTO_INCREMENT PRIMARY KEY,
                token_hash VARCHAR(64) NOT NULL,
                idusuario INT NOT NULL,
                jti VARCHAR(100) NOT NULL,
                expiracao DATETIME NOT NULL,
                motivo VARCHAR(50) DEFAULT 'logout',
                revoked_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                revoked_by_ip VARCHAR(45),
                user_agent TEXT,
                UNIQUE KEY unique_token_hash (token_hash),
                INDEX idx_jti (jti),
                INDEX idx_idusuario (idusuario),
                INDEX idx_expiracao (expiracao)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ";
        
        $pdo->exec($sql);
        echo "✅ Tabela token_blacklist criada com sucesso!\n\n";
    } else {
        echo "✅ Tabela token_blacklist já existe\n\n";
    }
    
    // Verificar se já está na blacklist
    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM token_blacklist WHERE jti = :jti");
    $stmtCheck->execute(['jti' => $jti]);
    $exists = $stmtCheck->fetchColumn();
    
    if ($exists) {
        echo "⚠️ Token já está na blacklist!\n";
        
        // Mostrar registro existente
        $stmt = $pdo->prepare("SELECT * FROM token_blacklist WHERE jti = :jti");
        $stmt->execute(['jti' => $jti]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "Registro existente:\n";
        print_r($row);
        
    } else {
        echo "📝 Inserindo token na blacklist...\n";
        
        // Inserir na blacklist
        $stmt = $pdo->prepare("
            INSERT INTO token_blacklist (token_hash, idusuario, jti, expiracao, motivo, revoked_by_ip, user_agent)
            VALUES (:hash, :idusuario, :jti, FROM_UNIXTIME(:exp), 'logout', :ip, :ua)
        ");
        
        $params = [
            'hash' => $tokenHash,
            'idusuario' => $idusuario,
            'jti' => $jti,
            'exp' => $exp,
            'ip' => '127.0.0.1',
            'ua' => 'CLI Test'
        ];
        
        $result = $stmt->execute($params);
        
        if ($result) {
            $affected = $stmt->rowCount();
            echo "✅ Token inserido na blacklist com sucesso! (Linhas afetadas: {$affected})\n\n";
            
            // Verificar inserção
            $stmt = $pdo->prepare("SELECT * FROM token_blacklist WHERE jti = :jti");
            $stmt->execute(['jti' => $jti]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo "📋 Registro inserido:\n";
            echo "  - ID: {$row['id']}\n";
            echo "  - JTI: {$row['jti']}\n";
            echo "  - Usuário ID: {$row['idusuario']}\n";
            echo "  - Motivo: {$row['motivo']}\n";
            echo "  - Revogado em: {$row['revoked_at']}\n";
            echo "  - Expira em: {$row['expiracao']}\n";
            
        } else {
            echo "❌ Falha ao inserir na blacklist\n";
            $error = $stmt->errorInfo();
            echo "Erro: " . json_encode($error) . "\n";
        }
    }
    
    // Estatísticas da blacklist
    echo "\n📊 Estatísticas da blacklist:\n";
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM token_blacklist");
    $total = $stmt->fetchColumn();
    echo "  - Total de tokens revogados: {$total}\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) as valid FROM token_blacklist WHERE expiracao > NOW()");
    $valid = $stmt->fetchColumn();
    echo "  - Tokens ainda válidos (não expirados): {$valid}\n";
    
} catch (Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}