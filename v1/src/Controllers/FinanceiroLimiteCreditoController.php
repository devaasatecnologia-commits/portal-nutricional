<?php

namespace Nutricional\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PDO;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

class FinanceiroLimiteCreditoController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = \getPDO();
    }

    private function json(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    private function erro(Response $response, string $msg, int $status = 400): Response
    {
        return $this->json($response, ['success' => false, 'error' => $msg], $status);
    }

    private function usuario(Request $request): array
    {
        $user = $request->getAttribute('user') ?? [];
        $username = strtoupper((string)($user['username'] ?? 'PORTAL'));
        return [
            'idusuario' => (int)($user['idusuario'] ?? 0),
            'idcliforemp' => (int)($user['uid'] ?? $user['idcliforemp'] ?? 0),
            'username' => $username,
            'is_admin' => (bool)($user['is_admin'] ?? false) || in_array('admin', $user['permissoes'] ?? [], true)
        ];
    }

    /**
     * GET /v1/financeiro/limite-credito/analise
     * Retorna os KPIs consolidados e a lista de clientes para análise/atualização.
     */
    public function analise(Request $request, Response $response): Response
    {
        try {
            $q = $request->getQueryParams();
            $busca = trim((string)($q['busca'] ?? ''));
            $statusPagto = trim((string)($q['statuspagto'] ?? 'todos'));
            $tipoAlt = trim((string)($q['tipo_alteracao'] ?? 'todos')); // todos, aumento, reducao, sem_alteracao
            $apenasDiferencas = !isset($q['apenas_diferencas']) || $q['apenas_diferencas'] === 'true' || $q['apenas_diferencas'] === '1';
            $page = max(1, (int)($q['page'] ?? 1));
            $limit = max(10, min(200, (int)($q['limit'] ?? 50)));
            $offset = ($page - 1) * $limit;

            // 1. KPIs Consolidados Rápidos
            $kpisSql = "
                SELECT 
                    COUNT(*) AS total_analisados,
                    COUNT(CASE WHEN ABS(COALESCE(limite_atual, 0) - novo_limite_sugerido) > 0.99 THEN 1 END) AS total_com_alteracao,
                    COUNT(CASE WHEN novo_limite_sugerido > COALESCE(limite_atual, 0) + 0.99 THEN 1 END) AS total_aumentos,
                    COUNT(CASE WHEN novo_limite_sugerido < COALESCE(limite_atual, 0) - 0.99 THEN 1 END) AS total_reducoes,
                    ROUND(COALESCE(SUM(CASE WHEN ABS(COALESCE(limite_atual, 0) - novo_limite_sugerido) > 0.99 THEN (novo_limite_sugerido - COALESCE(limite_atual, 0)) ELSE 0 END), 0), 2) AS impacto_liquido,
                    ROUND(COALESCE(SUM(CASE WHEN novo_limite_sugerido > COALESCE(limite_atual, 0) + 0.99 THEN (novo_limite_sugerido - COALESCE(limite_atual, 0)) ELSE 0 END), 0), 2) AS impacto_aumentos,
                    ROUND(COALESCE(SUM(CASE WHEN novo_limite_sugerido < COALESCE(limite_atual, 0) - 0.99 THEN (COALESCE(limite_atual, 0) - novo_limite_sugerido) ELSE 0 END), 0), 2) AS impacto_reducoes
                FROM analise_financeira_clientes
            ";
            $kpis = $this->pdo->query($kpisSql)->fetch(PDO::FETCH_ASSOC);

            // 2. Monta filtros da listagem
            $where = ['1=1'];
            $params = [];

            if ($apenasDiferencas && $tipoAlt === 'todos') {
                $where[] = 'ABS(COALESCE(limite_atual, 0) - novo_limite_sugerido) > 0.99';
            }

            if ($tipoAlt === 'aumento') {
                $where[] = 'novo_limite_sugerido > COALESCE(limite_atual, 0) + 0.99';
            } elseif ($tipoAlt === 'reducao') {
                $where[] = 'novo_limite_sugerido < COALESCE(limite_atual, 0) - 0.99';
            } elseif ($tipoAlt === 'sem_alteracao') {
                $where[] = 'ABS(COALESCE(limite_atual, 0) - novo_limite_sugerido) <= 0.99';
            }

            if ($statusPagto !== '' && $statusPagto !== 'todos') {
                $where[] = 'classificacao_pagamento = :statuspagto';
                $params['statuspagto'] = $statusPagto;
            }

            if ($busca !== '') {
                $where[] = '(nome_cliente ILIKE :busca OR CAST(cod_cliente AS TEXT) ILIKE :busca)';
                $params['busca'] = '%' . $busca . '%';
            }

            $whereStr = implode(' AND ', $where);

            // Contagem total para paginação
            $stCount = $this->pdo->prepare("SELECT COUNT(*) FROM analise_financeira_clientes WHERE $whereStr");
            $stCount->execute($params);
            $totalFiltrados = (int)$stCount->fetchColumn();

            // Consulta paginada
            $sqlItens = "
                SELECT 
                    cod_cliente AS cdcliente,
                    nome_cliente AS cliente,
                    inativo,
                    ROUND(COALESCE(limite_atual, 0)::numeric, 2) AS limiteanterior,
                    ROUND(novo_limite_sugerido::numeric, 2) AS novolimite,
                    ROUND((novo_limite_sugerido - COALESCE(limite_atual, 0))::numeric, 2) AS diferenca,
                    classificacao_pagamento AS statuspagto,
                    ROUND(COALESCE(media_mensal_vendas, 0)::numeric, 2) AS media_mensal_vendas,
                    COALESCE(maior_atraso, 0) AS maior_atraso,
                    COALESCE(media_dias_prazo, 0) AS media_dias_prazo,
                    COALESCE(titulos_em_aberto, 0) AS titulos_em_aberto,
                    data_ultima_compra,
                    status_atividade
                FROM analise_financeira_clientes
                WHERE $whereStr
                ORDER BY ABS(novo_limite_sugerido - COALESCE(limite_atual, 0)) DESC, nome_cliente ASC
                LIMIT :limit OFFSET :offset
            ";

            $stmt = $this->pdo->prepare($sqlItens);
            foreach ($params as $k => $v) {
                $stmt->bindValue($k, $v);
            }
            $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->json($response, [
                'success' => true,
                'kpis' => $kpis,
                'paginacao' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $totalFiltrados,
                    'total_paginas' => ceil($totalFiltrados / $limit)
                ],
                'itens' => $itens
            ]);
        } catch (\Throwable $e) {
            return $this->erro($response, 'Erro ao carregar análise de crédito: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /v1/financeiro/limite-credito/preview-email
     * Retorna o HTML gerado para envio ou pré-visualização.
     */
    public function previewEmail(Request $request, Response $response): Response
    {
        try {
            $html = $this->gerarHtmlRelatorio();
            return $this->json($response, [
                'success' => true,
                'html' => $html
            ]);
        } catch (\Throwable $e) {
            return $this->erro($response, 'Erro ao gerar pré-visualização: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /v1/financeiro/limite-credito/enviar-email
     * Dispara o e-mail consolidado para os destinatários informados.
     */
    public function enviarEmail(Request $request, Response $response): Response
    {
        try {
            $body = json_decode($request->getBody()->getContents(), true) ?? [];
            $destinatario = trim((string)($body['email_destino'] ?? 'financeiro@nutricionalbr.com'));
            $assunto = trim((string)($body['assunto'] ?? 'Relatório de Alterações de Limite de Crédito - Portal Nutricional'));

            if (!filter_var($destinatario, FILTER_VALIDATE_EMAIL)) {
                return $this->erro($response, 'Endereço de e-mail inválido.');
            }

            $html = $this->gerarHtmlRelatorio();

            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $_ENV['MAIL_HOST'] ?? 'mail.nutricionalbr.com';
            $mail->SMTPAuth = true;
            $mail->Username = $_ENV['MAIL_USER'] ?? 'nao-responder@nutricionalbr.com';
            $mail->Password = \Nutricional\Services\SecretVault::get('MAIL_PASS');
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = (int)($_ENV['MAIL_PORT'] ?? 465);
            $mail->CharSet = 'UTF-8';
            $mail->isHTML(true);
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];
            $mail->setFrom($_ENV['MAIL_USER'] ?? 'nao-responder@nutricionalbr.com', 'Portal Nutricional');
            $mail->addAddress($destinatario);
            $mail->Subject = $assunto;
            $mail->Body = $html;

            $mail->send();

            return $this->json($response, [
                'success' => true,
                'message' => "Relatório enviado com sucesso para {$destinatario}!"
            ]);
        } catch (\Throwable $e) {
            return $this->erro($response, 'Falha ao enviar e-mail: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /v1/financeiro/limite-credito/aplicar
     * Aplica o novo limite de crédito na tabela cliente do ERP EMA.
     */
    public function aplicar(Request $request, Response $response): Response
    {
        try {
            $body = json_decode($request->getBody()->getContents(), true) ?? [];
            $modo = $body['modo'] ?? 'selecionados'; // 'todos' ou 'selecionados'
            $clientesIds = (array)($body['clientes_ids'] ?? []);
            $u = $this->usuario($request);

            $this->pdo->beginTransaction();

            if ($modo === 'selecionados') {
                if (empty($clientesIds)) {
                    return $this->erro($response, 'Nenhum cliente selecionado para atualização.');
                }

                $inIds = implode(',', array_map('intval', $clientesIds));

                $sqlUpdate = "
                    UPDATE cliente c
                    SET valorlimitecredito = sub.novo_limite_sugerido
                    FROM analise_financeira_clientes sub
                    WHERE c.idcliforemp = sub.cod_cliente
                      AND c.idcliforemp IN ($inIds)
                      AND ABS(COALESCE(sub.limite_atual, 0) - sub.novo_limite_sugerido) > 0.99
                ";
            } else {
                // Atualiza todos com diferença > 0.99
                $sqlUpdate = "
                    UPDATE cliente c
                    SET valorlimitecredito = sub.novo_limite_sugerido
                    FROM analise_financeira_clientes sub
                    WHERE c.idcliforemp = sub.cod_cliente
                      AND ABS(COALESCE(sub.limite_atual, 0) - sub.novo_limite_sugerido) > 0.99
                ";
            }

            $afetados = $this->pdo->exec($sqlUpdate);

            // Registro na timeline do chamado #33 (Aposentadoria DOX)
            $stmtOp = $this->pdo->prepare("
                INSERT INTO chamado_operacao (
                    idchamado, idoperacao, descricao, datahora, usuario, acao, tipo, detalhes
                ) VALUES (
                    33,
                    (SELECT COALESCE(MAX(idoperacao), 0) + 1 FROM chamado_operacao WHERE idchamado = 33),
                    :descricao,
                    NOW(),
                    :usuario,
                    2,
                    'execucao',
                    :detalhes
                )
            ");
            $stmtOp->execute([
                'descricao' => "Atualizacao de Limite de Credito Executada no ERP ($afetados clientes)",
                'usuario' => $u['username'],
                'detalhes' => "Executada atualizacao automatica de limites de credito via Portal Nutricional (Rotina DOX #6 Aposentada). Modo: {$modo}. Total de clientes atualizados: {$afetados}."
            ]);

            $this->pdo->commit();

            return $this->json($response, [
                'success' => true,
                'total_atualizados' => $afetados,
                'message' => "Limite de crédito atualizado com sucesso no ERP EMA para {$afetados} clientes!"
            ]);
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return $this->erro($response, 'Erro ao aplicar limites de crédito: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Gera o HTML completo do relatório de alterações
     */
    public function gerarHtmlRelatorio(): string
    {
        $stmt = $this->pdo->query("
            SELECT 
                cod_cliente as cdcliente, 
                nome_cliente as cliente,
                COALESCE(limite_atual, 0) as limiteanterior,
                novo_limite_sugerido as novolimite, 
                classificacao_pagamento as statuspagto,
                ABS(novo_limite_sugerido - COALESCE(limite_atual, 0)) as diff_abs
            FROM analise_financeira_clientes 
            WHERE ABS(COALESCE(limite_atual, 0) - novo_limite_sugerido) > 0.99
            ORDER BY diff_abs DESC, nome_cliente ASC
            LIMIT 500
        ");
        $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalGeral = (int)$this->pdo->query("
            SELECT COUNT(*) 
            FROM analise_financeira_clientes 
            WHERE ABS(COALESCE(limite_atual, 0) - novo_limite_sugerido) > 0.99
        ")->fetchColumn();

        $linhasTabela = '';
        foreach ($dados as $item) {
            $limiteAntVal = (float)$item['limiteanterior'];
            $novoLimVal = (float)$item['novolimite'];
            $diff = $novoLimVal - $limiteAntVal;

            $limiteAntFmt = 'R$ ' . number_format($limiteAntVal, 2, ',', '.');
            $novoLimFmt = 'R$ ' . number_format($novoLimVal, 2, ',', '.');
            $diffFmt = ($diff >= 0 ? '+R$ ' : '-R$ ') . number_format(abs($diff), 2, ',', '.');
            $diffCor = $diff >= 0 ? '#16a34a' : '#dc2626';

            $status = strtoupper((string)$item['statuspagto']);
            $statusCor = '#2563eb';
            if (str_contains($status, 'EXCELENTE') || $status === 'BOM') {
                $statusCor = '#16a34a';
            } elseif (str_contains($status, 'REGULAR')) {
                $statusCor = '#d97706';
            } elseif (str_contains($status, 'RUIM')) {
                $statusCor = '#dc2626';
            }

            $linhasTabela .= "
                <tr>
                    <td style='padding: 10px; border: 1px solid #e2e8f0; font-family: monospace; font-size: 12px;'>{$item['cdcliente']}</td>
                    <td style='padding: 10px; border: 1px solid #e2e8f0; font-weight: 600;'>{$item['cliente']}</td>
                    <td style='padding: 10px; border: 1px solid #e2e8f0; text-align: right;'>{$limiteAntFmt}</td>
                    <td style='padding: 10px; border: 1px solid #e2e8f0; text-align: right; font-weight: bold;'>{$novoLimFmt}</td>
                    <td style='padding: 10px; border: 1px solid #e2e8f0; text-align: right; font-weight: bold; color: {$diffCor};'>{$diffFmt}</td>
                    <td style='padding: 10px; border: 1px solid #e2e8f0; text-align: center;'>
                        <span style='padding: 4px 8px; border-radius: 6px; background-color: {$statusCor}; color: #ffffff; font-size: 11px; font-weight: bold;'>
                            {$status}
                        </span>
                    </td>
                </tr>
            ";
        }

        $dataHoje = date('d/m/Y');
        $ano = date('Y');
        $dadosCount = count($dados);

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; margin: 0; padding: 24px; background-color: #f8fafc; color: #1e293b; }
        .container { max-width: 1000px; margin: 0 auto; background: #ffffff; padding: 32px; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .header { display: flex; align-items: center; justify-content: space-between; border-bottom: 2px solid #e2e8f0; padding-bottom: 16px; margin-bottom: 20px; }
        .header h2 { margin: 0; color: #0f172a; font-size: 20px; font-weight: 800; }
        .badge { background: #e0e7ff; color: #3730a3; padding: 4px 10px; border-radius: 9999px; font-size: 12px; font-weight: bold; }
        p { font-size: 14px; line-height: 1.6; color: #475569; margin: 8px 0; }
        table { width: 100%; border-collapse: collapse; margin: 24px 0; font-size: 13px; }
        th { background-color: #0f172a; color: #ffffff; font-weight: 700; text-align: left; padding: 12px 10px; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; }
        tr:nth-child(even) { background-color: #f8fafc; }
        tr:hover { background-color: #f1f5f9; }
        .footer { margin-top: 32px; padding-top: 20px; border-top: 1px solid #e2e8f0; color: #64748b; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h2>Relatório de Alterações de Limite de Crédito</h2>
                <p style="margin: 4px 0 0 0; font-size: 12px; color: #64748b;">Portal Nutricional · Inteligência Financeira Automatizada</p>
            </div>
            <span class="badge">{$dataHoje}</span>
        </div>
        
        <p>Prezados,</p>
        <p>Segue a lista consolidada de clientes com sugestão de alteração de limite de crédito baseada na análise de pagamentos de boletos (idmetodo = 6) dos últimos 6 meses:</p>
        
        <table>
            <thead>
                <tr>
                    <th style="width: 80px;">Código</th>
                    <th>Cliente</th>
                    <th style="text-align: right; width: 130px;">Limite Anterior</th>
                    <th style="text-align: right; width: 130px;">Novo Limite</th>
                    <th style="text-align: right; width: 120px;">Diferença</th>
                    <th style="text-align: center; width: 130px;">Classificação</th>
                </tr>
            </thead>
            <tbody>
                {$linhasTabela}
            </tbody>
        </table>
        
        <p style="font-size: 13px; color: #475569; margin-top: 12px;">
            Exibindo <strong>{$dadosCount}</strong> de um total de <strong>{$totalGeral}</strong> clientes com alteração sugerida no período.
        </p>
        
        <div class="footer">
            <p style="margin: 0;"><strong>Atenciosamente,</strong><br>
            Departamento Financeiro & Controladoria<br>
            <em>Portal Nutricional Distribuidora — {$ano}</em></p>
        </div>
    </div>
</body>
</html>
HTML;
    }
}
