<?php
namespace Nutricional\Services;

/**
 * Cofre de segredos: guarda tokens/chaves de integracao cifrados (AES-256-GCM)
 * na tabela sistema_segredos. O valor do banco tem prioridade sobre o .env,
 * que continua como fallback. Segredos de boot (DB_*, CHAVE_SECRETA, API_TOKEN,
 * CRON_TOKEN, JWT_SECRET) permanecem no .env porque sao necessarios antes do banco.
 */
class SecretVault
{
    private const PREFIXO = 'v1:';

    /** Chaves gerenciaveis pelo Admin: chave => [rotulo, grupo, descricao] */
    public const CATALOGO = [
        'COBLI_API_KEY'        => ['Cobli - API Key', 'Frota', 'Chave da API Cobli (rastreamento da frota)'],
        'COBLI_WEBHOOK_SECRET' => ['Cobli - Webhook Secret', 'Frota', 'Segredo para validar webhooks enviados pela Cobli'],
        'GOOGLE_MAPS_API_KEY'  => ['Google Maps - API Key', 'Frota', 'Geocodificacao e rotas'],
        'MAIL_PASS'            => ['SMTP - Senha', 'E-mail', 'Senha do usuario SMTP (MAIL_USER continua no .env)'],
        'FLEX_API_TOKEN'       => ['Saldo Flex (Mercos) - Token', 'Integracoes', 'Token da API de saldo Flex'],
    ];

    private static array $cache = [];
    private static bool $tabelaOk = false;

    /** Valor em claro: banco (cifrado) > .env > ''. Nunca lanca excecao. */
    public static function get(string $chave): string
    {
        if (array_key_exists($chave, self::$cache)) {
            return self::$cache[$chave];
        }
        $valor = '';
        try {
            $v = self::lerDoBanco($chave);
            if ($v !== null && $v !== '') {
                $valor = $v;
            }
        } catch (\Throwable $e) {
            error_log('[SecretVault] leitura falhou (' . $chave . '): ' . $e->getMessage());
        }
        if ($valor === '') {
            $valor = (string)($_ENV[$chave] ?? (getenv($chave) ?: ''));
        }
        return self::$cache[$chave] = $valor;
    }

    public static function set(string $chave, string $valor, ?string $usuario = null): void
    {
        self::exigirChaveValida($chave);
        self::garantirTabela();
        $stmt = \getPDO()->prepare("
            INSERT INTO sistema_segredos (chave, valor_cifrado, atualizado_por, updated_at)
            VALUES (:c, :v, :u, NOW())
            ON CONFLICT (chave) DO UPDATE
               SET valor_cifrado = EXCLUDED.valor_cifrado,
                   atualizado_por = EXCLUDED.atualizado_por,
                   updated_at = NOW()
        ");
        $stmt->execute(['c' => $chave, 'v' => self::cifrar($valor), 'u' => $usuario]);
        unset(self::$cache[$chave]);
    }

    /** Remove o valor do banco (volta a valer o .env, se existir). */
    public static function limpar(string $chave): void
    {
        self::exigirChaveValida($chave);
        self::garantirTabela();
        \getPDO()->prepare("DELETE FROM sistema_segredos WHERE chave = :c")->execute(['c' => $chave]);
        unset(self::$cache[$chave]);
    }

    /** Estado de cada chave do catalogo, sem expor o valor (apenas mascara). */
    public static function listar(): array
    {
        self::garantirTabela();
        $linhas = \getPDO()->query("SELECT chave, valor_cifrado, atualizado_por, updated_at FROM sistema_segredos")
            ->fetchAll(\PDO::FETCH_ASSOC);
        $doBanco = [];
        foreach ($linhas as $l) {
            $doBanco[$l['chave']] = $l;
        }

        $saida = [];
        foreach (self::CATALOGO as $chave => [$rotulo, $grupo, $descricao]) {
            $origem = 'vazio';
            $valor = '';
            $atualizado = null;
            if (isset($doBanco[$chave])) {
                $valor = self::decifrar($doBanco[$chave]['valor_cifrado']) ?? '';
                $origem = $valor !== '' ? 'banco' : 'vazio';
                $atualizado = ['por' => $doBanco[$chave]['atualizado_por'], 'em' => $doBanco[$chave]['updated_at']];
            }
            if ($valor === '' && !empty($_ENV[$chave])) {
                $valor = (string)$_ENV[$chave];
                $origem = 'env';
            }
            $saida[] = [
                'chave'      => $chave,
                'rotulo'     => $rotulo,
                'grupo'      => $grupo,
                'descricao'  => $descricao,
                'configurado' => $valor !== '',
                'origem'     => $origem,
                'mascara'    => self::mascarar($valor),
                'atualizado' => $atualizado,
            ];
        }
        return $saida;
    }

    public static function mascarar(string $valor): string
    {
        if ($valor === '') {
            return '';
        }
        $fim = strlen($valor) > 8 ? substr($valor, -4) : '';
        return '••••••••' . $fim;
    }

    public static function chaveValida(string $chave): bool
    {
        return isset(self::CATALOGO[$chave]);
    }

    // ------------------------------------------------------------------
    // Criptografia
    // ------------------------------------------------------------------

    public static function cifrar(string $texto): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $ct = openssl_encrypt($texto, 'aes-256-gcm', self::chaveMestra(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($ct === false) {
            throw new \RuntimeException('Falha ao cifrar segredo');
        }
        return self::PREFIXO . base64_encode($iv . $tag . $ct);
    }

    public static function decifrar(string $cifrado): ?string
    {
        if (strncmp($cifrado, self::PREFIXO, strlen(self::PREFIXO)) !== 0) {
            return null;
        }
        $bin = base64_decode(substr($cifrado, strlen(self::PREFIXO)), true);
        if ($bin === false || strlen($bin) < 29) {
            return null;
        }
        $txt = openssl_decrypt(substr($bin, 28), 'aes-256-gcm', self::chaveMestra(), OPENSSL_RAW_DATA, substr($bin, 0, 12), substr($bin, 12, 16));
        return $txt === false ? null : $txt;
    }

    /** APP_ENC_KEY (recomendado, dedicada) ou, na falta dela, CHAVE_SECRETA. */
    private static function chaveMestra(): string
    {
        $base = (string)($_ENV['APP_ENC_KEY'] ?? '');
        if ($base === '') {
            $base = (string)($_ENV['CHAVE_SECRETA'] ?? (defined('CHAVE_SECRETA') ? CHAVE_SECRETA : ''));
        }
        if ($base === '') {
            throw new \RuntimeException('Chave mestra de criptografia ausente (APP_ENC_KEY/CHAVE_SECRETA)');
        }
        return hash('sha256', 'portal-vault|' . $base, true);
    }

    // ------------------------------------------------------------------
    // Banco
    // ------------------------------------------------------------------

    private static function lerDoBanco(string $chave): ?string
    {
        if (!self::chaveValida($chave)) {
            return null;
        }
        self::garantirTabela();
        $stmt = \getPDO()->prepare("SELECT valor_cifrado FROM sistema_segredos WHERE chave = :c");
        $stmt->execute(['c' => $chave]);
        $cifrado = $stmt->fetchColumn();
        return $cifrado === false ? null : self::decifrar((string)$cifrado);
    }

    private static function garantirTabela(): void
    {
        if (self::$tabelaOk) {
            return;
        }
        // Sem permissao de DDL: rodar docs/segredos_migration.sql com o dono do banco.
        try {
            if (\getPDO()->query("SELECT to_regclass('sistema_segredos') IS NOT NULL")->fetchColumn()) {
                self::$tabelaOk = true;
                return;
            }
            \getPDO()->exec("
                CREATE TABLE IF NOT EXISTS sistema_segredos (
                    chave VARCHAR(80) PRIMARY KEY,
                    valor_cifrado TEXT NOT NULL,
                    atualizado_por VARCHAR(100),
                    updated_at TIMESTAMP DEFAULT NOW()
                )
            ");
        } catch (\Throwable $e) {
            error_log('[SecretVault] CREATE TABLE ignorado: ' . $e->getMessage());
        }
        self::$tabelaOk = true;
    }

    private static function exigirChaveValida(string $chave): void
    {
        if (!self::chaveValida($chave)) {
            throw new \InvalidArgumentException('Chave de segredo nao permitida: ' . $chave);
        }
    }
}
