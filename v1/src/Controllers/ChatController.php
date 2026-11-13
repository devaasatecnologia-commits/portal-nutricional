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
        $meuId = (int)($user['idusuario'] ?? 0);
        
        // Registra presença do usuário solicitante
        if ($meuId > 0) {
            try {
                $stPing = $this->pdo->prepare("
                    INSERT INTO usuario_online (idusuario, ultimo_ping)
                    VALUES (:uid, NOW())
                    ON CONFLICT (idusuario) DO UPDATE SET ultimo_ping = NOW()
                ");
                $stPing->execute(['uid' => $meuId]);
            } catch (\Throwable $e) {}
        }

        $sql = "SELECT DISTINCT u.idusuario, u.username, u.foto_perfil,
                    (SELECT COUNT(*) FROM chat_mensagens 
                     WHERE idusuario_destinatario = :eu AND idusuario_remetente = u.idusuario AND lida = FALSE
                    ) as nao_lidas,
                    (SELECT mensagem FROM chat_mensagens 
                     WHERE (idusuario_destinatario = :eu AND idusuario_remetente = u.idusuario)
                        OR (idusuario_destinatario = u.idusuario AND idusuario_remetente = :eu)
                     ORDER BY datahora DESC LIMIT 1
                    ) as ultima_mensagem,
                    (SELECT datahora FROM chat_mensagens 
                     WHERE (idusuario_destinatario = :eu AND idusuario_remetente = u.idusuario)
                        OR (idusuario_destinatario = u.idusuario AND idusuario_remetente = :eu)
                     ORDER BY datahora DESC LIMIT 1
                    ) as ultima_data,
                    EXISTS(
                        SELECT 1 FROM chat_digitando cd 
                        WHERE cd.idusuario_de = u.idusuario 
                          AND cd.idusuario_para = :eu 
                          AND cd.atualizado_em >= NOW() - INTERVAL '4 seconds'
                    ) as digitando,
                    uo.ultimo_ping,
                    (CASE WHEN uo.ultimo_ping >= NOW() - INTERVAL '70 seconds' THEN 1 ELSE 2 END) as ordem_online,
                    CASE 
                        WHEN uo.ultimo_ping >= NOW() - INTERVAL '70 seconds' THEN 'online'
                        WHEN uo.ultimo_ping >= NOW() - INTERVAL '5 minutes' THEN 'ausente'
                        ELSE 'offline'
                    END as status_online,
                    CASE 
                        WHEN uo.ultimo_ping >= NOW() - INTERVAL '70 seconds' THEN 'Online agora'
                        WHEN uo.ultimo_ping >= NOW() - INTERVAL '5 minutes' THEN 'Visto há instantes'
                        WHEN uo.ultimo_ping IS NOT NULL THEN 'Visto às ' || TO_CHAR(uo.ultimo_ping, 'HH24:MI')
                        ELSE 'Offline'
                    END as status_texto
                FROM usuario u
                LEFT JOIN usuario_online uo ON uo.idusuario = u.idusuario
                WHERE u.idusuario != :eu AND u.inativo = 'N'
                ORDER BY 
                    ordem_online ASC,
                    nao_lidas DESC,
                    ultima_data DESC NULLS LAST,
                    u.username ASC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['eu' => $meuId]);
        $contatos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Processar URL da foto e flags respeitando ambiente
        foreach ($contatos as &$c) {
            $c['digitando'] = (bool)($c['digitando'] ?? false);
            if (!empty($c['foto_perfil'])) {
                $foto = ltrim($c['foto_perfil'], '/');
                $c['foto_url'] = (strpos($foto, 'http') === 0) ? $foto : ('/' . $foto);
            } else {
                $c['foto_url'] = null;
            }
        }
        
        return $this->json($response, $contatos);
    }
    
    // GET /v1/chat/mensagens/{outroUsuario}
    public function getMensagens($request, $response, $args) {
        $user = $request->getAttribute('user');
        $meuId = (int)($user['idusuario'] ?? 0);
        $outroId = (int)($args['outroUsuario'] ?? 0);
        
        $sql = "SELECT cm.id, cm.id as idmensagem, cm.idusuario_remetente, cm.idusuario_destinatario, 
                       cm.mensagem, cm.lida, cm.datahora, u.username as remetente_nome
                FROM chat_mensagens cm
                JOIN usuario u ON u.idusuario = cm.idusuario_remetente
                WHERE (cm.idusuario_remetente = :eu AND cm.idusuario_destinatario = :outro)
                   OR (cm.idusuario_remetente = :outro AND cm.idusuario_destinatario = :eu)
                ORDER BY cm.datahora ASC
                LIMIT 150";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['eu' => $meuId, 'outro' => $outroId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Converte booleano de leitura
        foreach ($rows as &$r) {
            $r['lida'] = ($r['lida'] === true || $r['lida'] === 't' || $r['lida'] === 1 || $r['lida'] === '1');
        }

        return $this->json($response, $rows);
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
        $meuId = (int)($user['idusuario'] ?? 0);
        $remetente = (int)($args['remetente'] ?? 0);
        
        $sql = "UPDATE chat_mensagens SET lida = TRUE 
                WHERE idusuario_destinatario = :eu AND idusuario_remetente = :rem AND lida = FALSE";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['eu' => $meuId, 'rem' => $remetente]);
        
        return $this->json($response, ['success' => true]);
    }

    // POST /v1/chat/digitando
    public function notificarDigitando($request, $response) {
        $user = $request->getAttribute('user');
        $meuId = (int)($user['idusuario'] ?? 0);
        $input = json_decode($request->getBody()->getContents(), true) ?? [];
        $destinatario = (int)($input['para'] ?? 0);

        if ($meuId > 0 && $destinatario > 0) {
            try {
                $st = $this->pdo->prepare("
                    INSERT INTO chat_digitando (idusuario_de, idusuario_para, atualizado_em)
                    VALUES (:de, :para, NOW())
                    ON CONFLICT (idusuario_de, idusuario_para) DO UPDATE SET atualizado_em = NOW()
                ");
                $st->execute(['de' => $meuId, 'para' => $destinatario]);
            } catch (\Throwable $e) {}
        }

        return $this->json($response, ['success' => true]);
    }

    // POST /v1/chat/parar-digitando
    public function pararDigitando($request, $response) {
        $user = $request->getAttribute('user');
        $meuId = (int)($user['idusuario'] ?? 0);
        $input = json_decode($request->getBody()->getContents(), true) ?? [];
        $destinatario = (int)($input['para'] ?? 0);

        if ($meuId > 0 && $destinatario > 0) {
            try {
                $st = $this->pdo->prepare("
                    DELETE FROM chat_digitando WHERE idusuario_de = :de AND idusuario_para = :para
                ");
                $st->execute(['de' => $meuId, 'para' => $destinatario]);
            } catch (\Throwable $e) {}
        }

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

    // GET /v1/chat/ultimas-nao-lidas
    public function getUltimasNaoLidas($request, $response) {
        $user = $request->getAttribute('user');
        $meuId = $user['idusuario'] ?? 0;
        
        $sql = "SELECT cm.id, cm.id as idmensagem, cm.idusuario_remetente, cm.mensagem, cm.datahora,
                       u.username as remetente_nome
                FROM chat_mensagens cm
                JOIN usuario u ON u.idusuario = cm.idusuario_remetente
                WHERE cm.idusuario_destinatario = :eu AND cm.lida = FALSE
                ORDER BY cm.datahora DESC
                LIMIT 15";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['eu' => $meuId]);
        
        return $this->json($response, ['success' => true, 'mensagens' => $stmt->fetchAll(\PDO::FETCH_ASSOC)]);
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