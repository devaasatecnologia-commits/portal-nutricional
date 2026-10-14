<?php
namespace Nutricional\Services;

use PHPMailer\PHPMailer\PHPMailer;

/**
 * Mantem o saldo Flex minimo dos gestores (configurado em flex_minimo_config).
 *
 * Diferencas em relacao a rotina antiga do ERP:
 * - trava por gestor (advisory lock): duas execucoes nunca emprestam ao mesmo tempo;
 * - saldo invalido (NaN/vazio) vira erro, nunca emprestimo;
 * - o POST nao e repetido; se o resultado e incerto o saldo e conferido (MercosFlexService);
 * - falhas so entram no log quando a mensagem muda ou a cada 30 min, e alertam por e-mail
 *   apos N falhas seguidas;
 * - se o emprestimo foi feito mas o log falhou, alerta imediato por e-mail.
 */
class FlexMinimoService
{
    private \PDO $pdo;

    public function __construct(?\PDO $pdo = null)
    {
        $this->pdo = $pdo ?? \getPDO();
    }

    /** @return array<int,array> resultado por gestor */
    public function executar(bool $simular = false): array
    {
        $configs = $this->pdo->query('SELECT * FROM flex_minimo_config WHERE ativo ORDER BY id')->fetchAll(\PDO::FETCH_ASSOC);
        $saida = [];
        foreach ($configs as $cfg) {
            $saida[] = $this->processar($cfg, $simular);
        }
        return $saida;
    }

    private function processar(array $cfg, bool $simular): array
    {
        $gestor = (int)$cfg['gestor_id'];
        $filial = (int)$cfg['filial'];
        $res = ['gestor' => $gestor, 'filial' => $filial, 'acao' => 'nenhuma'];

        $lock = $this->pdo->prepare('SELECT pg_try_advisory_lock(?, ?)');
        $lock->execute([7101, $gestor]);
        if (!$lock->fetchColumn()) {
            $res['acao'] = 'ocupado';
            return $res;
        }

        try {
            $api = new MercosFlexService($filial);
            $saldo = $api->saldoDe($gestor, true);
            if ($saldo === null || !is_finite($saldo)) {
                throw new \RuntimeException("Saldo do gestor $gestor invalido ou nao encontrado na API Mercos.");
            }
            $res['saldo'] = $saldo;

            $minimo = (float)$cfg['valor_minimo'];
            $passo = (float)$cfg['valor_emprestimo'];
            $this->atualizar($cfg['id'], 'ultima_verificacao = NOW(), ultimo_saldo = ?', [$saldo]);

            if ($saldo >= $minimo) {
                $this->registrarSucesso($cfg);
                return $res;
            }

            $multiplos = max(1, min((int)ceil(($minimo - $saldo) / $passo), (int)$cfg['max_multiplos']));
            $valor = round($multiplos * $passo, 2);
            $res['acao'] = $simular ? 'simulado' : 'emprestimo';
            $res['valor'] = $valor;
            if ($simular) {
                return $res;
            }

            try {
                $api->movimentar($gestor, $valor, $cfg['motivo']);
            } catch (\Throwable $e) {
                $res['acao'] = 'falha';
                $this->registrarFalha($cfg, $e->getMessage(), $e instanceof MercosIndisponivelException && str_contains($e->getMessage(), 'incerto'));
                $res['erro'] = $e->getMessage();
                return $res;
            }

            $this->registrarEmprestimo($cfg, $valor, $saldo);
            $this->registrarSucesso($cfg);
        } catch (\Throwable $e) {
            $res['acao'] = 'falha';
            $res['erro'] = $e->getMessage();
            $this->registrarFalha($cfg, $e->getMessage(), false);
        } finally {
            $unlock = $this->pdo->prepare('SELECT pg_advisory_unlock(?, ?)');
            $unlock->execute([7101, $gestor]);
        }
        return $res;
    }

    private function registrarEmprestimo(array $cfg, float $valor, float $saldoAntes): void
    {
        $this->atualizar($cfg['id'], 'ultimo_emprestimo_em = NOW()', []);
        $inserido = false;
        for ($i = 0; $i < 2 && !$inserido; $i++) {
            try {
                $this->pdo->prepare('INSERT INTO log_debito_flex_gestor (cdgestor, valor_emprestimo, motivo, data, idfilial) VALUES (?, ?, ?, CURRENT_DATE, ?)')
                    ->execute([$cfg['gestor_id'], $valor, $cfg['motivo'], $cfg['filial']]);
                $inserido = true;
            } catch (\Throwable $e) {
                error_log('[flex-minimo] falha ao gravar log: ' . $e->getMessage());
            }
        }

        $fmt = fn($v) => 'R$ ' . number_format($v, 2, ',', '.');
        $aviso = $inserido ? '' : '<p style="color:#b91c1c"><strong>ATENÇÃO:</strong> o empréstimo foi feito mas NÃO foi gravado em log_debito_flex_gestor. Registre manualmente.</p>';
        $this->email($cfg['emails'], ($inserido ? '' : '[URGENTE] ') . 'Flex mínimo - empréstimo ' . $cfg['nome'],
            '<h2>Empréstimo realizado</h2>'
            . $aviso
            . '<p><b>Gestor:</b> ' . htmlspecialchars($cfg['nome']) . '</p>'
            . '<p><b>Valor:</b> ' . $fmt($valor) . '</p>'
            . '<p><b>Saldo anterior:</b> ' . $fmt($saldoAntes) . '</p>'
            . '<p><b>Filial:</b> ' . (int)$cfg['filial'] . '</p>'
            . '<p><b>Motivo:</b> ' . htmlspecialchars($cfg['motivo']) . '</p>');
    }

    private function registrarSucesso(array $cfg): void
    {
        if ((int)$cfg['falhas_seguidas'] > 0) {
            $this->atualizar($cfg['id'], 'falhas_seguidas = 0, alerta_falha_enviado = FALSE', []);
            if ($cfg['alerta_falha_enviado']) {
                $this->email($cfg['emails'], 'Flex mínimo - normalizado ' . $cfg['nome'],
                    '<p>A verificação do saldo Flex de <b>' . htmlspecialchars($cfg['nome']) . '</b> voltou a funcionar.</p>');
            }
        }
    }

    private function registrarFalha(array $cfg, string $mensagem, bool $incerto): void
    {
        $falhas = (int)$cfg['falhas_seguidas'] + 1;
        $mudou = trim((string)$cfg['ultimo_erro']) !== trim($mensagem);
        $velho = empty($cfg['ultimo_erro_em']) || strtotime($cfg['ultimo_erro_em']) < time() - 1800;

        $this->atualizar($cfg['id'], 'falhas_seguidas = ?, ultimo_erro = ?, ultimo_erro_em = ' . ($mudou || $velho ? 'NOW()' : 'ultimo_erro_em'), [$falhas, $mensagem]);

        if ($mudou || $velho) {
            try {
                $this->pdo->prepare('INSERT INTO log_debito_flex_gestor (cdgestor, valor_emprestimo, motivo, data, idfilial) VALUES (?, 0, ?, CURRENT_DATE, ?)')
                    ->execute([$cfg['gestor_id'], 'Falha: ' . $mensagem, $cfg['filial']]);
            } catch (\Throwable $e) {
                error_log('[flex-minimo] falha ao gravar log de erro: ' . $e->getMessage());
            }
        }

        $deveAlertar = $incerto || ($falhas >= (int)$cfg['falhas_para_alertar'] && !$cfg['alerta_falha_enviado']);
        if ($deveAlertar) {
            $this->atualizar($cfg['id'], 'alerta_falha_enviado = TRUE', []);
            $this->email($cfg['emails'], '[ALERTA] Flex mínimo - ' . $cfg['nome'],
                '<h2>Falha na rotina de Flex mínimo</h2>'
                . '<p><b>Gestor:</b> ' . htmlspecialchars($cfg['nome']) . ' (filial ' . (int)$cfg['filial'] . ')</p>'
                . '<p><b>Falhas seguidas:</b> ' . $falhas . '</p>'
                . '<p><b>Erro:</b> ' . htmlspecialchars($mensagem) . '</p>'
                . ($incerto ? '<p><b>Verifique manualmente</b> se o empréstimo foi aplicado antes de qualquer nova ação.</p>' : ''));
        }
    }

    private function atualizar($id, string $set, array $params): void
    {
        $params[] = $id;
        $this->pdo->prepare("UPDATE flex_minimo_config SET $set WHERE id = ?")->execute($params);
    }

    private function email(?string $destinos, string $assunto, string $html): void
    {
        if (!$destinos) {
            return;
        }
        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $_ENV['MAIL_HOST'] ?? 'mail.nutricionalbr.com';
            $mail->SMTPAuth = true;
            $mail->Username = $_ENV['MAIL_USER'] ?? 'nao-responder@nutricionalbr.com';
            $mail->Password = $_ENV['MAIL_PASS'] ?? '';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = (int)($_ENV['MAIL_PORT'] ?? 465);
            $mail->CharSet = 'UTF-8';
            $mail->isHTML(true);
            $mail->setFrom($_ENV['MAIL_USER'] ?? 'nao-responder@nutricionalbr.com', 'Nutricional');
            foreach (explode(',', $destinos) as $e) {
                if (filter_var(trim($e), FILTER_VALIDATE_EMAIL)) {
                    $mail->addAddress(trim($e));
                }
            }
            $mail->Subject = $assunto;
            $mail->Body = $html;
            $mail->send();
        } catch (\Throwable $e) {
            error_log('[flex-minimo] erro ao enviar e-mail: ' . $e->getMessage());
        }
    }
}
