<?php
namespace Nutricional\Services;

/**
 * Politica de seguranca por usuario: 2FA no login, inatividade e duracao do token.
 * Sem registro em usuario_seguranca valem os padroes (sem 2FA, 10 min, 240 min).
 */
class SegurancaUsuario
{
    public const PADRAO_INATIVIDADE_MIN = 10;
    public const PADRAO_TOKEN_MIN = 240;
    private const MAX_FALHAS = 5;
    private const JANELA_MIN = 10;

    public static function garantirTabela(\PDO $pdo): void
    {
        // Sem permissao de DDL: rodar docs/segredos_migration.sql com o dono do banco.
        try {
            if ($pdo->query("SELECT to_regclass('usuario_seguranca') IS NOT NULL")->fetchColumn()) {
                if (!$pdo->query("SELECT 1 FROM information_schema.columns WHERE table_name = 'usuario_seguranca' AND column_name = 'whatsapp'")->fetchColumn()) {
                    $pdo->exec("ALTER TABLE usuario_seguranca ADD COLUMN IF NOT EXISTS whatsapp VARCHAR(20)");
                }
                return;
            }
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS usuario_seguranca (
                    idusuario INT PRIMARY KEY,
                    whatsapp VARCHAR(20),
                    exige_2fa_login BOOLEAN NOT NULL DEFAULT false,
                    inatividade_min INT NOT NULL DEFAULT 10,
                    token_min INT NOT NULL DEFAULT 240,
                    atualizado_por VARCHAR(100),
                    atualizado_em TIMESTAMP DEFAULT NOW()
                )
            ");
        } catch (\Throwable $e) {
            error_log('[SegurancaUsuario] CREATE TABLE ignorado: ' . $e->getMessage());
        }
    }

    public static function config(\PDO $pdo, int $idusuario): array
    {
        $padrao = [
            'exige_2fa_login' => false,
            'inatividade_min' => self::PADRAO_INATIVIDADE_MIN,
            'token_min' => self::PADRAO_TOKEN_MIN,
        ];
        try {
            $st = $pdo->prepare("SELECT exige_2fa_login, inatividade_min, token_min FROM usuario_seguranca WHERE idusuario = :id");
            $st->execute(['id' => $idusuario]);
            $r = $st->fetch(\PDO::FETCH_ASSOC);
            if ($r) {
                return [
                    'exige_2fa_login' => (bool)$r['exige_2fa_login'],
                    'inatividade_min' => self::limitar((int)$r['inatividade_min'], 1, 480, $padrao['inatividade_min']),
                    'token_min' => self::limitar((int)$r['token_min'], 15, 1440, $padrao['token_min']),
                ];
            }
        } catch (\Throwable $e) {
            // tabela ausente: padroes (login nunca pode quebrar por causa desta politica)
        }
        return $padrao;
    }

    public static function limitar(int $v, int $min, int $max, int $padrao): int
    {
        return ($v >= $min && $v <= $max) ? $v : $padrao;
    }

    /** Usuario tem 2FA (TOTP) ativo? */
    public static function tem2fa(\PDO $pdo, int $idusuario): bool
    {
        try {
            $st = $pdo->prepare("SELECT ativo FROM admin_2fa WHERE idusuario = :id");
            $st->execute(['id' => $idusuario]);
            return (bool)$st->fetchColumn();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Valida o codigo TOTP do login.
     * @return string 'ok' | 'invalido' | 'bloqueado'
     */
    public static function validarCodigoLogin(\PDO $pdo, int $idusuario, string $username, string $codigo, string $ip): string
    {
        $st = $pdo->prepare("
            SELECT COUNT(*) FROM admin_segredos_log
            WHERE idusuario = :id AND chave = 'login' AND sucesso = false
              AND created_at > NOW() - INTERVAL '" . self::JANELA_MIN . " minutes'
        ");
        $st->execute(['id' => $idusuario]);
        if ((int)$st->fetchColumn() >= self::MAX_FALHAS) {
            return 'bloqueado';
        }

        $st = $pdo->prepare("SELECT segredo_cifrado, ultimo_contador FROM admin_2fa WHERE idusuario = :id AND ativo = true");
        $st->execute(['id' => $idusuario]);
        $reg = $st->fetch(\PDO::FETCH_ASSOC);
        $segredo = $reg ? SecretVault::decifrar($reg['segredo_cifrado']) : '';
        $contador = $segredo ? Totp::verificar($segredo, $codigo, (int)$reg['ultimo_contador']) : null;

        if ($contador !== null) {
            $pdo->prepare("UPDATE admin_2fa SET ultimo_contador = :c WHERE idusuario = :id")
                ->execute(['c' => $contador, 'id' => $idusuario]);
        }
        self::log($pdo, $idusuario, $username, $ip, $contador !== null);
        return $contador !== null ? 'ok' : 'invalido';
    }

    private static function log(\PDO $pdo, int $idusuario, string $username, string $ip, bool $ok): void
    {
        try {
            $pdo->prepare("
                INSERT INTO admin_segredos_log (chave, acao, idusuario, username, ip, sucesso)
                VALUES ('login', '2fa', :i, :u, :ip, :s)
            ")->execute(['i' => $idusuario, 'u' => $username, 'ip' => $ip, 's' => $ok ? 'true' : 'false']);
        } catch (\Throwable $e) {
            error_log('[SegurancaUsuario] log falhou: ' . $e->getMessage());
        }
    }
}
