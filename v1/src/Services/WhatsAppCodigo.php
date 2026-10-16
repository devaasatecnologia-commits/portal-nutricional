<?php
namespace Nutricional\Services;

/** Codigo de verificacao de 6 digitos enviado por WhatsApp: valido por 5 min, uso unico, max. 5 tentativas. */
class WhatsAppCodigo
{
    public const VALIDADE_MIN = 5;
    private const MAX_TENTATIVAS = 5;
    private const INTERVALO_SEG = 60;
    private const MAX_ENVIOS_HORA = 5;

    public static function garantirTabela(\PDO $pdo): void
    {
        // Sem permissao de DDL: rodar docs/segredos_migration.sql com o dono do banco.
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS admin_whatsapp_codigo (
                    idusuario INT PRIMARY KEY,
                    hash VARCHAR(64) NOT NULL,
                    expira TIMESTAMP NOT NULL,
                    tentativas INT NOT NULL DEFAULT 0,
                    enviado_em TIMESTAMP NOT NULL DEFAULT NOW()
                )
            ");
        } catch (\Throwable $e) {
            error_log('[WhatsAppCodigo] CREATE TABLE ignorado: ' . $e->getMessage());
        }
    }

    public static function numero(\PDO $pdo, int $idusuario): ?string
    {
        try {
            $st = $pdo->prepare("SELECT whatsapp FROM usuario_seguranca WHERE idusuario = :id");
            $st->execute(['id' => $idusuario]);
            $n = (string)$st->fetchColumn();
            return $n !== '' ? $n : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** So exige quando ha provedor configurado E o usuario cadastrou o numero (evita lockout). */
    public static function exigido(\PDO $pdo, int $idusuario): bool
    {
        return WhatsAppService::provedor() !== null && self::numero($pdo, $idusuario) !== null;
    }

    /** @return array{ok:bool, erro?:string, status?:int, destino?:string} */
    public static function enviar(\PDO $pdo, int $idusuario, string $username, ?string $ip): array
    {
        $numero = self::numero($pdo, $idusuario);
        if (WhatsAppService::provedor() === null || $numero === null) {
            return ['ok' => false, 'status' => 409, 'erro' => 'WhatsApp nao configurado para este usuario'];
        }
        $st = $pdo->prepare("SELECT 1 FROM admin_whatsapp_codigo WHERE idusuario = :id AND enviado_em > NOW() - INTERVAL '" . self::INTERVALO_SEG . " seconds'");
        $st->execute(['id' => $idusuario]);
        if ($st->fetchColumn()) {
            return ['ok' => false, 'status' => 429, 'erro' => 'Aguarde ' . self::INTERVALO_SEG . 's para pedir outro codigo'];
        }
        $st = $pdo->prepare("SELECT COUNT(*) FROM admin_segredos_log WHERE idusuario = :id AND chave = 'whatsapp' AND acao = 'enviar' AND created_at > NOW() - INTERVAL '1 hour'");
        $st->execute(['id' => $idusuario]);
        if ((int)$st->fetchColumn() >= self::MAX_ENVIOS_HORA) {
            return ['ok' => false, 'status' => 429, 'erro' => 'Limite de codigos por hora atingido'];
        }

        $codigo = (string)random_int(100000, 999999);
        $pdo->prepare("
            INSERT INTO admin_whatsapp_codigo (idusuario, hash, expira, tentativas, enviado_em)
            VALUES (:id, :h, NOW() + INTERVAL '" . self::VALIDADE_MIN . " minutes', 0, NOW())
            ON CONFLICT (idusuario) DO UPDATE
               SET hash = EXCLUDED.hash, expira = EXCLUDED.expira, tentativas = 0, enviado_em = NOW()
        ")->execute(['id' => $idusuario, 'h' => self::hash($idusuario, $codigo)]);

        $ok = WhatsAppService::enviarCodigo($numero, $codigo);
        try {
            $pdo->prepare("INSERT INTO admin_segredos_log (chave, acao, idusuario, username, ip, sucesso) VALUES ('whatsapp', 'enviar', :i, :u, :ip, :s)")
                ->execute(['i' => $idusuario, 'u' => $username, 'ip' => $ip, 's' => $ok ? 'true' : 'false']);
        } catch (\Throwable $e) {
            error_log('[WhatsAppCodigo] log falhou: ' . $e->getMessage());
        }
        if (!$ok) {
            $pdo->prepare("DELETE FROM admin_whatsapp_codigo WHERE idusuario = :id")->execute(['id' => $idusuario]);
            return ['ok' => false, 'status' => 502, 'erro' => 'Falha ao enviar o WhatsApp. Verifique as credenciais do provedor.'];
        }
        return ['ok' => true, 'destino' => WhatsAppService::mascarar($numero)];
    }

    /** Para endpoints sem TOTP (troca de senha): retorna mensagem de erro ou null se liberado. */
    public static function conferir(\PDO $pdo, int $idusuario, string $codigo): ?string
    {
        if (!self::exigido($pdo, $idusuario)) {
            return null;
        }
        $res = self::verificar($pdo, $idusuario, trim($codigo));
        if ($res === 'ok') {
            return null;
        }
        return $res === 'expirado'
            ? 'Codigo do WhatsApp ausente ou expirado. Envie um novo codigo.'
            : 'Codigo do WhatsApp incorreto';
    }

    /** @return string 'ok' | 'invalido' | 'expirado' */
    public static function verificar(\PDO $pdo, int $idusuario, string $codigo): string
    {
        $st = $pdo->prepare("SELECT hash, tentativas, expira > NOW() AS vigente FROM admin_whatsapp_codigo WHERE idusuario = :id");
        $st->execute(['id' => $idusuario]);
        $r = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$r) {
            return 'expirado';
        }
        if (!$r['vigente'] || (int)$r['tentativas'] >= self::MAX_TENTATIVAS) {
            $pdo->prepare("DELETE FROM admin_whatsapp_codigo WHERE idusuario = :id")->execute(['id' => $idusuario]);
            return 'expirado';
        }
        if (!preg_match('/^\d{6}$/', $codigo) || !hash_equals($r['hash'], self::hash($idusuario, $codigo))) {
            $pdo->prepare("UPDATE admin_whatsapp_codigo SET tentativas = tentativas + 1 WHERE idusuario = :id")->execute(['id' => $idusuario]);
            return 'invalido';
        }
        $pdo->prepare("DELETE FROM admin_whatsapp_codigo WHERE idusuario = :id")->execute(['id' => $idusuario]);
        return 'ok';
    }

    private static function hash(int $idusuario, string $codigo): string
    {
        return hash_hmac('sha256', $idusuario . ':' . $codigo, 'wa-codigo|' . CHAVE_SECRETA);
    }
}
