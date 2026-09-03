<?php
namespace Nutricional\Controllers;

use PDO;

class ChatController
{
    private $pdo;
    
    public function __construct() { $this->pdo = \getPDO(); }
    
    // GET /v1/chat/contatos
public function getContatos($request, $response) {
    $user = $request->getAttribute('user');
    $meuId = $user['idusuario'] ?? 0;
    
    $sql = "SELECT DISTINCT u.idusuario, u.username, u.foto_perfil,
                (SELECT COUNT(*) FROM chat_mensagens 
                 WHERE idusuario_destinatario = :eu AND idusuario_remetente = u.idusuario AND lida = FALSE
                ) as nao_lidas
            FROM usuario u
            WHERE u.idusuario != :eu AND u.inativo = 'N'
            ORDER BY nao_lidas DESC, u.username";
    
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute(['eu' => $meuId]);
    $contatos = $stmt->fetchAll();
    
    // Processar URL da foto
    foreach ($contatos as &$c) {
        if (!empty($c['foto_perfil'])) {
            $c['foto_url'] = (strpos($c['foto_perfil'], 'http') === 0) 
                ? $c['foto_perfil'] 
                : 'https://api.nutricionalbr.com/' . $c['foto_perfil'];
        } else {
            $c['foto_url'] = null;
        }
    }
    
    return $this->json($response, $contatos);
}
    
    // GET /v1/chat/mensagens/{outroUsuario}
    public function getMensagens($request, $response, $args) {
        $user = $request->getAttribute('user');
        $meuId = $user['idusuario'] ?? 0;
        $outroId = (int)($args['outroUsuario'] ?? 0);
        
        $sql = "SELECT cm.*, u.username as remetente_nome
                FROM chat_mensagens cm
                JOIN usuario u ON u.idusuario = cm.idusuario_remetente
                WHERE (cm.idusuario_remetente = :eu AND cm.idusuario_destinatario = :outro)
                   OR (cm.idusuario_remetente = :outro AND cm.idusuario_destinatario = :eu)
                ORDER BY cm.datahora ASC
                LIMIT 100";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['eu' => $meuId, 'outro' => $outroId]);
        return $this->json($response, $stmt->fetchAll());
    }
    
    // POST /v1/chat/enviar
    public function enviarMensagem($request, $response) {
        $user = $request->getAttribute('user');
        $meuId = $user['idusuario'] ?? 0;
        $input = json_decode($request->getBody()->getContents(), true) ?? [];
        
        $destinatario = (int)($input['destinatario'] ?? 0);
        $mensagem = trim($input['mensagem'] ?? '');
        
        if (empty($mensagem) || $destinatario <= 0) {
            return $this->json($response, ['error' => 'Dados inválidos'], 400);
        }
        
        $sql = "INSERT INTO chat_mensagens (idusuario_remetente, idusuario_destinatario, mensagem) 
                VALUES (:rem, :dest, :msg)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['rem' => $meuId, 'dest' => $destinatario, 'msg' => $mensagem]);
        
        return $this->json($response, ['success' => true, 'id' => $this->pdo->lastInsertId()]);
    }
    
    // POST /v1/chat/marcar-lida/{remetente}
    public function marcarLida($request, $response, $args) {
        $user = $request->getAttribute('user');
        $meuId = $user['idusuario'] ?? 0;
        $remetente = (int)($args['remetente'] ?? 0);
        
        $sql = "UPDATE chat_mensagens SET lida = TRUE 
                WHERE idusuario_destinatario = :eu AND idusuario_remetente = :rem AND lida = FALSE";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['eu' => $meuId, 'rem' => $remetente]);
        
        return $this->json($response, ['success' => true]);
    }


    
    // GET /v1/chat/nao-lidas
    public function getNaoLidas($request, $response) {
        $user = $request->getAttribute('user');
        $meuId = $user['idusuario'] ?? 0;
        
        $sql = "SELECT COUNT(*) FROM chat_mensagens 
                WHERE idusuario_destinatario = :eu AND lida = FALSE";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['eu' => $meuId]);
        
        return $this->json($response, ['total' => (int)$stmt->fetchColumn()]);
    }
    
    private function json($response, $data, $status = 200) {
        $response->getBody()->write(json_encode($data));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }



    // GET /v1/chat/minhas-conversas
public function getMinhasConversas($request, $response) {
    $user = $request->getAttribute('user');
    $meuId = $user['idusuario'] ?? 0;
    
    $sql = "SELECT 
                u.idusuario, u.username,
                (SELECT COUNT(*) FROM chat_mensagens WHERE idusuario_remetente = :eu AND idusuario_destinatario = u.idusuario) as enviadas,
                (SELECT COUNT(*) FROM chat_mensagens WHERE idusuario_destinatario = :eu AND idusuario_remetente = u.idusuario) as recebidas,
                (SELECT mensagem FROM chat_mensagens 
                 WHERE ((idusuario_remetente = :eu AND idusuario_destinatario = u.idusuario) 
                     OR (idusuario_remetente = u.idusuario AND idusuario_destinatario = :eu))
                 ORDER BY datahora DESC LIMIT 1) as ultima_mensagem,
                (SELECT datahora FROM chat_mensagens 
                 WHERE ((idusuario_remetente = :eu AND idusuario_destinatario = u.idusuario) 
                     OR (idusuario_remetente = u.idusuario AND idusuario_destinatario = :eu))
                 ORDER BY datahora DESC LIMIT 1) as ultima_data
            FROM chat_mensagens cm
            JOIN usuario u ON (u.idusuario = cm.idusuario_remetente OR u.idusuario = cm.idusuario_destinatario)
            WHERE (cm.idusuario_remetente = :eu OR cm.idusuario_destinatario = :eu)
              AND u.idusuario != :eu
            GROUP BY u.idusuario, u.username
            ORDER BY ultima_data DESC NULLS LAST";
    
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute(['eu' => $meuId]);
    return $this->json($response, $stmt->fetchAll());
}
}